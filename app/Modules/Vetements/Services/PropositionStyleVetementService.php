<?php

namespace App\Modules\Vetements\Services;

use App\Modules\Vetements\Exceptions\StyleVetementException;
use App\Modules\Vetements\Models\Vetement;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PropositionStyleVetementService
{
    private const MODELE_TEXTE = 'gemini-3.5-flash-lite';

    private const MODELE_TEXTE_SECOURS = 'gemini-flash-lite-latest';

    /** @var list<string> */
    private const MODELES_IMAGE = [
        'gemini-3.1-flash-lite-image',
        'gemini-2.5-flash-image',
    ];

    private bool $imageSaturee = false;

    public function estConfigure(): bool
    {
        return trim((string) config('services.vision.gemini_key')) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function proposer(UploadedFile $photo): array
    {
        if (! $this->estConfigure()) {
            throw new StyleVetementException('Le nouveau style n\'est pas activé. Ajoutez GEMINI_API_KEY.');
        }

        $binaire = file_get_contents($photo->getRealPath() ?: '');
        if ($binaire === false || $binaire === '') {
            throw new StyleVetementException('La photo est vide ou illisible.');
        }

        $image = [
            'mime' => ($photo->getMimeType() ?: 'image/jpeg') === 'image/jpg' ? 'image/jpeg' : ($photo->getMimeType() ?: 'image/jpeg'),
            'data' => base64_encode($binaire),
        ];
        $cle = 'vetement-style:'.hash('sha256', $binaire);
        $cache = Cache::get($cle);

        if (is_array($cache) && ! empty($cache['vue'])) {
            return $cache;
        }

        $style = is_array($cache['style'] ?? null) ? $cache['style'] : $this->lireStyle($image);
        if (empty($style['est_vetement'])) {
            return [
                'est_vetement' => false,
                'message' => 'La photo ne montre pas un vêtement à restyler. Photographiez la pièce entière, à plat.',
            ];
        }

        $vue = $this->genererVue($image, $style);
        $resultat = $this->assembler($style, $vue);

        Cache::put($cle, $vue !== null ? $resultat : ['style' => $style], now()->addMinutes($vue !== null ? 20 : 10));

        return $resultat;
    }

    /**
     * Photo d'origine + instruction de réparation : une nouvelle image du même vêtement.
     *
     * @return array{image: string, instruction: string, jeton?: string, en_attente?: bool, relancer_dans?: int, message?: string}
     */
    public function restyler(UploadedFile $photo, string $instruction): array
    {
        if (! $this->estConfigure()) {
            throw new StyleVetementException('Le restyle n\'est pas activé. Ajoutez GEMINI_API_KEY.');
        }

        $instruction = trim($instruction);
        $image = $this->imageDepuisFichier($photo);
        $cle = 'vetement-restyle:'.hash('sha256', $image['data'].'|'.$instruction);
        $cache = Cache::get($cle);
        if (is_string($cache) && $cache !== '' && ! $this->estRenduCensure($cache)) {
            return ['image' => $cache, 'instruction' => $instruction, 'jeton' => $cle];
        }

        $consigne = <<<TXT
Tu reçois la photo d'origine d'un vêtement et une instruction de réparation.
Génère une nouvelle image de ce même vêtement après la réparation décrite.
Conserver la forme, la coupe, la couleur principale, les motifs, la texture et tous les détails qui ne sont pas visés par la réparation.
Même cadrage, même vêtement, sans personne, sans visage, sans mannequin et sans décor. Fond clair uni.
Réparation demandée : {$instruction}
TXT;

        $rendu = $this->genererImage($image, $consigne);
        if ($rendu === null) {
            $gratuit = $this->genererImageGratuite($image, $consigne);
            if (isset($gratuit['image'])) {
                $rendu = $gratuit['image'];
            } else {
                return [
                    'image' => '',
                    'instruction' => $instruction,
                    'en_attente' => true,
                    'relancer_dans' => $gratuit['relancer_dans'],
                    'message' => $gratuit['message'],
                ];
            }
        }

        Cache::put($cle, $rendu, now()->addMinutes(20));

        return ['image' => $rendu, 'instruction' => $instruction, 'jeton' => $cle];
    }

    /**
     * Guide écrit : description complète, état avant, état après.
     *
     * @param  array{mime: string, data: string}  $avant
     * @param  array{mime: string, data: string}  $apres
     * @return array{titre: string, description: string, etat_avant: string, etat_apres: string, instruction: string}
     */
    public function redigerGuide(array $avant, array $apres, string $instruction): array
    {
        if (! $this->estConfigure()) {
            throw new StyleVetementException('Le guide de réparation n\'est pas activé. Ajoutez GEMINI_API_KEY.');
        }

        $instruction = trim($instruction);
        $consigne = <<<TXT
Tu rédiges un guide de réparation textile. La première image est l'état avant. La seconde image est l'état après la réparation.
Instruction du citoyen : {$instruction}
Réponds uniquement avec un objet JSON :
{
  "titre": string,
  "description": string,
  "etat_avant": string,
  "etat_apres": string
}
Règles :
- description : 4 à 6 phrases en français. Décris la pièce, le défaut, le geste de réparation et le résultat visible.
- etat_avant : 2 à 4 phrases sur la photo d'origine (usure, couture, trou, couleur, forme).
- etat_apres : 2 à 4 phrases sur la pièce une fois la réparation appliquée, fidèles à la seconde image.
- Ne décris pas une personne. Pas de markdown.
TXT;

        $texte = $this->interrogerTexteImages($consigne, [$avant, $apres]);

        return $this->normaliserGuide($this->extraireJson($texte), $instruction);
    }

    /**
     * @return array{mime: string, data: string}
     */
    private function imageDepuisFichier(UploadedFile $photo): array
    {
        $binaire = file_get_contents($photo->getRealPath() ?: '');
        if ($binaire === false || $binaire === '') {
            throw new StyleVetementException('La photo est vide ou illisible.');
        }

        $mime = $photo->getMimeType() ?: 'image/jpeg';

        return [
            'mime' => $mime === 'image/jpg' ? 'image/jpeg' : $mime,
            'data' => base64_encode($binaire),
        ];
    }

    /**
     * @param  array{mime: string, data: string}  $image
     * @return array<string, mixed>
     */
    private function lireStyle(array $image): array
    {
        $types = implode(' | ', Vetement::TYPES);
        $consigne = <<<TXT
Tu es styliste de réparation textile. Observe la photo d'une pièce à réparer.
Réponds uniquement avec un objet JSON :
{
  "est_vetement": boolean,
  "type": "{$types}",
  "titre": string,
  "style": string,
  "description": string,
  "couleurs": ["#RRGGBB", "#RRGGBB", "#RRGGBB"],
  "gestes": [string, string, string]
}
Règles :
- est_vetement est false si la photo n'est pas un vêtement.
- Le nouveau style naît de la réparation : reprise visible, surpiqûre contrastée, pièce rapportée, teinture ou découpe.
- Garde le même type de vêtement. Ne décris pas une personne.
- couleurs : tissu, couture contrastée, pièce rapportée. Trois codes hexadécimaux.
- description : 2 phrases en français.
- gestes : 3 actions d'atelier courtes.
TXT;

        $texte = $this->interrogerTexte($consigne, $image);

        return $this->normaliserStyle($this->extraireJson($texte));
    }

    /**
     * @param  array{mime: string, data: string}  $image
     * @param  array<string, mixed>  $style
     */
    private function genererVue(array $image, array $style): ?string
    {
        $consigne = sprintf(
            'Dessine uniquement ce vêtement après réparation, sans personne ni visage, fond clair uni, vue de face. Type : %s. Style : %s. %s',
            $style['type'],
            $style['style'],
            $style['description']
        );

        return $this->genererImage($image, $consigne);
    }

    /**
     * @param  array{mime: string, data: string}  $image
     */
    private function genererImage(array $image, string $consigne): ?string
    {
        $this->imageSaturee = false;

        foreach (self::MODELES_IMAGE as $modele) {
            try {
                $reponse = Http::timeout(40)
                    ->acceptJson()
                    ->withHeaders(['x-goog-api-key' => $this->cle()])
                    ->post($this->url($modele), [
                        'contents' => [[
                            'parts' => [
                                ['text' => $consigne],
                                ['inlineData' => [
                                    'mimeType' => $image['mime'],
                                    'data' => $image['data'],
                                ]],
                            ],
                        ]],
                        'generationConfig' => [
                            'responseModalities' => ['TEXT', 'IMAGE'],
                        ],
                    ]);
            } catch (ConnectionException $exception) {
                Log::warning('Rendu de style injoignable', ['modele' => $modele]);

                continue;
            }

            if ($reponse->status() === 429) {
                $this->imageSaturee = true;
            }

            if ($reponse->successful()) {
                $vue = $this->imageDataUrl($reponse);
                if ($vue !== null) {
                    return $vue;
                }
            }

            Log::warning('Rendu de style indisponible', [
                'modele' => $modele,
                'statut' => $reponse->status(),
            ]);
        }

        return null;
    }

    /**
     * Générateur gratuit, sans clé : la photo part avec l'instruction.
     *
     * @param  array{mime: string, data: string}  $image
     * @return array{image: string}|array{relancer_dans: int, message: string}
     */
    private function genererImageGratuite(array $image, string $consigne): array
    {
        $binaire = base64_decode($image['data'], true);
        if ($binaire === false || $binaire === '') {
            throw new StyleVetementException('La photo est vide ou illisible.');
        }

        $source = $this->reduirePourGenerateurGratuit($binaire);
        $cleJob = 'vetement-restyle-job:'.hash('sha256', $image['data'].'|'.$consigne);
        $id = Cache::get($cleJob);
        $attente = 30;

        try {
            if (! is_string($id) || $id === '') {
                $negatif = '###nude, naked, nsfw, erotic, sexual, explicit, lingerie';
                $positif = 'Clothed garment product photo, family-safe, plain off-white background. Same garment as the reference, keep the shape, cut, main color and texture. Apply only this repair: '.$consigne;
                $demande = Http::timeout(25)
                    ->acceptJson()
                    ->withHeaders([
                        'apikey' => '0000000000',
                        'Client-Agent' => 'TexTileCycle:1.0:local',
                    ])
                    ->post('https://stablehorde.net/api/v2/generate/async', [
                        'prompt' => mb_substr($positif, 0, 900 - mb_strlen($negatif)).$negatif,
                        'params' => [
                            'width' => $source['width'],
                            'height' => $source['height'],
                            'steps' => 20,
                            'n' => 1,
                            'cfg_scale' => 6,
                            'sampler_name' => 'k_euler_a',
                            'denoising_strength' => 0.45,
                        ],
                        'nsfw' => false,
                        'censor_nsfw' => false,
                        'r2' => true,
                        'shared' => false,
                        'models' => ['stable_diffusion'],
                        'source_image' => $source['data'],
                        'source_processing' => 'img2img',
                    ]);

                $id = $demande->json('id');
                if (! $demande->successful() || ! is_string($id) || $id === '') {
                    Log::warning('Générateur gratuit indisponible', ['statut' => $demande->status()]);
                    throw new StyleVetementException('Le générateur gratuit ne répond pas. Réessayez dans une minute.');
                }

                Cache::put($cleJob, $id, now()->addMinutes(30));
            }

            $limite = app()->runningUnitTests() ? 1 : 12;
            for ($essai = 0; $essai < $limite; $essai++) {
                if ($essai > 0) {
                    sleep(3);
                }

                $suivi = Http::timeout(20)
                    ->acceptJson()
                    ->withHeaders(['Client-Agent' => 'TexTileCycle:1.0:local'])
                    ->get('https://stablehorde.net/api/v2/generate/status/'.$id);

                if ($suivi->json('faulted') === true) {
                    Cache::forget($cleJob);
                    throw new StyleVetementException('Le générateur gratuit n\'a pas pu modifier la photo. Réessayez.');
                }

                if ($suivi->json('done') === true) {
                    $url = (string) data_get($suivi->json(), 'generations.0.img', '');
                    $rendu = $this->imageGratuiteVersDataUrl($url);
                    Cache::forget($cleJob);
                    if ($rendu === null) {
                        throw new StyleVetementException('Le générateur gratuit n\'a pas renvoyé d\'image. Réessayez.');
                    }

                    if (data_get($suivi->json(), 'generations.0.censored') === true || $this->estRenduCensure($rendu)) {
                        throw new StyleVetementException('Le générateur a bloqué le rendu de cette photo. Recadrez le vêtement seul, sans visage ni mains, puis réessayez.');
                    }

                    return ['image' => $rendu];
                }

                $attente = max(12, (int) $suivi->json('wait_time'));
            }
        } catch (ConnectionException $exception) {
            Log::warning('Générateur gratuit injoignable');
            throw new StyleVetementException('Le générateur gratuit ne répond pas. Réessayez dans une minute.');
        }

        return [
            'relancer_dans' => 12,
            'message' => 'Le générateur gratuit traite la photo d\'origine. Encore environ '.$attente.' secondes. La page réessaie toute seule.',
        ];
    }

    /**
     * @return array{data: string, width: int, height: int}
     */
    private function reduirePourGenerateurGratuit(string $binaire): array
    {
        if (! function_exists('imagecreatefromstring')) {
            return ['data' => base64_encode($binaire), 'width' => 512, 'height' => 512];
        }

        $source = @imagecreatefromstring($binaire);
        if ($source === false) {
            return ['data' => base64_encode($binaire), 'width' => 512, 'height' => 512];
        }

        $largeur = imagesx($source);
        $hauteur = imagesy($source);
        $ratio = min(512 / max(1, $largeur), 512 / max(1, $hauteur), 1);
        $nouvelleLargeur = max(64, (int) (floor($largeur * $ratio / 64) * 64));
        $nouvelleHauteur = max(64, (int) (floor($hauteur * $ratio / 64) * 64));
        $destination = imagecreatetruecolor($nouvelleLargeur, $nouvelleHauteur);
        $blanc = imagecolorallocate($destination, 255, 255, 255);
        imagefill($destination, 0, 0, $blanc);
        imagecopyresampled($destination, $source, 0, 0, 0, 0, $nouvelleLargeur, $nouvelleHauteur, $largeur, $hauteur);
        ob_start();
        imagejpeg($destination, null, 82);
        $jpeg = ob_get_clean();
        imagedestroy($source);
        imagedestroy($destination);

        return [
            'data' => base64_encode(is_string($jpeg) && $jpeg !== '' ? $jpeg : $binaire),
            'width' => $nouvelleLargeur,
            'height' => $nouvelleHauteur,
        ];
    }

    private function imageGratuiteVersDataUrl(string $url): ?string
    {
        if (str_starts_with($url, 'data:image')) {
            return $url;
        }

        if (! str_starts_with($url, 'http')) {
            return null;
        }

        try {
            $fichier = Http::timeout(30)->get($url);
        } catch (ConnectionException $exception) {
            return null;
        }

        if (! $fichier->successful() || $fichier->body() === '') {
            return null;
        }

        $type = trim(explode(';', (string) $fichier->header('Content-Type'))[0]);
        if (! str_starts_with($type, 'image/')) {
            $type = 'image/webp';
        }

        return 'data:'.$type.';base64,'.base64_encode($fichier->body());
    }

    private function estRenduCensure(string $dataUrl): bool
    {
        if (! str_starts_with($dataUrl, 'data:image')) {
            return false;
        }

        $charge = preg_replace('#^data:image/[^;]+;base64,#', '', $dataUrl) ?? '';
        $binaire = base64_decode($charge, true);
        if ($binaire === false || $binaire === '') {
            return false;
        }

        return $this->estImageCensuree($binaire);
    }

    /**
     * Carte noire « CENSORED » renvoyée quand le filtre prend un vêtement porté pour un contenu bloqué.
     */
    private function estImageCensuree(string $binaire): bool
    {
        if (! function_exists('imagecreatefromstring')) {
            return false;
        }

        $image = @imagecreatefromstring($binaire);
        if ($image === false) {
            return false;
        }

        $largeur = imagesx($image);
        $hauteur = imagesy($image);
        $noirs = 0;
        $blancs = 0;
        $total = 0;
        $pasX = max(1, (int) ($largeur / 24));
        $pasY = max(1, (int) ($hauteur / 24));

        for ($y = 0; $y < $hauteur; $y += $pasY) {
            for ($x = 0; $x < $largeur; $x += $pasX) {
                $couleur = imagecolorat($image, min($x, $largeur - 1), min($y, $hauteur - 1));
                $rouge = ($couleur >> 16) & 0xFF;
                $vert = ($couleur >> 8) & 0xFF;
                $bleu = $couleur & 0xFF;
                $luminance = (0.299 * $rouge) + (0.587 * $vert) + (0.114 * $bleu);
                $total++;
                if ($luminance < 28) {
                    $noirs++;
                }
                if ($luminance > 210 && abs($rouge - $vert) < 18 && abs($vert - $bleu) < 18) {
                    $blancs++;
                }
            }
        }

        imagedestroy($image);

        if ($total === 0) {
            return false;
        }

        $partNoire = $noirs / $total;
        $partBlanche = $blancs / $total;

        return $partNoire > 0.82 && $partBlanche > 0.02 && $partBlanche < 0.16;
    }

    /**
     * @param  array{mime: string, data: string}  $image
     */
    private function interrogerTexte(string $consigne, array $image): string
    {
        return $this->interrogerTexteImages($consigne, [$image]);
    }

    /**
     * @param  list<array{mime: string, data: string}>  $images
     */
    private function interrogerTexteImages(string $consigne, array $images): string
    {
        $modeles = array_values(array_unique(array_filter([
            (string) config('services.vision.gemini_model', self::MODELE_TEXTE),
            self::MODELE_TEXTE,
            self::MODELE_TEXTE_SECOURS,
        ])));
        $dernier = null;

        foreach ($modeles as $indice => $modele) {
            try {
                $reponse = Http::timeout(20)
                    ->acceptJson()
                    ->withHeaders(['x-goog-api-key' => $this->cle()])
                    ->post($this->url($modele), [
                        'contents' => [[
                            'parts' => $this->parties($consigne, $images),
                        ]],
                        'generationConfig' => [
                            'temperature' => 0.4,
                            'responseMimeType' => 'application/json',
                        ],
                    ]);
            } catch (ConnectionException $exception) {
                Log::warning('Style textile injoignable', ['modele' => $modele]);
                if ($indice === count($modeles) - 1) {
                    throw new StyleVetementException('Le styliste ne répond pas. Réessayez dans un instant.');
                }

                continue;
            }

            if ($reponse->successful()) {
                $texte = $this->texte($reponse);
                if ($texte !== '') {
                    return $texte;
                }
            }

            $dernier = $reponse;
            if (! in_array($reponse->status(), [404, 429, 503], true) || $indice === count($modeles) - 1) {
                break;
            }
        }

        if ($dernier instanceof Response && $dernier->status() === 429) {
            throw new StyleVetementException('Le styliste est saturé. Réessayez dans un instant.');
        }

        throw new StyleVetementException('Le nouveau style n\'a pas pu être proposé. Essayez une autre photo.');
    }

    /**
     * @param  list<array{mime: string, data: string}>  $images
     * @return list<array<string, mixed>>
     */
    private function parties(string $consigne, array $images): array
    {
        $parties = [['text' => $consigne]];
        foreach ($images as $image) {
            $parties[] = ['inlineData' => [
                'mimeType' => $image['mime'],
                'data' => $image['data'],
            ]];
        }

        return $parties;
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array{titre: string, description: string, etat_avant: string, etat_apres: string, instruction: string}
     */
    private function normaliserGuide(array $donnees, string $instruction): array
    {
        $description = trim((string) ($donnees['description'] ?? ''));
        $etatAvant = trim((string) ($donnees['etat_avant'] ?? ''));
        $etatApres = trim((string) ($donnees['etat_apres'] ?? ''));
        if ($description === '' || $etatAvant === '' || $etatApres === '') {
            throw new StyleVetementException('Le guide de réparation est incomplet.');
        }

        $titre = trim((string) ($donnees['titre'] ?? ''));

        return [
            'titre' => mb_substr($titre !== '' ? $titre : 'Guide de réparation', 0, 120),
            'description' => mb_substr($description, 0, 1500),
            'etat_avant' => mb_substr($etatAvant, 0, 800),
            'etat_apres' => mb_substr($etatApres, 0, 800),
            'instruction' => mb_substr($instruction, 0, 400),
        ];
    }

    /**
     * @param  array<string, mixed>  $style
     * @return array<string, mixed>
     */
    private function assembler(array $style, ?string $vue): array
    {
        return [
            'est_vetement' => true,
            'type' => $style['type'],
            'titre' => $style['titre'],
            'style' => $style['style'],
            'description' => $style['description'],
            'couleurs' => $style['couleurs'],
            'gestes' => $style['gestes'],
            'vue' => $vue,
        ];
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    private function normaliserStyle(array $donnees): array
    {
        $estVetement = ($donnees['est_vetement'] ?? false) === true;
        $type = in_array($donnees['type'] ?? '', Vetement::TYPES, true) ? $donnees['type'] : 'Veste';
        $couleursBrutes = is_array($donnees['couleurs'] ?? null) ? $donnees['couleurs'] : [];
        $defauts = ['#243E73', '#E8B931', '#F3EDE2'];
        $couleurs = [];
        foreach ([0, 1, 2] as $index) {
            $couleurs[] = $this->couleur((string) ($couleursBrutes[$index] ?? ''), $defauts[$index]);
        }

        $gestes = [];
        foreach (is_array($donnees['gestes'] ?? null) ? $donnees['gestes'] : [] as $geste) {
            $geste = trim((string) $geste);
            if ($geste !== '') {
                $gestes[] = mb_substr($geste, 0, 140);
            }
            if (count($gestes) === 3) {
                break;
            }
        }
        if ($gestes === []) {
            $gestes = ['Reprendre les coutures en fil contrasté', 'Poser une pièce rapportée', 'Teindre le tissu pour unifier la réparation'];
        }

        return [
            'est_vetement' => $estVetement,
            'type' => $type,
            'titre' => mb_substr(trim((string) ($donnees['titre'] ?? 'Nouveau style')), 0, 80) ?: 'Nouveau style',
            'style' => mb_substr(trim((string) ($donnees['style'] ?? 'Reprise visible')), 0, 80) ?: 'Reprise visible',
            'description' => mb_substr(trim((string) ($donnees['description'] ?? '')), 0, 500),
            'couleurs' => $couleurs,
            'gestes' => $gestes,
        ];
    }

    private function couleur(string $valeur, string $defaut): string
    {
        $valeur = trim($valeur);
        if (preg_match('/^#?[0-9a-fA-F]{6}$/', $valeur)) {
            return str_starts_with($valeur, '#') ? strtoupper($valeur) : '#'.strtoupper($valeur);
        }

        $normalise = mb_strtolower($valeur);
        $noms = [
            'noir' => '#1C1C1C', 'blanc' => '#F4F1EA', 'bleu' => '#1D4E89', 'marine' => '#1B3A4B',
            'rouge' => '#9B2335', 'vert' => '#2E7D32', 'jaune' => '#E8B931', 'orange' => '#E88932',
            'rose' => '#C48B9F', 'beige' => '#E6D3B3', 'gris' => '#8D8F8E', 'marron' => '#6B4423',
            'camel' => '#C19A6B', 'indigo' => '#243E73', 'ecru' => '#F3EDE2',
        ];
        foreach ($noms as $nom => $hex) {
            if (str_contains($normalise, $nom)) {
                return $hex;
            }
        }

        return $defaut;
    }

    private function url(string $modele): string
    {
        return sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            rawurlencode($modele)
        );
    }

    private function texte(Response $reponse): string
    {
        $texte = '';
        foreach ($reponse->json('candidates.0.content.parts') ?? [] as $part) {
            if (is_array($part) && isset($part['text']) && empty($part['thought'])) {
                $texte .= $part['text'];
            }
        }

        return trim($texte);
    }

    private function imageDataUrl(Response $reponse): ?string
    {
        foreach ($reponse->json('candidates.0.content.parts') ?? [] as $part) {
            if (! is_array($part)) {
                continue;
            }
            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (! is_array($inline) || empty($inline['data'])) {
                continue;
            }
            $mime = (string) ($inline['mimeType'] ?? $inline['mime_type'] ?? 'image/png');
            if (! str_starts_with($mime, 'image/')) {
                continue;
            }

            return 'data:'.$mime.';base64,'.$inline['data'];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function extraireJson(string $texte): array
    {
        $texte = trim(preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($texte)) ?? $texte);
        $debut = strpos($texte, '{');
        $fin = strrpos($texte, '}');
        if ($debut === false || $fin === false || $fin <= $debut) {
            return [];
        }
        $donnees = json_decode(substr($texte, $debut, $fin - $debut + 1), true);

        return is_array($donnees) ? $donnees : [];
    }

    private function cle(): string
    {
        return trim((string) config('services.vision.gemini_key'));
    }
}
