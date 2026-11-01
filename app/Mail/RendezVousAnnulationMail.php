<?php

namespace App\Mail;

use App\Modules\RendezVous\Models\RendezVous;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RendezVousAnnulationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array<string, mixed>>  $propositions  créneaux de remplacement calculés par l'IA
     */
    public function __construct(
        public RendezVous $rendezVous,
        public string $nom,
        public array $propositions = []
    ) {
    }

    public function build(): self
    {
        $adresse = trim((string) config('mail.from.address'));
        if ($adresse === '' || $adresse === 'null') {
            $adresse = 'noreply@textilecycle.tn';
        }

        $rdv = $this->rendezVous;
        $intro = $this->propositions === []
            ? 'votre rendez-vous a dû être annulé. Vous pouvez en reprendre un depuis votre espace.'
            : 'votre rendez-vous a dû être annulé. Notre IA a déjà trouvé des créneaux de remplacement : reprogrammez en un clic depuis votre espace.';

        return $this
            ->from($adresse, (string) config('mail.from.name', 'TexTileCycle'))
            ->subject('Rendez-vous annulé — '.$rdv->atelierNom().($this->propositions === [] ? '' : ' : nouveaux créneaux proposés'))
            ->view('emails.rendez-vous', [
                'titre' => 'Rendez-vous annulé chez '.$rdv->atelierNom(),
                'intro' => $intro,
                'rdv' => $rdv,
                'nom' => $this->nom,
                'vetement' => $rdv->vetement?->displayName() ?? '—',
                'date' => $rdv->date_rdv?->locale('fr')->translatedFormat('l j F Y \à H\hi') ?? '—',
                'propositions' => $this->propositions,
                'lien' => route('front.rdv', ['replanifier' => $rdv->getKey()]).'#replanifier-'.$rdv->getKey(),
                'bouton' => $this->propositions === [] ? 'Prendre un nouveau rendez-vous' : 'Reprogrammer mon rendez-vous',
            ]);
    }
}
