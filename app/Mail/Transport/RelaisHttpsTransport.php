<?php

namespace App\Mail\Transport;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Envoie le message en HTTPS.
 * L'offre gratuite Render bloque les ports SMTP 25, 465 et 587.
 */
class RelaisHttpsTransport extends AbstractTransport
{
    public function __construct(
        private string $url,
        private string $secret
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'relais://https';
    }

    /**
     * @return array<string, mixed>
     */
    public function charge(SentMessage $message): array
    {
        $original = $message->getOriginalMessage();
        if (! $original instanceof Email) {
            throw new TransportException('Le relais mail ne sait envoyer qu\'un message MIME.');
        }

        $destinataires = array_map(
            fn (Address $adresse) => $adresse->getAddress(),
            $message->getEnvelope()->getRecipients()
        );
        if ($destinataires === []) {
            throw new TransportException('Le message n\'a aucun destinataire.');
        }

        $expediteur = $original->getFrom()[0] ?? null;
        $inline = [];
        $pieces = [];
        foreach ($original->getAttachments() as $part) {
            $entree = [
                'cid' => $part->hasContentId() ? $part->getContentId() : '',
                'name' => $this->nomFichier($part),
                'mime' => $part->getMediaType().'/'.$part->getMediaSubtype(),
                'data' => base64_encode($part->getBody()),
            ];
            if ($this->estInline($part)) {
                if ($entree['cid'] === '') {
                    $entree['cid'] = $part->getContentId();
                }
                $inline[] = $entree;
            } else {
                $pieces[] = $entree;
            }
        }

        return [
            'secret' => $this->secret,
            'to' => implode(',', $destinataires),
            'subject' => (string) $original->getSubject(),
            'fromName' => $expediteur?->getName() ?: 'TexTileCycle',
            'replyTo' => $expediteur?->getAddress() ?: '',
            'text' => $this->contenu($original->getTextBody()),
            'html' => $this->contenu($original->getHtmlBody()),
            'inline' => $inline,
            'attachments' => $pieces,
        ];
    }

    protected function doSend(SentMessage $message): void
    {
        $url = trim($this->url);
        if ($url === '' || $this->secret === '') {
            throw new TransportException('Le relais mail n\'est pas configuré.');
        }

        $charge = $this->charge($message);
        $corps = json_encode($charge, JSON_UNESCAPED_UNICODE);
        if ($corps === false) {
            throw new TransportException('Le message n\'a pas pu être préparé pour le relais.');
        }

        $cookies = tempnam(sys_get_temp_dir(), 'relais');
        $canal = curl_init($url);
        if ($canal === false || $cookies === false) {
            throw new TransportException('Le relais mail est injoignable.');
        }

        curl_setopt_array($canal, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $corps,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $cookies,
            CURLOPT_COOKIEFILE => $cookies,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $brut = curl_exec($canal);
        $erreur = curl_error($canal);
        $statut = (int) curl_getinfo($canal, CURLINFO_HTTP_CODE);
        curl_close($canal);

        if ($brut === false) {
            @unlink($cookies);
            throw new TransportException('Le relais mail est injoignable. '.$erreur);
        }

        $reponse = $this->corpsHttp((string) $brut);
        if (in_array($statut, [301, 302, 303], true) && preg_match('/^Location:\s*(.+)$/mi', (string) $brut, $correspondance)) {
            $reponse = $this->lireRedirection(trim($correspondance[1]), $cookies);
        }
        @unlink($cookies);

        $decode = json_decode($reponse, true);
        if (! is_array($decode) || empty($decode['ok'])) {
            $detail = is_array($decode) ? trim((string) ($decode['error'] ?? '')) : '';
            throw new TransportException(
                'Le relais mail a refusé l\'envoi.'.($detail !== '' ? ' '.$detail : '')
            );
        }
    }

    private function lireRedirection(string $url, string $cookies): string
    {
        $canal = curl_init($url);
        if ($canal === false) {
            throw new TransportException('Le relais mail est injoignable.');
        }

        curl_setopt_array($canal, [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEJAR => $cookies,
            CURLOPT_COOKIEFILE => $cookies,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $reponse = curl_exec($canal);
        $erreur = curl_error($canal);
        curl_close($canal);

        if ($reponse === false) {
            throw new TransportException('Le relais mail est injoignable. '.$erreur);
        }

        return (string) $reponse;
    }

    private function corpsHttp(string $brut): string
    {
        $separateur = strpos($brut, "\r\n\r\n");

        return $separateur === false ? $brut : substr($brut, $separateur + 4);
    }

    private function estInline(DataPart $part): bool
    {
        $entete = $part->getPreparedHeaders()->get('Content-Disposition');

        return $entete !== null && strtolower((string) $entete->getBody()) === 'inline';
    }

    private function nomFichier(DataPart $part): string
    {
        $entete = $part->getPreparedHeaders()->get('Content-Disposition');
        $nom = $entete?->getParameter('filename');

        return is_string($nom) && $nom !== '' ? $nom : 'fichier';
    }

    /**
     * @param  resource|string|null  $contenu
     */
    private function contenu($contenu): string
    {
        if (is_resource($contenu)) {
            $contenu = stream_get_contents($contenu) ?: '';
        }

        return is_string($contenu) ? $contenu : '';
    }
}
