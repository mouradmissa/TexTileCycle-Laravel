<?php

namespace App\Modules\RendezVous\Services;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\RendezVous\Models\RendezVous;
use App\Modules\Vetements\Models\Vetement;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * IA du module Rendez-vous : suggestion du créneau optimal.
 *
 * Chaque créneau libre des 14 prochains jours reçoit un score sur 100 :
 *  - 40 pts : charge du jour (peu de RDV déjà prévus dans l'atelier) ;
 *  - 20 pts : affluence habituelle de l'heure (historique de l'atelier) ;
 *  - 25 pts : proximité (plus c'est tôt, mieux c'est) ;
 *  - 15 pts : marge libre avant et après le créneau.
 *
 * Les dates sont manipulées « à l'heure murale » : date_rdv est saisi en heure locale
 * et stocké tel quel dans le fuseau de l'application, comme le fait le formulaire.
 */
class SuggestionCreneauService
{
    /** Durées proposées par le formulaire, en minutes. */
    public const DUREES = [15, 30, 45, 60, 90, 120, 180, 240, 360, 480];

    /** Bornes horaires des préférences exprimées par le citoyen. */
    public const PLAGES = [
        'matin' => ['00:00', '12:00'],
        'apres-midi' => ['12:00', '17:00'],
        'soir' => ['17:00', '23:59'],
    ];

    private const HORIZON_JOURS = 14;

    private const PAS_MINUTES = 30;

    private const DELAI_MINIMUM_MINUTES = 120;

    private const STATUTS_OCCUPANTS = [RendezVous::STATUT_EN_ATTENTE, RendezVous::STATUT_CONFIRME];

    /** Horaires supposés quand l'atelier n'a rien renseigné. */
    private const HORAIRES_PAR_DEFAUT = [['09:00', '12:00'], ['14:00', '18:00']];

    /**
     * Durée ajustée selon le service, l'état et la matière du vêtement.
     *
     * @return array{minutes: int, raisons: list<string>}
     */
    public function estimerDuree(?Service $service, ?Vetement $vetement, ?string $commentaire = null): array
    {
        $minutes = (int) ($service?->duree_estimee ?? 0);
        $raisons = [];

        if ($minutes > 0) {
            $raisons[] = 'Durée habituelle du service : '.$minutes.' min';
        } else {
            $minutes = 30;
            $raisons[] = 'Durée moyenne d\'une retouche : 30 min';
        }

        $etat = (string) $vetement?->condition_label;
        if ($etat === 'Abîmé') {
            $minutes = (int) ceil($minutes * 1.5);
            $raisons[] = 'Pièce abîmée : +50 %';
        } elseif ($etat === 'À réparer') {
            $minutes = (int) ceil($minutes * 1.25);
            $raisons[] = 'Pièce à réparer : +25 %';
        }

        $matiere = (string) $vetement?->material;
        if (in_array($matiere, ['Cuir', 'Denim', 'Laine'], true)) {
            $minutes += 15;
            $raisons[] = 'Matière épaisse ('.mb_strtolower($matiere).') : +15 min';
        } elseif (in_array($matiere, ['Soie', 'Cachemire'], true)) {
            $minutes += 15;
            $raisons[] = 'Matière délicate ('.mb_strtolower($matiere).') : +15 min';
        }

        if ($commentaire && preg_match('/plusieurs|trous|d[ée]chirures|beaucoup/iu', $commentaire)) {
            $minutes += 15;
            $raisons[] = 'Plusieurs réparations décrites : +15 min';
        }

        return ['minutes' => $this->arrondirDuree($minutes), 'raisons' => $raisons];
    }

