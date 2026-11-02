<?php

namespace App\Modules\Dons\Services;

use App\Modules\Associations\Models\Association;
use App\Modules\Vetements\Models\Vetement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Matching Don ↔ Association.
 *
 * Couche 1 : score déterministe sur 100, explicable (type, taille, état, quantité).
 * Couche 2 (optionnelle) : Gemini rédige une phrase d'explication pour le Top 3 ;
 * sans clé ou en cas d'échec, la phrase est construite à partir des raisons du score.
 */
class MatchingDonService
{
    private const POINTS_TYPE = 40;

    private const POINTS_TAILLE = 25;

    private const POINTS_ETAT = 20;

    private const POINTS_QUANTITE = 15;

    /** Sans correspondance de type, le score est fortement réduit. */
    private const FACTEUR_SANS_TYPE = 0.4;

    private const DELAI_GEMINI = 6;

    /** Clé Gemini propre au module Associations & Dons (GEMINI_DONS_API_KEY) ; la clé partagée n'est jamais utilisée. */
    private function cleGemini(): string
    {
        return trim((string) config('services.dons.gemini_key'));
    }

    /** Catégorie de besoin d'association la plus proche pour chaque type de vêtement. */
    private const CATEGORIE_PAR_TYPE = [
        'veste' => 'Manteaux & vestes',
        'manteau' => 'Manteaux & vestes',
        'pantalon' => 'Pantalons & jeans',
        'jean' => 'Pantalons & jeans',
        'short' => 'Pantalons & jeans',
        'pull' => 'Pulls & sweats',
        'robe' => 'Robes & jupes',
        'jupe' => 'Robes & jupes',
        'chemise' => 'Hauts (t-shirts, chemises)',
        't-shirt' => 'Hauts (t-shirts, chemises)',
    ];

    private const POINTS_ETAT_PAR_LIBELLE = [
        'excellent' => 20,
        'bon état' => 18,
        'à réparer' => 8,
        'abîmé' => 2,
    ];

    /**
     * Score d'un vêtement pour une association.
     *
     * @return array{association_id: string, nom: string, score: int, raisons: list<string>, phrase: string}
     */
    public function evaluer(Vetement $vetement, Association $association): array
    {
        $raisons = [];
        $meilleur = ['points' => 0, 'type' => false, 'taille' => false, 'besoin' => null];

        foreach ((array) ($association->besoins ?? []) as $besoin) {
            $typeOk = $this->typeCorrespond($vetement, (string) ($besoin['type'] ?? ''));
            $tailleOk = $this->tailleCorrespond($vetement, (string) ($besoin['taille'] ?? ''));
            $quantite = (int) ($besoin['quantite'] ?? 0);

            $points = ($typeOk ? self::POINTS_TYPE : 0)
                + ($typeOk ? $this->pointsTaille($vetement, (string) ($besoin['taille'] ?? '')) : 0)
                + $this->pointsQuantite($quantite);

            if ($points > $meilleur['points'] || $meilleur['besoin'] === null) {
                $meilleur = ['points' => $points, 'type' => $typeOk, 'taille' => $tailleOk, 'besoin' => $besoin];
            }
        }

        $total = $meilleur['points'] + $this->pointsEtat($vetement);
        if (! $meilleur['type']) {
            $total = (int) round($total * self::FACTEUR_SANS_TYPE);
        }

        if ($meilleur['besoin'] === null) {
            $raisons[] = 'Aucun besoin précis publié par cette association';
        } else {
            $besoin = $meilleur['besoin'];
            $raisons[] = $meilleur['type']
                ? 'Recherche : '.mb_strtolower((string) $besoin['type'])
                : 'Ne recherche pas ce type de vêtement';
            if ($meilleur['type'] && $meilleur['taille'] && ! empty($besoin['taille'])) {
                $raisons[] = $besoin['taille'] === 'Toutes tailles'
                    ? 'Toutes tailles acceptées'
                    : 'Taille '.$besoin['taille'].' acceptée';
            } elseif ($meilleur['type'] && ! $meilleur['taille']) {
                $raisons[] = 'Taille différente de celle demandée ('.$besoin['taille'].')';
            }
            if ($meilleur['type'] && (int) ($besoin['quantite'] ?? 0) > 0) {
                $raisons[] = $besoin['quantite'].' pièce(s) encore recherchée(s)';
            }
        }

        $etat = (string) $vetement->condition_label;
        if ($etat !== '') {
            $raisons[] = 'Vêtement : '.mb_strtolower($etat);
        }

        $score = max(0, min(100, $total));

        return [
            'association_id' => (string) $association->_id,
            'nom' => (string) $association->nom,
            'score' => $score,
            'raisons' => $raisons,
            'phrase' => $this->phraseLocale($association, $score, $raisons),
        ];
    }

