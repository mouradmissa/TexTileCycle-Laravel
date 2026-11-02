<?php

namespace App\Modules\Ateliers\Services;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Passeport;
use App\Modules\RendezVous\Models\RendezVous;
use Illuminate\Support\Carbon;

/**
 * Les « outils » de l'assistant atelier : des lectures prévues à l'avance, toujours limitées à l'atelier du compte
 * connecté (passé par le contrôleur, jamais lu dans la question). Aucune écriture. Les résultats ne contiennent
 * aucune donnée personnelle de client (ni nom, ni téléphone) : seulement des pièces, des réparations et des dates.
 * Les rendez-vous (module d'un coéquipier) sont seulement lus.
 */
class ActiviteAtelierService
{
    /** Périodes glissantes proposées : clé => nombre de jours. */
    public const PERIODES = ['semaine' => 7, 'mois' => 30, 'annee' => 365];

    public const HORIZONS = [7, 30, 90];

    public const LIMITES = [3, 5];

    /** Garde-fou de volume : au-delà, les compteurs restent justes à cette borne près. */
    private const LECTURES_MAX = 500;

    public function __construct(private AvisService $avis)
    {
    }

    /**
     * @return array{jours: int, rdv: array<string, int>, passeports: int}
     */
    public function resume(Atelier $atelier, string $periode): array
    {
        $jours = self::PERIODES[$periode] ?? self::PERIODES['mois'];
        $depuis = now()->subDays($jours)->startOfDay();

        $statuts = RendezVous::query()
            ->where('atelier_id', (string) $atelier->getKey())
            ->where('date_rdv', '>=', $depuis)
            ->where('date_rdv', '<=', now()->endOfDay())
            ->limit(self::LECTURES_MAX)
            ->get(['statut'])
            ->countBy('statut');

        $rdv = [];
        foreach (RendezVous::STATUTS as $statut) {
            $rdv[$statut] = (int) ($statuts[$statut] ?? 0);
        }

        $passeports = Passeport::query()
            ->where('atelier_id', (string) $atelier->getKey())
            ->where('created_at', '>=', $depuis)
            ->count();

        return ['jours' => $jours, 'rdv' => $rdv, 'passeports' => $passeports];
    }

    /**
     * Garanties encore valables qui se terminent dans les $jours prochains jours, de la plus proche à la plus lointaine.
     *
     * @return list<array{numero: string, piece: string, reparation: string, fin: Carbon}>
     */
    public function garantiesQuiExpirent(Atelier $atelier, int $jours): array
    {
        $jours = in_array($jours, self::HORIZONS, true) ? $jours : 30;
        $limite = now()->addDays($jours)->endOfDay();

        return Passeport::query()
            ->where('atelier_id', (string) $atelier->getKey())
            ->whereNull('revoque_le')
            ->limit(self::LECTURES_MAX)
            ->get()
            ->filter(fn (Passeport $p) => $p->garantieActive() && $p->garantieFin()->lte($limite))
            ->sortBy(fn (Passeport $p) => $p->garantieFin()->getTimestamp())
            ->map(fn (Passeport $p) => [
                'numero' => $p->numeroCourt(),
                'piece' => $p->vetementAffiche(),
                'reparation' => $p->reparationAffichee(),
                'fin' => $p->garantieFin(),
            ])
            ->values()
            ->all();
    }

    /**
     * Services classés par nombre de réparations terminées.
     *
     * @return array{total: int, services: list<array{nom: string, nb: int}>}
     */
    public function servicesLesPlusDemandes(Atelier $atelier, int $limite): array
    {
        $limite = in_array($limite, self::LIMITES, true) ? $limite : 3;

        $termines = RendezVous::query()
            ->where('atelier_id', (string) $atelier->getKey())
            ->where('statut', RendezVous::STATUT_TERMINE)
            ->with('service')
            ->limit(self::LECTURES_MAX)
            ->get();

        $services = $termines
            ->map(fn (RendezVous $rdv) => $rdv->serviceNom())
            // Service supprimé : serviceNom() rend son identifiant brut, qu'on n'affiche pas.
            ->map(fn (string $nom) => $nom === '—' || preg_match('/^[0-9a-f]{24}$/i', $nom) ? 'Service supprimé' : $nom)
            ->countBy()
            ->sortDesc()
            ->take($limite)
            ->map(fn (int $nb, string $nom) => ['nom' => $nom, 'nb' => $nb])
            ->values()
            ->all();

        return ['total' => $termines->count(), 'services' => $services];
    }

    /**
     * Réputation : moyenne, nombre d'avis et les trois derniers commentaires (sans le nom de leur auteur).
     *
     * @return array{moyenne: float, total: int, derniers: list<array{note: int, commentaire: string, date: ?Carbon}>}
     */
    public function avis(Atelier $atelier): array
    {
        $resume = $this->avis->resume($atelier);

        $derniers = $this->avis->recents($atelier, 3)
            ->map(fn ($avis) => [
                'note' => (int) $avis->note,
                'commentaire' => trim((string) $avis->commentaire),
                'date' => $avis->created_at,
            ])
            ->values()
            ->all();

        return ['moyenne' => (float) $resume['moyenne'], 'total' => (int) $resume['total'], 'derniers' => $derniers];
    }

    /**
     * Rendez-vous en attente ou confirmés dans les $jours prochains jours, par date.
     *
     * @return list<array{date: Carbon, reparation: string, piece: string, statut: string}>
     */
    public function prochainsRendezVous(Atelier $atelier, int $jours): array
    {
        $jours = in_array($jours, self::HORIZONS, true) ? $jours : 7;

        return RendezVous::query()
            ->where('atelier_id', (string) $atelier->getKey())
            ->whereIn('statut', [RendezVous::STATUT_EN_ATTENTE, RendezVous::STATUT_CONFIRME])
            ->where('date_rdv', '>=', now())
            ->where('date_rdv', '<=', now()->addDays($jours)->endOfDay())
            ->with(['service', 'vetement'])
            ->orderBy('date_rdv')
            ->limit(self::LECTURES_MAX)
            ->get()
            ->map(fn (RendezVous $rdv) => [
                'date' => $rdv->date_rdv,
                'reparation' => $rdv->serviceNom(),
                'piece' => $rdv->vetement?->displayName() ?? 'Vêtement',
                'statut' => $rdv->statutLabel(),
            ])
            ->values()
            ->all();
    }
}