    /**
     * Meilleurs créneaux libres d'un atelier. Si les préférences ne laissent aucun créneau,
     * elles sont assouplies (plage horaire, puis jour) et preferences_respectees passe à false.
     *
     * @param  array{date?: ?string, jour?: ?string, plage?: ?string}  $preferences
     * @return array{creneaux: list<array<string, mixed>>, preferences_respectees: bool}
     */
    public function suggerer(Atelier $atelier, int $duree, array $preferences = [], int $limite = 3, ?string $ignorerRdvId = null): array
    {
        $duree = $this->arrondirDuree($duree);
        $tentatives = [
            $preferences,
            array_merge($preferences, ['plage' => null]),
            ['date' => null, 'jour' => null, 'plage' => $preferences['plage'] ?? null],
            [],
        ];

        $contexte = $this->contexteAtelier($atelier, $ignorerRdvId);

        foreach ($tentatives as $indice => $tentative) {
            $creneaux = $this->calculer($atelier, $duree, $tentative, $contexte);
            if ($creneaux !== []) {
                return [
                    'creneaux' => $this->diversifier($creneaux, $limite),
                    'preferences_respectees' => $indice === 0,
                ];
            }
        }

        return ['creneaux' => [], 'preferences_respectees' => false];
    }

    /** Vrai si aucun RDV en attente ou confirmé de l'atelier ne chevauche ce créneau. */
    public function estLibre(string $atelierId, CarbonInterface $debut, int $duree, ?string $ignorerRdvId = null): bool
    {
        $debut = CarbonImmutable::instance($debut);
        $fin = $debut->addMinutes($duree);

        return $this->occupations($atelierId, $debut->subDay(), $fin, $ignorerRdvId)
            ->every(fn (array $o) => $o['fin'] <= $debut->getTimestamp() || $o['debut'] >= $fin->getTimestamp());
    }

    /**
     * Créneau enrichi des infos de l'atelier et du service : format commun à l'assistant,
     * à la replanification et au mail d'annulation.
     *
     * @param  array<string, mixed>  $creneau
     * @return array<string, mixed>
     */
    public function proposition(array $creneau, Atelier $atelier, ?Service $service): array
    {
        return array_merge($creneau, [
            'atelier_id' => (string) $atelier->getKey(),
            'atelier' => $atelier->nom,
            'adresse' => trim(($atelier->adresse ?? '').($atelier->ville ? ', '.$atelier->ville : ''), ', '),
            'note' => $atelier->note_moyenne ? $atelier->noteFormatee() : null,
            'service_id' => $service ? (string) $service->getKey() : null,
            'service' => $service?->nom,
            'prix' => $service?->prixFormate(),
        ]);
    }

    public function arrondirDuree(int $minutes): int
    {
        foreach (self::DUREES as $duree) {
            if ($duree >= $minutes) {
                return $duree;
            }
        }

        return self::DUREES[count(self::DUREES) - 1];
    }

    public static function maintenant(): CarbonImmutable
    {
        // Heure murale tunisienne exprimée dans le fuseau de l'application, comme date_rdv.
        return CarbonImmutable::parse(Atelier::maintenant()->format('Y-m-d H:i:s'));
    }

    public static function libelleDuree(int $minutes): string
    {
        $heures = intdiv($minutes, 60);
        $reste = $minutes % 60;

        return match (true) {
            $heures === 0 => $reste.' min',
            $reste === 0 => $heures.'h',
            default => $heures.'h'.sprintf('%02d', $reste),
        };
    }

    /**
     * @return array{occupations: Collection<int, array{debut: int, fin: int}>, histogramme: array<int, int>, max_histo: int}
     */
    private function contexteAtelier(Atelier $atelier, ?string $ignorerRdvId): array
    {
        $debut = self::maintenant()->startOfDay();
        $atelierId = (string) $atelier->getKey();

        $histogramme = [];
        RendezVous::query()
            ->where('atelier_id', $atelierId)
            ->where('statut', '!=', RendezVous::STATUT_ANNULE)
            ->orderByDesc('date_rdv')
            ->limit(500)
            ->get(['date_rdv'])
            ->each(function (RendezVous $rdv) use (&$histogramme) {
                if ($rdv->date_rdv) {
                    $heure = (int) $rdv->date_rdv->format('G');
                    $histogramme[$heure] = ($histogramme[$heure] ?? 0) + 1;
                }
            });

        return [
            'occupations' => $this->occupations($atelierId, $debut, $debut->addDays(self::HORIZON_JOURS + 1), $ignorerRdvId),
            'histogramme' => $histogramme,
            'max_histo' => $histogramme === [] ? 0 : max($histogramme),
        ];
    }

