<?php

namespace App\Modules\Dons\Services;

use App\Modules\Dons\Models\Don;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\URL;

/**
 * Reçu d'un don accepté : numéro, QR code et lien de vérification publique.
 * Le lien est signé (APP_KEY) : il ne peut être ni deviné ni modifié, et ne contient aucune donnée personnelle.
 */
class RecuDonService
{
    /** Un reçu n'existe que pour un don accepté. */
    public function disponible(Don $don): bool
    {
        return $don->statut === Don::STATUT_ACCEPTE;
    }

    /** Ex : RC-2026-3F9A1C (année du don + 6 derniers caractères de l'identifiant). */
    public function numero(Don $don): string
    {
        $annee = ($don->created_at ?? now())->format('Y');

        return 'RC-'.$annee.'-'.strtoupper(substr((string) $don->_id, -6));
    }

    public function urlVerification(Don $don): string
    {
        return URL::signedRoute('front.dons.recu.verifier', ['id' => (string) $don->_id]);
    }

    /** QR code de la page de vérification, en SVG (net à toute taille d'impression). */
    public function qrSvg(Don $don, int $taille = 160): string
    {
        $renderer = new ImageRenderer(new RendererStyle($taille, 2), new SvgImageBackEnd());

        return (new Writer($renderer))->writeString($this->urlVerification($don), 'UTF-8', ErrorCorrectionLevel::Q());
    }
}
