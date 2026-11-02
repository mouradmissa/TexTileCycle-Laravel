<?php

namespace App\Modules\RendezVous\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Modules\RendezVous\Http\Requests\StoreRendezVousRequest;
use App\Modules\RendezVous\Http\Requests\UpdateRendezVousRequest;
use App\Modules\RendezVous\Models\RendezVous;
use App\Modules\RendezVous\Services\RendezVousService;
use App\Modules\Vetements\Models\Vetement;
use Illuminate\Http\RedirectResponse;

/**
 * Contrôleur front-office pour le CRUD des rendez-vous.
 *
 * Toutes les actions vérifient que le citoyen connecté
 * est bien le propriétaire du rendez-vous.
 */
class RendezVousController extends Controller
{
    public function __construct(
        private RendezVousService $rendezVousService
    ) {
    }
    /* ---------------------------------------------------------------
     |  Helpers privés
     |--------------------------------------------------------------- */

    /**
     * Vérifie que le RDV appartient à l'utilisateur connecté.
     * Retourne abort(403) si ce n'est pas le cas.
     */
    private function autoriserAcces(RendezVous $rendezVous): void
    {
        if ((string) $rendezVous->user_id !== (string) auth()->id()) {
            abort(403, 'Vous n\'êtes pas autorisé à accéder à ce rendez-vous.');
        }
    }

    /**
     * Récupère les données nécessaires aux formulaires
     * (vêtements de l'utilisateur, ateliers, services).
     */
    private function donneesFormulaire(): array
    {
        $userId = (string) auth()->id();
        $vetements = Vetement::where('user_id', $userId)->orderBy('type')->get();
        $atelierPreselect = request('atelier_id', request('atelier'));

        return [
            'vetements' => $vetements,
            'ateliers' => RendezVous::catalogueAteliers(),
            'services' => RendezVous::catalogueServicesPourAtelier(is_string($atelierPreselect) ? $atelierPreselect : null),
            'servicesParAtelier' => RendezVous::catalogueServicesParAtelier(),
        ];
    }

    /* ---------------------------------------------------------------
     |  CRUD — Resource
     |--------------------------------------------------------------- */

    /**
     * Liste paginée des rendez-vous de l'utilisateur connecté.
     * Triés par date_rdv desc, avec eager loading.
     */
    public function index()
    {
        $rendezVous = RendezVous::where('user_id', (string) auth()->id())
            ->with('vetement')
            ->orderBy('date_rdv', 'desc')
            ->paginate(10);

        // Annulations récentes à reprogrammer, tant que la pièce n'est pas déjà réparée, donnée ou recyclée.
        $aReprogrammer = RendezVous::where('user_id', (string) auth()->id())
            ->where('statut', RendezVous::STATUT_ANNULE)
            ->whereNull('replanifie_id')
            ->where('date_rdv', '>=', now()->subDays(30))
            ->with('vetement')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->filter(fn (RendezVous $rdv) => $rdv->vetement && ! in_array($rdv->vetement->status, Vetement::STATUTS_HISTORIQUE, true))
            ->values();

        return view('rendez-vous.index', compact('rendezVous', 'aReprogrammer'));
    }

    /**
     * Affiche le formulaire de création d'un rendez-vous.
     * Réutilise le design existant de la page "Prendre rendez-vous".
     */
    public function create()
    {
        return view('rendez-vous.create', array_merge($this->donneesFormulaire(), [
            'atelierPreselect' => request('atelier_id', request('atelier')),
            'servicePreselect' => request('service_id', request('service')),
            'vetementPreselect' => request('vetement_id', request('vetement')),
        ]));
    }

    /**
     * Enregistre un nouveau rendez-vous.
     * user_id et statut sont définis automatiquement.
     */
    public function store(StoreRendezVousRequest $request): RedirectResponse
    {
        $this->rendezVousService->creerPourCitoyen(auth()->user(), $request->validated());

        return redirect()
            ->route('front.rdv')
            ->with('success', 'Votre rendez-vous a été envoyé à l\'atelier. La pièce apparaîtra dans son espace dès confirmation.');
    }

    /**
     * Affiche le détail d'un rendez-vous.
     */
    public function show(string $rendezVous)
    {
        $rendezVous = RendezVous::with('vetement')->findOrFail($rendezVous);
        $this->autoriserAcces($rendezVous);

        return view('rendez-vous.show', compact('rendezVous'));
    }

    /**
     * Affiche le formulaire de modification d'un rendez-vous.
     * Autorisé seulement si le statut est 'en_attente'.
     */
    public function edit(string $rendezVous)
    {
        $rendezVous = RendezVous::with('vetement')->findOrFail($rendezVous);
        $this->autoriserAcces($rendezVous);

        if (! $rendezVous->peutEtreModifie()) {
            return redirect()
                ->route('front.rdv')
                ->with('error', 'Ce rendez-vous ne peut plus être modifié.');
        }

        return view('rendez-vous.edit', array_merge(
            ['rendezVous' => $rendezVous],
            $this->donneesFormulaire()
        ));
    }

    /**
     * Met à jour un rendez-vous existant.
     * Autorisé seulement si le statut est 'en_attente'.
     */
    public function update(UpdateRendezVousRequest $request, string $rendezVous): RedirectResponse
    {
        $rendezVous = RendezVous::findOrFail($rendezVous);
        $this->autoriserAcces($rendezVous);

        if (! $rendezVous->peutEtreModifie()) {
            return redirect()
                ->route('front.rdv')
                ->with('error', 'Ce rendez-vous ne peut plus être modifié.');
        }

        $donnees = $request->validated();
        $this->rendezVousService->verifierCreneauLibre(
            (string) $donnees['atelier_id'],
            $donnees['date_rdv'],
            (int) $donnees['duree'],
            (string) $rendezVous->getKey()
        );

        $rendezVous->update($donnees);

        return redirect()
            ->route('front.rdv')
            ->with('success', 'Le rendez-vous a été mis à jour avec succès.');
    }

    /**
     * Supprime un rendez-vous.
     * Autorisé seulement si le statut est 'en_attente' ou 'annule'.
     */
    public function destroy(string $rendezVous): RedirectResponse
    {
        $rendezVous = RendezVous::findOrFail($rendezVous);
        $this->autoriserAcces($rendezVous);

        if (! $rendezVous->peutEtreSupprime()) {
            return redirect()
                ->route('front.rdv')
                ->with('error', 'Ce rendez-vous ne peut pas être supprimé.');
        }

        $rendezVous->delete();

        return redirect()
            ->route('front.rdv')
            ->with('success', 'Le rendez-vous a été supprimé.');
    }

    /* ---------------------------------------------------------------
     |  Action supplémentaire — Annulation
     |--------------------------------------------------------------- */

    /**
     * Passe le statut du rendez-vous à 'annule'.
     * Autorisé seulement si le statut est 'en_attente' ou 'confirme'.
     */
    public function annuler(string $rendezVous): RedirectResponse
    {
        $rendezVous = RendezVous::findOrFail($rendezVous);
        $this->autoriserAcces($rendezVous);

        if (! $rendezVous->peutEtreAnnule()) {
            return redirect()
                ->route('front.rdv')
                ->with('error', 'Ce rendez-vous ne peut pas être annulé.');
        }

        $rendezVous->update(['statut' => RendezVous::STATUT_ANNULE, 'annule_par' => 'citoyen']);

        return redirect()
            ->route('front.rdv')
            ->with('success', 'Le rendez-vous a été annulé.');
    }
}
