<?php

namespace App\Modules\Ateliers\Services;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Avis;
use App\Modules\Auth\Models\User;
use App\Modules\RendezVous\Models\RendezVous;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Avis des clients. Seul un client qui a un rendez-vous terminé chez l'atelier peut en laisser un (avis vérifié) ;
 * chaque écriture recalcule note_moyenne et nb_avis de l'atelier, utilisés par la liste, le tri et la recommandation.
 */
class AvisService
{
    public const BAG = 'avis';

    public const NB_AFFICHES = 10;

    public function peutDonnerAvis(?User $user, Atelier $atelier): bool
    {
        return $user !== null && $this->rendezVousTermine($user, (string) $atelier->getKey()) !== null;
    }

    public function avisDe(?User $user, Atelier $atelier): ?Avis
    {
        if (! $user) {
            return null;
        }

        return Avis::query()
            ->where('atelier_id', (string) $atelier->getKey())
            ->where('user_id', (string) $user->getKey())
            ->first();
    }

    /**
     * Crée l'avis du client ou remplace le précédent (un seul avis par client et par atelier).
     *
     * @param  array{note: int|string, commentaire?: string|null}  $data
     */
    public function enregistrer(User $user, Atelier $atelier, array $data): Avis
    {
        $atelierId = (string) $atelier->getKey();
        $rdv = $this->rendezVousTermine($user, $atelierId);

        if (! $rdv) {
            throw ValidationException::withMessages([
                'note' => 'Vous pourrez donner votre avis après une réparation terminée chez cet atelier.',
            ])->errorBag(self::BAG);
        }

        $avis = Avis::query()->updateOrCreate(
            ['atelier_id' => $atelierId, 'user_id' => (string) $user->getKey()],
            [
                'rendez_vous_id' => (string) $rdv->getKey(),
                'note' => (int) $data['note'],
                'commentaire' => filled($data['commentaire'] ?? null) ? $data['commentaire'] : null,
            ]
        );

        $this->recalculerNote($atelierId);

        return $avis;
    }

    public function supprimer(User $user, Atelier $atelier): bool
    {
        $avis = $this->avisDe($user, $atelier);

        if (! $avis) {
            return false;
        }

        $avis->delete();
        $this->recalculerNote((string) $atelier->getKey());

        return true;
    }

    /**
     * @return array{moyenne: float, total: int, repartition: array<int, int>}
     */
    public function resume(Atelier $atelier): array
    {
        $notes = Avis::query()->where('atelier_id', (string) $atelier->getKey())->get(['note'])->pluck('note');

        return self::resumeDe($notes);
    }

    /**
     * Moyenne, total et nombre d'avis par note (de 5 à 1, pour l'affichage en barres).
     *
     * @param  Collection<int, mixed>  $notes
     * @return array{moyenne: float, total: int, repartition: array<int, int>}
     */
    public static function resumeDe(Collection $notes): array
    {
        $notes = $notes->map(fn ($note) => (int) $note)
            ->filter(fn (int $note) => $note >= Avis::NOTE_MIN && $note <= Avis::NOTE_MAX)
            ->values();

        $repartition = [];
        foreach (range(Avis::NOTE_MAX, Avis::NOTE_MIN) as $note) {
            $repartition[$note] = $notes->filter(fn (int $n) => $n === $note)->count();
        }

        return [
            'moyenne' => $notes->isEmpty() ? 0.0 : round($notes->avg(), 2),
            'total' => $notes->count(),
            'repartition' => $repartition,
        ];
    }

    /**
     * @return Collection<int, Avis>
     */
    public function recents(Atelier $atelier, int $limite = self::NB_AFFICHES): Collection
    {
        return Avis::query()
            ->where('atelier_id', (string) $atelier->getKey())
            ->with('auteur')
            ->orderByDesc('created_at')
            ->limit($limite)
            ->get();
    }

    private function rendezVousTermine(User $user, string $atelierId): ?RendezVous
    {
        return RendezVous::query()
            ->where('user_id', (string) $user->getKey())
            ->where('atelier_id', $atelierId)
            ->where('statut', RendezVous::STATUT_TERMINE)
            // Un RDV « terminé » daté d'un jour futur ne prouve pas encore une réparation.
            ->where('date_rdv', '<=', today()->endOfDay())
            ->orderByDesc('date_rdv')
            ->first();
    }

    /** Mise à jour ciblée : seuls note_moyenne et nb_avis changent dans la fiche atelier. */
    private function recalculerNote(string $atelierId): void
    {
        $resume = self::resumeDe(Avis::query()->where('atelier_id', $atelierId)->get(['note'])->pluck('note'));

        Atelier::query()->where('_id', $atelierId)->update([
            'note_moyenne' => $resume['moyenne'],
            'nb_avis' => $resume['total'],
        ]);
    }
}
