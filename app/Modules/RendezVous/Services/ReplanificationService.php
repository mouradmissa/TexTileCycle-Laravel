<?php

namespace App\Modules\RendezVous\Services;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\RendezVous\Models\RendezVous;

/**
 * IA du module Rendez-vous : créneaux de remplacement après une annulation.
 *
 * Priorité au même atelier, à la même période de la journée que le RDV annulé
 * (le citoyen avait choisi ce moment) ; puis le meilleur créneau d'un autre atelier
 * qui propose le même service, au cas où l'atelier d'origine serait indisponible.
 */
class ReplanificationService
{
    private const MAX_AUTRES_ATELIERS = 4;

    public function __construct(
        private SuggestionCreneauService $creneaux
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function proposer(RendezVous $rdv, int $limite = 3): array
    {
        $rdv->loadMissing(['atelier', 'service']);
        $duree = $this->creneaux->arrondirDuree(max(15, (int) $rdv->duree));
        $preferences = ['plage' => $this->plage($rdv)];
        $serviceNom = $rdv->service?->nom;

        $memeAtelier = [];
        $atelier = $rdv->atelier;
        if ($atelier && $atelier->statut === Atelier::STATUT_ACTIF) {
            $resultat = $this->creneaux->suggerer($atelier, $duree, $preferences, $limite);
            foreach ($resultat['creneaux'] as $creneau) {
                if (! $this->chevaucheOriginal($creneau, $rdv)) {
                    $memeAtelier[] = $this->creneaux->proposition($creneau, $atelier, $rdv->service) + ['meme_atelier' => true];
                }
            }
        }

        $autres = [];
        $ateliers = Atelier::query()
            ->where('statut', Atelier::STATUT_ACTIF)
            ->where('_id', '!=', (string) $rdv->atelier_id)
            ->with('services')
            ->get()
            ->map(fn (Atelier $a) => [
                'atelier' => $a,
                'service' => $serviceNom ? $a->services->first(fn (Service $s) => $s->nom === $serviceNom) : null,
            ])
            ->filter(fn (array $c) => ! $serviceNom || $c['service'] !== null)
            ->sortByDesc(fn (array $c) => (float) $c['atelier']->note_moyenne)
            ->take(self::MAX_AUTRES_ATELIERS);

        foreach ($ateliers as ['atelier' => $autre, 'service' => $service]) {
            $resultat = $this->creneaux->suggerer($autre, $duree, $preferences, 1);
            foreach ($resultat['creneaux'] as $creneau) {
                $autres[] = $this->creneaux->proposition($creneau, $autre, $service) + ['meme_atelier' => false];
            }
        }
        usort($autres, fn (array $a, array $b) => [$b['score'], $a['date']] <=> [$a['score'], $b['date']]);

        // 2 créneaux chez l'atelier d'origine + 1 alternative, ou ce qui existe.
        $nbMeme = $autres === [] ? $limite : min(count($memeAtelier), $limite - 1);
        $propositions = array_slice($memeAtelier, 0, $nbMeme);

        return array_slice(array_merge($propositions, $autres), 0, $limite);
    }

    /** Période de la journée du RDV annulé : on la garde, le citoyen l'avait choisie. */
    private function plage(RendezVous $rdv): ?string
    {
        if (! $rdv->date_rdv) {
            return null;
        }

        $heure = $rdv->date_rdv->format('H:i');
        foreach (SuggestionCreneauService::PLAGES as $plage => [$debut, $fin]) {
            if ($heure >= $debut && $heure < $fin) {
                return $plage;
            }
        }

        return null;
    }

    /** L'atelier vient d'annuler ce créneau : on ne le repropose pas. */
    private function chevaucheOriginal(array $creneau, RendezVous $rdv): bool
    {
        if (! $rdv->date_rdv) {
            return false;
        }

        $debut = strtotime($creneau['date']);
        $fin = $debut + (int) $creneau['duree'] * 60;
        $origine = $rdv->date_rdv->getTimestamp();

        return $debut < $origine + max(15, (int) $rdv->duree) * 60 && $fin > $origine;
    }
}