    /**
     * @return Collection<int, array{debut: int, fin: int}>
     */
    private function occupations(string $atelierId, CarbonInterface $debut, CarbonInterface $fin, ?string $ignorerRdvId): Collection
    {
        return RendezVous::query()
            ->where('atelier_id', $atelierId)
            ->whereIn('statut', self::STATUTS_OCCUPANTS)
            ->where('date_rdv', '>=', $debut)
            ->where('date_rdv', '<', $fin)
            ->get(['_id', 'date_rdv', 'duree'])
            ->reject(fn (RendezVous $rdv) => ! $rdv->date_rdv || ($ignorerRdvId && (string) $rdv->getKey() === $ignorerRdvId))
            ->map(fn (RendezVous $rdv) => [
                'debut' => $rdv->date_rdv->getTimestamp(),
                'fin' => $rdv->date_rdv->getTimestamp() + max(15, (int) $rdv->duree) * 60,
            ])
            ->values();
    }

    /**
     * @param  array{date?: ?string, jour?: ?string, plage?: ?string}  $preferences
     * @param  array{occupations: Collection<int, array{debut: int, fin: int}>, histogramme: array<int, int>, max_histo: int}  $contexte
     * @return list<array<string, mixed>>
     */
    private function calculer(Atelier $atelier, int $duree, array $preferences, array $contexte): array
    {
        $maintenant = self::maintenant();
        $auPlusTot = $maintenant->addMinutes(self::DELAI_MINIMUM_MINUTES);
        $semaine = $atelier->horairesSemaine();
        $sansHoraires = collect($semaine)->flatten()->isEmpty();
        $plage = self::PLAGES[$preferences['plage'] ?? ''] ?? null;
        $creneaux = [];

        for ($decalage = 0; $decalage <= self::HORIZON_JOURS; $decalage++) {
            $jourDate = $maintenant->startOfDay()->addDays($decalage);
            $nomJour = Atelier::JOURS[$jourDate->dayOfWeekIso - 1];

            if (! empty($preferences['date']) && $jourDate->format('Y-m-d') !== $preferences['date']) {
                continue;
            }
            if (! empty($preferences['jour']) && $nomJour !== $preferences['jour']) {
                continue;
            }

            $plagesJour = $sansHoraires
                ? ($nomJour === 'dimanche' ? [] : self::HORAIRES_PAR_DEFAUT)
                : $semaine[$nomJour];

            $occupationsJour = $contexte['occupations']->filter(
                fn (array $o) => $o['debut'] >= $jourDate->getTimestamp() && $o['debut'] < $jourDate->addDay()->getTimestamp()
            );

            foreach ($plagesJour as [$ouverture, $fermeture]) {
                $debut = $jourDate->setTimeFromTimeString($ouverture);
                $limite = $jourDate->setTimeFromTimeString($fermeture);

                for (; $debut->addMinutes($duree) <= $limite; $debut = $debut->addMinutes(self::PAS_MINUTES)) {
                    if ($debut < $auPlusTot) {
                        continue;
                    }
                    $heure = $debut->format('H:i');
                    if ($plage && ($heure < $plage[0] || $heure >= $plage[1])) {
                        continue;
                    }

                    $creneau = $this->evaluer($debut, $duree, $decalage, $occupationsJour, $contexte, $sansHoraires);
                    if ($creneau !== null) {
                        $creneaux[] = $creneau;
                    }
                }
            }
        }

        usort($creneaux, fn (array $a, array $b) => [$b['score'], $a['date']] <=> [$a['score'], $b['date']]);

        return $creneaux;
    }

