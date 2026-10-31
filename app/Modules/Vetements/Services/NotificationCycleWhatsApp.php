<?php

namespace App\Modules\Vetements\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Vetements\Models\Vetement;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationCycleWhatsApp
{
    public function apresCloture(Vetement $vetement, string $statut, string $titre, ?string $detail): void
    {
        $texte = $this->message($vetement, $statut, $titre, $detail);
        $telephone = $this->destinataire($this->proprietaire($vetement)?->phone);
        $token = (string) config('services.whatsapp.token');
        $phoneId = (string) config('services.whatsapp.phone_id');

        if ($texte === null || $telephone === null || $token === '' || $phoneId === '') {
            return;
        }

        $version = trim((string) config('services.whatsapp.graph_version', 'v22.0'), '/');

        try {
            $reponse = Http::withToken($token)
                ->acceptJson()
                ->timeout(15)
                ->post("https://graph.facebook.com/{$version}/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $telephone,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $texte,
                    ],
                ]);
        } catch (Throwable $exception) {
            Log::warning('WhatsApp cycle de vie injoignable.', [
                'erreur' => $exception->getMessage(),
            ]);

            return;
        }

        if ($reponse->failed()) {
            Log::warning('WhatsApp a refusé le message de cycle de vie.', [
                'statut' => $reponse->status(),
                'erreur' => mb_substr((string) ($reponse->json('error.message') ?? ''), 0, 180),
            ]);
        }
    }

    public function message(Vetement $vetement, string $statut, string $titre, ?string $detail): ?string
    {
        $prenom = $this->prenom($this->proprietaire($vetement));
        $salut = $prenom !== '' ? 'Bonjour '.$prenom.',' : 'Bonjour,';
        $piece = $vetement->displayName();

        if ($statut === Vetement::STATUS_DONNE) {
            return $this->joindre([
                $salut,
                '',
                'Merci pour votre don. '.$piece.' va pouvoir servir à quelqu\'un d\'autre.',
                $this->ligne('Suite', $detail),
                '',
                'Votre geste prolonge la vie de ce vêtement et aide une association. Continuez à donner les pièces que vous ne portez plus : chaque don compte.',
                '',
                'TexTileCycle',
            ]);
        }

        if ($statut === Vetement::STATUS_REPARE) {
            return $this->joindre([
                $salut,
                '',
                'Votre pièce est réparée : '.$piece.'.',
                $this->ligne('Matière', $vetement->material),
                $this->ligne('État', $vetement->condition_label),
                $this->ligne('Réparation', $detail ?: $titre),
                $this->ligne('Détail', $vetement->description),
                $this->ligne('Analyse', $vetement->analyseIa()['resume'] ?? null),
                $this->ligne('Atelier', $this->nomAtelier($vetement)),
                '',
                'Vous pouvez la récupérer. Merci de faire durer vos vêtements.',
                '',
                'TexTileCycle',
            ]);
        }

        return null;
    }

    private function proprietaire(Vetement $vetement): ?User
    {
        if (! $vetement->relationLoaded('owner')) {
            return null;
        }

        $owner = $vetement->owner;

        return $owner instanceof User ? $owner : null;
    }

    private function nomAtelier(Vetement $vetement): ?string
    {
        if (! $vetement->relationLoaded('atelier')) {
            return null;
        }

        $nom = trim((string) ($vetement->atelier?->nom ?? ''));

        return $nom !== '' ? $nom : null;
    }

    private function prenom(?User $user): string
    {
        $nom = trim((string) ($user?->name ?? ''));
        if ($nom === '') {
            return '';
        }

        $parties = preg_split('/\s+/u', $nom) ?: [];

        return (string) ($parties[0] ?? '');
    }

    public function destinataire(?string $telephone): ?string
    {
        $chiffres = preg_replace('/\D+/', '', (string) $telephone) ?? '';
        if (str_starts_with($chiffres, '00')) {
            $chiffres = substr($chiffres, 2);
        }

        if (strlen($chiffres) === 8) {
            $chiffres = '216'.$chiffres;
        }

        if (strlen($chiffres) < 8 || strlen($chiffres) > 15) {
            return null;
        }

        return $chiffres;
    }

    private function ligne(string $etiquette, mixed $valeur): ?string
    {
        $texte = trim((string) $valeur);
        if ($texte === '') {
            return null;
        }

        return $etiquette.' : '.$texte;
    }

    /**
     * @param  list<string|null>  $lignes
     */
    private function joindre(array $lignes): string
    {
        $texte = implode("\n", array_map(
            static fn (?string $ligne) => $ligne ?? '',
            $lignes
        ));

        return trim(preg_replace("/\n{3,}/", "\n\n", $texte) ?? $texte);
    }
}
