<?php

namespace App\Modules\Dons\Services;

use App\Modules\Associations\Models\Association;
use App\Modules\Vetements\Models\Vetement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Pré-contrôle d'un don avant l'envoi : le don a-t-il des chances d'être accepté ?
 *
 * Le verdict est calculé (score du matching + cohérence avec l'analyse photo).
 * Gemini ne fait que reformuler l'avis ; l'avertissement ne bloque jamais l'envoi.
 */
class PreControleDonService
{
    public const NIVEAU_ADAPTE = 'adapte';

    public const NIVEAU_A_VERIFIER = 'a_verifier';

    public const NIVEAU_PEU_ADAPTE = 'peu_adapte';

    private const SEUIL_ADAPTE = 70;

    private const SEUIL_A_VERIFIER = 35;

    /** Une alternative n'est proposée que si elle est nettement meilleure. */
    private const ECART_ALTERNATIVE = 15;

    private const RANG_ETAT = [
        'excellent' => 0,
        'bon état' => 1,
        'à réparer' => 2,
        'abîmé' => 3,
    ];

    public function __construct(private MatchingDonService $matching)
    {
    }

    /**
     * @param  Collection<int, Association>  $associations  associations actives (pour l'alternative)
     * @return array{niveau: string, titre: string, score: int, messages: list<string>, photo_analysee: bool, alternative: array{association_id: string, nom: string, score: int}|null}
     */
    public function verifier(Vetement $vetement, Association $association, Collection $associations): array
    {
        $evaluation = $this->matching->evaluer($vetement, $association);
        $score = $evaluation['score'];
        $niveau = $this->niveauDepuisScore($score);
        $messages = $evaluation['raisons'];

        $analyse = $this->analysePhoto($vetement);
        $etatDeclare = $this->rangEtat((string) $vetement->condition_label);

        if ($analyse === null) {
            $messages[] = 'Aucune analyse de photo : contrôle basé sur l\'état que vous avez déclaré.';
        } else {
            [$niveau, $messages] = $this->croiserAvecPhoto($vetement, $analyse, $etatDeclare, $niveau, $messages);
        }

        $pireEtat = max($etatDeclare ?? 0, $this->rangEtat((string) ($analyse['etat'] ?? '')) ?? 0);
        if ($pireEtat >= 3 && $niveau === self::NIVEAU_ADAPTE) {
            $niveau = self::NIVEAU_A_VERIFIER;
            $messages[] = 'Un vêtement abîmé est rarement accepté : pensez à la réparation ou au recyclage.';
        }

        $alternative = $niveau === self::NIVEAU_ADAPTE
            ? null
            : $this->alternative($vetement, $association, $associations, $score);

        $resultat = [
            'niveau' => $niveau,
            'titre' => $this->titre($niveau),
            'score' => $score,
            'messages' => array_values(array_unique($messages)),
            'photo_analysee' => $analyse !== null,
            'alternative' => $alternative,
        ];

        return $this->reformuler($vetement, $association, $resultat);
    }

    /**
     * @param  array<string, mixed>  $analyse
     * @param  list<string>  $messages
     * @return array{0: string, 1: list<string>}
     */
    private function croiserAvecPhoto(Vetement $vetement, array $analyse, ?int $etatDeclare, string $niveau, array $messages): array
    {
        if (($analyse['est_vetement'] ?? true) === false) {
            $messages[] = 'La photo ne montre pas clairement un vêtement.';

            return [$this->degrader($niveau), $messages];
        }

        $etatPhoto = $this->rangEtat((string) ($analyse['etat'] ?? ''));
        if ($etatPhoto !== null && $etatDeclare !== null && $etatPhoto >= 2 && $etatPhoto > $etatDeclare) {
            $defauts = array_slice(array_filter((array) ($analyse['defauts'] ?? []), 'is_string'), 0, 2);
            $detail = $defauts === [] ? '' : ' ('.implode(', ', $defauts).')';
            $messages[] = 'La photo semble montrer un vêtement « '.mb_strtolower((string) $analyse['etat']).' »'.$detail
                .' alors que vous avez déclaré « '.mb_strtolower((string) $vetement->condition_label).' ».';
            $niveau = $this->degrader($niveau);
        }

        $categoriePhoto = $this->matching->categorie($analyse['type'] ?? null);
        $categorieDeclaree = $this->matching->categorie($vetement->type);
        if ($categoriePhoto !== null && $categorieDeclaree !== null && $categoriePhoto !== $categorieDeclaree) {
            $messages[] = 'La photo ressemble plutôt à : '.mb_strtolower((string) $analyse['type']).'. Vérifiez le type déclaré.';
        }

        return [$niveau, $messages];
    }

    private function niveauDepuisScore(int $score): string
    {
        return match (true) {
            $score >= self::SEUIL_ADAPTE => self::NIVEAU_ADAPTE,
            $score >= self::SEUIL_A_VERIFIER => self::NIVEAU_A_VERIFIER,
            default => self::NIVEAU_PEU_ADAPTE,
        };
    }

    private function degrader(string $niveau): string
    {
        return $niveau === self::NIVEAU_ADAPTE ? self::NIVEAU_A_VERIFIER : $niveau;
    }

    private function titre(string $niveau): string
    {
        return match ($niveau) {
            self::NIVEAU_ADAPTE => 'Don adapté',
            self::NIVEAU_A_VERIFIER => 'À vérifier avant d\'envoyer',
            default => 'Don peu adapté à cette association',
        };
    }

    private function rangEtat(string $etat): ?int
    {
        return self::RANG_ETAT[mb_strtolower(trim($etat))] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function analysePhoto(Vetement $vetement): ?array
    {
        $analyse = $vetement->analyseIa();

        return $analyse !== null && ! empty($analyse['disponible']) ? $analyse : null;
    }

    /**
     * @param  Collection<int, Association>  $associations
     * @return array{association_id: string, nom: string, score: int}|null
     */
    private function alternative(Vetement $vetement, Association $actuelle, Collection $associations, int $scoreActuel): ?array
    {
        $autres = $associations->reject(fn (Association $a) => (string) $a->_id === (string) $actuelle->_id);

        $meilleur = $this->matching->classer($vetement, $autres)[0] ?? null;

        if ($meilleur !== null
            && $meilleur['score'] >= self::SEUIL_ADAPTE
            && $meilleur['score'] >= $scoreActuel + self::ECART_ALTERNATIVE) {
            return ['association_id' => $meilleur['association_id'], 'nom' => $meilleur['nom'], 'score' => $meilleur['score']];
        }

        return null;
    }

    /**
     * Gemini reformule les messages en un avis court ; en cas d'échec, les messages calculés restent.
     *
     * @param  array<string, mixed>  $resultat
     * @return array<string, mixed>
     */
    private function reformuler(Vetement $vetement, Association $association, array $resultat): array
    {
        $cle = 'precontrole_don_v1_'.md5(json_encode([(string) $vetement->_id, (string) $association->_id, $resultat]));

        $avis = Cache::remember($cle, now()->addMinutes(30), function () use ($vetement, $association, $resultat) {
            $consigne = 'Tu es le conseiller d\'une plateforme de don de vêtements. '
                .'Rédige UN avis en français (max 200 caractères), bienveillant et factuel, pour un citoyen qui va proposer son vêtement à une association. '
                .'Appuie-toi uniquement sur les faits fournis, cite le plus important (taille, type, état, photo) et ne change pas le niveau. '
                .'Réponds en JSON : {"avis": "<texte>"}. Données : '
                .json_encode([
                    'vetement' => ['type' => $vetement->type, 'taille' => $vetement->size, 'etat_declare' => $vetement->condition_label],
                    'association' => $association->nom,
                    'niveau' => $resultat['titre'],
                    'score' => $resultat['score'],
                    'faits' => $resultat['messages'],
                    'alternative' => $resultat['alternative']['nom'] ?? null,
                ], JSON_UNESCAPED_UNICODE);

            $reponse = $this->matching->interrogerGeminiJson($consigne);
            $texte = is_array($reponse) ? ($reponse['avis'] ?? null) : null;

            return is_string($texte) && trim($texte) !== '' ? mb_substr(trim($texte), 0, 240) : '';
        });

        $resultat['avis'] = $avis !== '' ? $avis : null;

        return $resultat;
    }
}
