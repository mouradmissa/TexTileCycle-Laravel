<?php

namespace App\Mail;

use App\Modules\Vetements\Models\GuideRestyle;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GuideRestyleMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public GuideRestyle $guide,
        public string $nom
    ) {
    }

    public function build(): self
    {
        $adresse = trim((string) config('mail.from.address'));
        if ($adresse === '' || $adresse === 'null') {
            $adresse = 'noreply@textilecycle.tn';
        }

        return $this
            ->from($adresse, (string) config('mail.from.name', 'TexTileCycle'))
            ->subject('Votre guide de réparation — '.$this->guide->titre)
            ->view('emails.guide-restyle', [
                'guide' => $this->guide,
                'nom' => $this->nom,
                'cheminAvant' => Storage::disk('public')->path($this->guide->image_avant),
                'cheminApres' => Storage::disk('public')->path($this->guide->image_apres),
            ]);
    }
}
