<?php

namespace App\Mail;

use App\Modules\RendezVous\Models\RendezVous;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RendezVousReservationMail extends Mailable
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
            ->subject('Votre demande de rendez-vous — '.$rdv->atelierNom())
            ->view('emails.rendez-vous', [
                'titre' => 'Demande envoyée à '.$rdv->atelierNom(),
                'intro' => 'votre demande de rendez-vous est enregistrée. L\'atelier doit maintenant la confirmer.',
                'rdv' => $rdv,
                'nom' => $this->nom,
                'vetement' => $rdv->vetement?->displayName() ?? '—',
                'date' => $rdv->date_rdv?->locale('fr')->translatedFormat('l j F Y \à H\hi') ?? '—',
                'lien' => route('front.rdv.show', $rdv->getKey()),
            ]);
    }
}
