<?php

namespace App\Modules\RendezVous\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\RendezVous\Http\Requests\StoreRendezVousRequest;
use App\Modules\RendezVous\Models\RendezVous;
use App\Modules\RendezVous\Services\AssistantReservationService;
use App\Modules\RendezVous\Services\RendezVousService;
use App\Modules\RendezVous\Services\ReplanificationService;
use App\Modules\RendezVous\Services\SuggestionCreneauService;
use App\Modules\Vetements\Models\Vetement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * IA du module Rendez-vous : suggestion de créneaux et assistant de réservation (réponses JSON).
 */
class AssistantRendezVousController extends Controller
{
    public function __construct(
        private SuggestionCreneauService $creneaux,
        private AssistantReservationService $assistant,
        private RendezVousService $rendezVous,
        private ReplanificationService $replanification
    ) {
    }

    /** Les 3 meilleurs créneaux d'un atelier, pour le bouton « Suggérer un créneau » du formulaire. */
    public function creneaux(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'atelier_id' => ['required', 'string'],
            'service_id' => ['nullable', 'string'],
            'vetement_id' => ['nullable', 'string'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
            'ignorer' => ['nullable', 'string'],
        ]);

        $atelier = Atelier::query()->where('statut', Atelier::STATUT_ACTIF)->find($donnees['atelier_id']);
        if (! $atelier) {
            return response()->json(['message' => 'Choisissez d\'abord un atelier.'], 422);
        }

        $service = empty($donnees['service_id']) ? null : Service::query()->where('atelier_id', (string) $atelier->getKey())->find($donnees['service_id']);
        $vetement = empty($donnees['vetement_id']) ? null : Vetement::query()
            ->where('user_id', (string) $request->user()->getKey())
            ->find($donnees['vetement_id']);

        $duree = $this->creneaux->estimerDuree($service, $vetement, $donnees['commentaire'] ?? null);
        $resultat = $this->creneaux->suggerer($atelier, $duree['minutes'], [], 3, $donnees['ignorer'] ?? null);

        return response()->json([
            'duree' => $duree['minutes'],
            'duree_libelle' => SuggestionCreneauService::libelleDuree($duree['minutes']),
            'duree_raisons' => $duree['raisons'],
            'creneaux' => $resultat['creneaux'],
        ]);
    }

    public function discuter(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'contexte' => ['nullable', 'array'],
        ]);

        return response()->json($this->assistant->repondre(
            $request->user(),
            $donnees['message'],
            $donnees['contexte'] ?? []
        ));
    }

    /** Créneaux de remplacement d'un RDV annulé du citoyen connecté. */
    public function propositionsReplanification(Request $request, string $rendezVous): JsonResponse
    {
        $rdv = RendezVous::query()
            ->where('_id', $rendezVous)
            ->where('user_id', (string) $request->user()->getKey())
            ->firstOrFail();

        if (! $rdv->peutEtreReplanifie()) {
            return response()->json(['message' => 'Ce rendez-vous n\'est pas annulé ou a déjà été reprogrammé.'], 422);
        }

        return response()->json(['propositions' => $this->replanification->proposer($rdv)]);
    }

    public function replanifier(Request $request, string $rendezVous): JsonResponse
    {
        $regles = (new StoreRendezVousRequest())->rules();
        $donnees = $request->validate(
            array_intersect_key($regles, array_flip(['atelier_id', 'service_id', 'date_rdv', 'duree'])),
            (new StoreRendezVousRequest())->messages()
        );

        $nouveau = $this->rendezVous->replanifier($request->user(), $rendezVous, $donnees);

        return response()->json([
            'message' => 'Rendez-vous reprogrammé ! Un mail de confirmation de votre demande vient de partir.',
            'url' => route('front.rdv.show', $nouveau->getKey()),
        ], 201);
    }

    /** Réserve une proposition de l'assistant : mêmes règles et même mail que le formulaire. */
    public function reserver(Request $request): JsonResponse
    {
        $donnees = $request->validate(
            (new StoreRendezVousRequest())->rules(),
            (new StoreRendezVousRequest())->messages()
        );
        $donnees['commentaire'] = trim(($donnees['commentaire'] ?? '').' (Réservé avec l\'assistant IA.)');

        $rdv = $this->rendezVous->creerPourCitoyen($request->user(), $donnees);

        return response()->json([
            'message' => 'Rendez-vous réservé ! Un mail de confirmation de votre demande vient de partir.',
            'url' => route('front.rdv.show', $rdv->getKey()),
        ], 201);
    }
}