    /**
     * @param  Collection<int, array{debut: int, fin: int}>  $occupationsJour
     * @param  array{histogramme: array<int, int>, max_histo: int}  $contexte
     * @return array<string, mixed>|null
     */
    private function evaluer(CarbonImmutable $debut, int $duree, int $decalage, Collection $occupationsJour, array $contexte, bool $sansHoraires): ?array
    {
        $t0 = $debut->getTimestamp();
        $t1 = $t0 + $duree * 60;

        $marge = 60;
        foreach ($occupationsJour as $o) {
            if ($o['debut'] < $t1 && $o['fin'] > $t0) {
                return null;
            }
            $ecart = $o['fin'] <= $t0 ? ($t0 - $o['fin']) / 60 : ($o['debut'] - $t1) / 60;
            $marge = min($marge, $ecart);
        }

        $charge = $occupationsJour->count();
        $ratioHeure = $contexte['max_histo'] > 0
            ? ($contexte['histogramme'][(int) $debut->format('G')] ?? 0) / $contexte['max_histo']
            : 0.0;

        $score = (int) round(
            max(0, 1 - $charge / 4) * 40
            + (1 - $ratioHeure) * 20
            + (1 - $decalage / self::HORIZON_JOURS) * 25
            + ($marge / 60) * 15
        );

        $affluence = match (true) {
            $charge === 0 && $ratioHeure < 0.34 => 'faible',
            $charge <= 2 => 'moyenne',
            default => 'forte',
        };

        $raisons = [];
        $raisons[] = $charge === 0
            ? 'Aucun autre rendez-vous ce jour-là'
            : $charge.' rendez-vous déjà prévu'.($charge > 1 ? 's' : '').' ce jour-là';
        if ($contexte['max_histo'] > 0) {
            $raisons[] = $ratioHeure < 0.34 ? 'Heure habituellement calme' : 'Heure souvent demandée';
        }
        if ($charge > 0 && $marge >= 60) {
            $raisons[] = 'Marge d\'au moins 1h avant et après';
        }
        if ($sansHoraires) {
            $raisons[] = 'Horaires de l\'atelier non renseignés : créneau à confirmer';
        }

        $jour = ucfirst($debut->locale('fr')->translatedFormat('l j F'));
        $libelleAffluence = ['faible' => 'Période de faible affluence', 'moyenne' => 'Affluence moyenne', 'forte' => 'Journée chargée'][$affluence];

        return [
            'date' => $debut->format('Y-m-d\TH:i'),
            'libelle' => $jour.' à '.$debut->format('H\hi'),
            'duree' => $duree,
            'duree_libelle' => self::libelleDuree($duree),
            'score' => $score,
            'affluence' => $affluence,
            'resume' => $jour.' '.$debut->format('H\hi').' — '.self::libelleDuree($duree).' estimées — '.$libelleAffluence,
            'raisons' => $raisons,
        ];
    }

    /**
     * Un créneau par jour d'abord, pour proposer de vrais choix ; on complète ensuite.
     *
     * @param  list<array<string, mixed>>  $creneaux
     * @return list<array<string, mixed>>
     */
    private function diversifier(array $creneaux, int $limite): array
    {
        $choisis = [];
        $jours = [];

        foreach ($creneaux as $creneau) {
            $jour = substr($creneau['date'], 0, 10);
            if (! isset($jours[$jour])) {
                $jours[$jour] = true;
                $choisis[] = $creneau;
            }
            if (count($choisis) === $limite) {
                return $choisis;
            }
        }

        foreach ($creneaux as $creneau) {
            if (! in_array($creneau, $choisis, true)) {
                $choisis[] = $creneau;
            }
            if (count($choisis) === $limite) {
                break;
            }
        }

        return $choisis;
    }
}
