<?php

namespace App\Mail;

use App\Modules\RendezVous\Models\RendezVous;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RendezVousConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RendezVous $rendezVous,
        public string $nom
    ) {
    }

    public function build(): self
    {
        $adresse = trim((string) config('mail.from.address'));
        if ($adresse === '' || $adresse === 'null') {
            $adresse = 'noreply@textilecycle.tn';
        }

        $rdv = $this->rendezVous;

        return $this
            ->from($adresse, (string) config('mail.from.name', 'TexTileCycle'))
            ->subject('Rendez-vous confirmé — '.$rdv->atelierNom())
            ->view('emails.rendez-vous', [
                'titre' => 'Rendez-vous confirmé chez '.$rdv->atelierNom(),
                'intro' => 'bonne nouvelle : l\'atelier a confirmé votre rendez-vous. Pensez à apporter votre vêtement à l\'heure prévue.',
                'rdv' => $rdv,
                'nom' => $this->nom,
                'vetement' => $rdv->vetement?->displayName() ?? '—',
                'date' => $rdv->date_rdv?->locale('fr')->translatedFormat('l j F Y \à H\hi') ?? '—',
                'lien' => route('front.rdv.show', $rdv->getKey()),
            ]);
    }
}
