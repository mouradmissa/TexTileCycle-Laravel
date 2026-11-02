<?php

namespace App\Modules\RendezVous\Services;

use App\Mail\RendezVousAnnulationMail;
use App\Mail\RendezVousConfirmationMail;
use App\Mail\RendezVousReservationMail;
use App\Modules\Ateliers\Models\Service;
use App\Modules\Auth\Models\User;
use App\Modules\RendezVous\Models\RendezVous;
use App\Modules\Vetements\Models\Vetement;
use App\Modules\Vetements\Services\VetementService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class RendezVousService
{
    public function __construct(
        private VetementService $vetements,
        private SuggestionCreneauService $creneaux,
        private ReplanificationService $replanification
    ) {
    }

    /** Refuse un créneau qui chevauche un RDV en attente ou confirmé du même atelier. */
    public function verifierCreneauLibre(string $atelierId, mixed $date, int $duree, ?string $ignorerRdvId = null): void
    {
        if (! $this->creneaux->estLibre($atelierId, Carbon::parse($date), $duree, $ignorerRdvId)) {
            throw ValidationException::withMessages([
                'date_rdv' => 'Ce créneau est déjà pris dans cet atelier. Utilisez « Suggérer un créneau » pour trouver un horaire libre.',
            ]);
        }
    }

    public function creerPourCitoyen(User $user, array $data): RendezVous
    {
        $vetement = Vetement::query()
            ->where('_id', $data['vetement_id'])
            ->where('user_id', (string) $user->getKey())
            ->first();

        if (! $vetement) {
            throw ValidationException::withMessages([
                'vetement_id' => 'Ce vêtement ne vous appartient pas.',
            ]);
        }

        $this->assertServiceDeLatelier($data['service_id'] ?? null, (string) $data['atelier_id']);
        $this->verifierCreneauLibre((string) $data['atelier_id'], $data['date_rdv'], (int) $data['duree']);

        $rdv = RendezVous::create([
            'vetement_id' => (string) $vetement->getKey(),
            'atelier_id' => (string) $data['atelier_id'],
            'service_id' => $data['service_id'] ?? null,
            'user_id' => (string) $user->getKey(),
            'date_rdv' => $data['date_rdv'],
            'duree' => $data['duree'],
            'statut' => RendezVous::STATUT_EN_ATTENTE,
            'commentaire' => $data['commentaire'] ?? null,
        ]);

        $this->vetements->assignerPourReparation($vetement, (string) $data['atelier_id']);

        $this->envoyerMail($user, $rdv, RendezVousReservationMail::class);

        return $rdv;
    }

    /**
     * Remplace un RDV annulé par un nouveau, sur un créneau proposé par l'IA de replanification.
     *
     * @param  array{atelier_id: string, service_id?: ?string, date_rdv: string, duree: int}  $data
     */
    public function replanifier(User $user, string $ancienId, array $data): RendezVous
    {
        $ancien = RendezVous::query()
            ->where('_id', $ancienId)
            ->where('user_id', (string) $user->getKey())
            ->firstOrFail();

        if (! $ancien->peutEtreReplanifie()) {
            throw ValidationException::withMessages([
                'rendez_vous' => 'Ce rendez-vous n\'est pas annulé ou a déjà été reprogrammé.',
            ]);
        }

        $nouveau = $this->creerPourCitoyen($user, [
            'vetement_id' => (string) $ancien->vetement_id,
            'atelier_id' => $data['atelier_id'],
            'service_id' => $data['service_id'] ?? null,
            'date_rdv' => $data['date_rdv'],
            'duree' => $data['duree'],
            'commentaire' => trim(($ancien->commentaire ?? '').' (Reprogrammé avec l\'IA après l\'annulation du RDV du '
                .($ancien->date_rdv?->format('d/m/Y à H\hi') ?? '—').'.)'),
        ]);

        $ancien->replanifie_id = (string) $nouveau->getKey();
        $ancien->save();

        return $nouveau;
    }

    /**
     * Le mail est un plus : son échec ne doit pas annuler l'action sur le rendez-vous.
     *
     * @param  class-string<RendezVousReservationMail|RendezVousConfirmationMail|RendezVousAnnulationMail>  $mail
     * @param  list<mixed>  $arguments  arguments supplémentaires du mail
     */
    private function envoyerMail(?User $user, RendezVous $rdv, string $mail, array $arguments = []): void
    {
        $email = trim((string) $user?->email);
        if ($email === '') {
            return;
        }

        try {
            Mail::to($email)->send(new $mail(
                $rdv->load(['vetement', 'atelier', 'service']),
                trim((string) $user->name) ?: 'bonjour',
                ...$arguments
            ));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Prévient le citoyen au passage à « confirmé », ou à « annulé » par l'atelier ou l'admin :
     * le mail d'annulation propose alors des créneaux de remplacement.
     */
    private function notifierChangementStatut(RendezVous $rdv, string $ancienStatut): void
    {
        if ($rdv->statut === $ancienStatut) {
            return;
        }

        if ($rdv->statut === RendezVous::STATUT_CONFIRME) {
            $this->envoyerMail($rdv->user, $rdv, RendezVousConfirmationMail::class);
        }

        if ($rdv->statut === RendezVous::STATUT_ANNULE && $rdv->annuleParLatelier()) {
            try {
                $propositions = $this->replanification->proposer($rdv);
            } catch (\Throwable $exception) {
                report($exception);
                $propositions = [];
            }
            $this->envoyerMail($rdv->user, $rdv, RendezVousAnnulationMail::class, [$propositions]);
        }
    }

    public function listerPourAtelier(string $atelierId, ?string $statut = null): Collection
    {
        $query = RendezVous::query()
            ->where('atelier_id', $atelierId)
            ->with(['vetement', 'user', 'service'])
            ->orderBy('date_rdv');

        if ($statut && in_array($statut, RendezVous::STATUTS, true)) {
            $query->where('statut', $statut);
        }

        return $query->get();
    }

    public function listerPourAdmin(?string $statut = null): Collection
    {
        $query = RendezVous::query()
            ->with(['vetement', 'user', 'service', 'atelier'])
            ->orderByDesc('date_rdv');

        if ($statut && in_array($statut, RendezVous::STATUTS, true)) {
            $query->where('statut', $statut);
        }

        return $query->limit(80)->get();
    }

    public function countsPourAtelier(string $atelierId): array
    {
        $parStatut = RendezVous::query()
            ->where('atelier_id', $atelierId)
            ->get(['statut'])
            ->countBy('statut');

        $counts = [];
        foreach (RendezVous::STATUTS as $statut) {
            $counts[$statut] = (int) ($parStatut[$statut] ?? 0);
        }
        $counts['total'] = array_sum($counts);

        return $counts;
    }

    public function changerStatutPourAtelier(string $rdvId, string $atelierId, string $statut): RendezVous
    {
        $rdv = RendezVous::query()->with('vetement')->findOrFail($rdvId);

        if (! $rdv->appartientALatelier($atelierId)) {
            abort(403, 'Ce rendez-vous n\'appartient pas à votre atelier.');
        }

        if (! in_array($statut, $rdv->transitionsAtelier(), true)) {
            abort(422, 'Cette action n\'est pas possible pour le statut actuel du rendez-vous.');
        }

        $ancienStatut = (string) $rdv->statut;
        $rdv->statut = $statut;
        if ($statut === RendezVous::STATUT_ANNULE) {
            $rdv->annule_par = 'atelier';
        }
        $rdv->save();

        if ($rdv->vetement) {
            $this->vetements->synchroniserDepuisRendezVous($rdv->vetement, $statut);
        }

        $this->notifierChangementStatut($rdv, $ancienStatut);

        return $rdv->fresh(['vetement', 'user', 'service']) ?? $rdv;
    }

    public function changerStatutPourAdmin(string $rdvId, string $statut): RendezVous
    {
        if (! in_array($statut, RendezVous::STATUTS, true)) {
            abort(422, 'Statut de rendez-vous invalide.');
        }

        $rdv = RendezVous::query()->with('vetement')->findOrFail($rdvId);
        $ancienStatut = (string) $rdv->statut;
        $rdv->statut = $statut;
        if ($statut === RendezVous::STATUT_ANNULE && $ancienStatut !== RendezVous::STATUT_ANNULE) {
            $rdv->annule_par = 'admin';
        }
        $rdv->save();

        if ($rdv->vetement) {
            $this->vetements->synchroniserDepuisRendezVous($rdv->vetement, $statut);
        }

        $this->notifierChangementStatut($rdv, $ancienStatut);

        return $rdv->fresh(['vetement', 'user', 'service', 'atelier']) ?? $rdv;
    }

    private function assertServiceDeLatelier(?string $serviceId, string $atelierId): void
    {
        if ($serviceId === null || $serviceId === '') {
            return;
        }

        $service = Service::query()->find($serviceId);

        if ($service && (string) $service->atelier_id !== (string) $atelierId) {
            throw ValidationException::withMessages([
                'service_id' => 'Ce service n\'est pas proposé par l\'atelier choisi.',
            ]);
        }
    }
}