    /**
     * Associations classées par score décroissant.
     *
     * @param  iterable<Association>  $associations
     * @return list<array{association_id: string, nom: string, score: int, raisons: list<string>, phrase: string}>
     */
    public function classer(Vetement $vetement, iterable $associations): array
    {
        $resultats = [];
        foreach ($associations as $association) {
            $resultats[] = $this->evaluer($vetement, $association);
        }

        usort($resultats, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return $resultats;
    }

    /**
     * Top 3 avec phrases rédigées par Gemini quand c'est possible.
     *
     * @param  Collection<int, Association>  $associations
     * @return list<array{association_id: string, nom: string, score: int, raisons: list<string>, phrase: string}>
     */
    public function topTrois(Vetement $vetement, Collection $associations): array
    {
        $top = array_slice($this->classer($vetement, $associations), 0, 3);

        return $this->enrichirAvecGemini($vetement, $top);
    }

    /**
     * @param  list<array{association_id: string, nom: string, score: int, raisons: list<string>, phrase: string}>  $top
     * @return list<array{association_id: string, nom: string, score: int, raisons: list<string>, phrase: string}>
     */
    private function enrichirAvecGemini(Vetement $vetement, array $top): array
    {
        $cle = $this->cleGemini();
        if ($cle === '' || $top === []) {
            return $top;
        }

        $cache = 'matching_don_v2_'.md5(json_encode([
            (string) $vetement->_id,
            $vetement->type,
            $vetement->size,
            $vetement->condition_label,
            array_map(fn (array $t) => [$t['association_id'], $t['score']], $top),
        ]));

        $phrases = Cache::remember($cache, now()->addMinutes(30), fn () => $this->demanderPhrases($cle, $vetement, $top));

        foreach ($top as &$ligne) {
            if (! empty($phrases[$ligne['association_id']])) {
                $ligne['phrase'] = $phrases[$ligne['association_id']];
            }
        }
        unset($ligne);

        return $top;
    }

    /**
     * @param  list<array{association_id: string, nom: string, score: int, raisons: list<string>, phrase: string}>  $top
     * @return array<string, string>
     */
    private function demanderPhrases(string $cle, Vetement $vetement, array $top): array
    {
        $donnees = [
            'vetement' => [
                'type' => $vetement->type,
                'taille' => $vetement->size,
                'etat' => $vetement->condition_label,
                'matiere' => $vetement->material,
            ],
            'associations' => array_map(fn (array $t) => [
                'id' => $t['association_id'],
                'nom' => $t['nom'],
                'score' => $t['score'],
                'raisons' => $t['raisons'],
            ], $top),
        ];

        $consigne = "Tu aides un citoyen à choisir l'association qui recevra son vêtement. "
            ."Pour chaque association, écris UNE phrase en français (max 120 caractères) qui explique le score "
            ."en citant 1 ou 2 raisons CONCRÈTES prises dans sa liste « raisons » (type recherché, taille, état, quantité), "
            ."y compris un point faible s'il y en a un (ex : taille différente). "
            ."Interdit : phrases vagues comme « correspondance avec vos critères ». "
            .'Exemple : "Solidarité Mode — 98 % : recherche des pulls, taille L acceptée, 6 pièces attendues." '
            .'Réponds en JSON : {"<id>": "<phrase>", ...}. Données : '.json_encode($donnees, JSON_UNESCAPED_UNICODE);

        $decode = $this->interrogerGeminiJson($consigne, $cle);
        if ($decode === null) {
            return [];
        }

        $ids = array_column($top, 'association_id');

        return collect($decode)
            ->filter(fn ($phrase, $id) => in_array((string) $id, $ids, true) && is_string($phrase) && trim($phrase) !== '')
            ->map(fn (string $phrase) => mb_substr(trim($phrase), 0, 160))
            ->all();
    }

    /**
     * Interroge Gemini (texte seul) et renvoie le JSON décodé, ou null en cas d'échec.
     *
     * @return array<string, mixed>|null
     */
    public function interrogerGeminiJson(string $consigne, ?string $cle = null): ?array
    {
        $cle ??= $this->cleGemini();
        if ($cle === '') {
            return null;
        }

        $modele = (string) config('services.vision.gemini_model') ?: 'gemini-3.5-flash-lite';

        try {
            $reponse = Http::timeout(self::DELAI_GEMINI)
                ->acceptJson()
                ->withHeaders(['x-goog-api-key' => $cle])
                ->post(sprintf(
                    'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
                    rawurlencode($modele)
                ), [
                    'contents' => [['parts' => [['text' => $consigne]]]],
                    'generationConfig' => ['temperature' => 0.3, 'responseMimeType' => 'application/json'],
                ]);

            if (! $reponse->successful()) {
                Log::warning('Matching don : Gemini indisponible', ['statut' => $reponse->status()]);

                return null;
            }

            $texte = '';
            foreach ($reponse->json('candidates.0.content.parts') ?? [] as $part) {
                if (is_array($part) && isset($part['text']) && empty($part['thought'])) {
                    $texte .= $part['text'];
                }
            }

            $decode = json_decode(trim($texte), true);

            return is_array($decode) ? $decode : null;
        } catch (Throwable $e) {
            Log::warning('Matching don : échec de l\'appel IA', ['erreur' => $e->getMessage()]);

            return null;
        }
    }

    /** Catégorie de besoin d'association correspondant à un type de vêtement. */
    public function categorie(?string $type): ?string
    {
        return self::CATEGORIE_PAR_TYPE[mb_strtolower((string) $type)] ?? null;
    }

    private function typeCorrespond(Vetement $vetement, string $besoinType): bool
    {
        if ($besoinType === '') {
            return false;
        }

        $categorie = self::CATEGORIE_PAR_TYPE[mb_strtolower((string) $vetement->type)] ?? null;
        if ($categorie !== null && $categorie === $besoinType) {
            return true;
        }

        // Besoin libre non listé : correspondance par mot contenu.
        return $vetement->type !== null
            && $vetement->type !== ''
            && str_contains(mb_strtolower($besoinType), mb_strtolower((string) $vetement->type));
    }

    private function tailleCorrespond(Vetement $vetement, string $besoinTaille): bool
    {
        return $this->pointsTaille($vetement, $besoinTaille) > 0;
    }

    private function pointsTaille(Vetement $vetement, string $besoinTaille): int
    {
        if ($besoinTaille === '' || $besoinTaille === 'Toutes tailles') {
            return self::POINTS_TAILLE - 5;
        }

        return mb_strtolower(trim((string) $vetement->size)) === mb_strtolower($besoinTaille)
            ? self::POINTS_TAILLE
            : 0;
    }

    private function pointsEtat(Vetement $vetement): int
    {
        return self::POINTS_ETAT_PAR_LIBELLE[mb_strtolower((string) $vetement->condition_label)] ?? 10;
    }

    private function pointsQuantite(int $quantite): int
    {
        return match (true) {
            $quantite >= 5 => self::POINTS_QUANTITE,
            $quantite >= 2 => 11,
            default => 8,
        };
    }

    /**
     * @param  list<string>  $raisons
     */
    private function phraseLocale(Association $association, int $score, array $raisons): string
    {
        return sprintf('%s — %d %% — %s', $association->nom, $score, $raisons[0] ?? 'Compatibilité calculée');
    }
}
