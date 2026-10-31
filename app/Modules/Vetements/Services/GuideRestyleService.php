<?php

namespace App\Modules\Vetements\Services;

use App\Mail\GuideRestyleMail;
use App\Modules\Auth\Models\User;
use App\Modules\Vetements\Exceptions\StyleVetementException;
use App\Modules\Vetements\Models\GuideRestyle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GuideRestyleService
{
    public function __construct(private PropositionStyleVetementService $styles)
    {
    }

    /**
     * @return array{enregistre: bool, envoye: bool, email: string, message: string, guide: array{titre: string, description: string, etat_avant: string, etat_apres: string, instruction: string}}
     */
    public function enregistrer(
        User $user,
        UploadedFile $photo,
        string $instruction,
        ?string $jeton,
        ?string $imageApres,
        ?string $vetementId
    ): array {
        $email = trim((string) $user->email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new StyleVetementException('Aucune adresse e-mail n\'est associée à ce compte.');
        }

        $avant = $this->imageDepuisFichier($photo);
        $apres = $this->imageApres($jeton, $imageApres);
        $instruction = trim($instruction);

        try {
            $texte = $this->styles->redigerGuide(
                ['mime' => $avant['mime'], 'data' => $avant['data']],
                ['mime' => $apres['mime'], 'data' => $apres['data']],
                $instruction
            );
        } catch (StyleVetementException $exception) {
            report($exception);
            Log::warning('Guide de restyle rédigé sans le modèle', ['message' => $exception->getMessage()]);
            $texte = $this->guideDeSecours($instruction);
        }

        $dossier = 'guides-restyle/'.Str::uuid();
        $cheminAvant = $dossier.'/avant.'.$this->extension($avant['mime']);
        $cheminApres = $dossier.'/apres.'.$this->extension($apres['mime']);
        Storage::disk('public')->put($cheminAvant, $avant['binaire']);
        Storage::disk('public')->put($cheminApres, $apres['binaire']);

        $guide = $this->persister([
            'user_id' => (string) $user->getKey(),
            'vetement_id' => $vetementId,
            'titre' => $texte['titre'],
            'description' => $texte['description'],
            'etat_avant' => $texte['etat_avant'],
            'etat_apres' => $texte['etat_apres'],
            'instruction' => $texte['instruction'],
            'image_avant' => $cheminAvant,
            'image_apres' => $cheminApres,
            'envoye_a' => $email,
            'envoye_le' => null,
        ]);

        $envoye = false;
        try {
            Mail::to($email)->send(new GuideRestyleMail($guide, trim((string) $user->name) ?: 'bonjour'));
            $envoye = true;
        } catch (\Throwable $exception) {
            report($exception);
        }

        if ($envoye) {
            try {
                $this->marquerEnvoi($guide);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return [
            'enregistre' => true,
            'envoye' => $envoye,
            'email' => $email,
            'message' => $envoye
                ? 'Guide enregistré et envoyé à '.$email.'.'
                : 'Le guide est enregistré, mais l\'e-mail n\'a pas pu être envoyé à '.$email.'.',
            'guide' => [
                'titre' => $texte['titre'],
                'description' => $texte['description'],
                'etat_avant' => $texte['etat_avant'],
                'etat_apres' => $texte['etat_apres'],
                'instruction' => $texte['instruction'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    protected function persister(array $attributs): GuideRestyle
    {
        return GuideRestyle::create($attributs);
    }

    protected function marquerEnvoi(GuideRestyle $guide): void
    {
        $guide->envoye_le = now();
        $guide->save();
    }

    /**
     * @return array{mime: string, data: string, binaire: string}
     */
    private function imageDepuisFichier(UploadedFile $photo): array
    {
        $binaire = file_get_contents($photo->getRealPath() ?: '');
        if ($binaire === false || $binaire === '') {
            throw new StyleVetementException('La photo d\'origine est vide ou illisible.');
        }

        $mime = $photo->getMimeType() ?: 'image/jpeg';

        return [
            'mime' => $mime === 'image/jpg' ? 'image/jpeg' : $mime,
            'data' => base64_encode($binaire),
            'binaire' => $binaire,
        ];
    }

    /**
     * @return array{mime: string, data: string, binaire: string}
     */
    private function imageApres(?string $jeton, ?string $imageApres): array
    {
        $jeton = trim((string) $jeton);
        if ($jeton !== '') {
            if (! str_starts_with($jeton, 'vetement-restyle:')) {
                throw new StyleVetementException('Ce rendu ne correspond pas à un restyle.');
            }

            $cache = Cache::get($jeton);
            if (! is_string($cache) || $cache === '') {
                throw new StyleVetementException('Le rendu a expiré. Relancez la génération, puis enregistrez le guide.');
            }

            return $this->decoderDataUrl($cache);
        }

        if (! is_string($imageApres) || trim($imageApres) === '') {
            throw new StyleVetementException('L\'image après réparation est manquante. Relancez la génération.');
        }

        return $this->decoderDataUrl($imageApres);
    }

    /**
     * @return array{mime: string, data: string, binaire: string}
     */
    private function decoderDataUrl(string $dataUrl): array
    {
        if (! preg_match('#^data:(image/(?:png|jpeg|jpg|webp));base64,(.+)$#s', $dataUrl, $correspondance)) {
            throw new StyleVetementException('L\'image après réparation est illisible. Relancez la génération.');
        }

        $binaire = base64_decode(preg_replace('/\s+/', '', $correspondance[2]) ?? '', true);
        if ($binaire === false || $binaire === '') {
            throw new StyleVetementException('L\'image après réparation est vide.');
        }

        if (strlen($binaire) > 8 * 1024 * 1024) {
            throw new StyleVetementException('L\'image après réparation est trop lourde.');
        }

        $mime = $correspondance[1] === 'image/jpg' ? 'image/jpeg' : $correspondance[1];

        return [
            'mime' => $mime,
            'data' => base64_encode($binaire),
            'binaire' => $binaire,
        ];
    }

    private function extension(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    /**
     * @return array{titre: string, description: string, etat_avant: string, etat_apres: string, instruction: string}
     */
    private function guideDeSecours(string $instruction): array
    {
        return [
            'titre' => 'Guide de réparation',
            'description' => 'Ce guide décrit la pièce avant et après la réparation demandée : '.$instruction.'. La forme, la coupe et la couleur principale restent celles de la photo d\'origine. Seul le défaut visé par la réparation change.',
            'etat_avant' => 'Sur la photo d\'origine, la pièce porte encore le défaut à reprendre : '.$instruction.'.',
            'etat_apres' => 'Après la réparation, le défaut est repris. Le vêtement conserve sa forme, sa coupe et sa couleur principale.',
            'instruction' => $instruction,
        ];
    }
}
