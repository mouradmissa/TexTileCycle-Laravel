<?php

namespace App\Modules\Dons\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Modules\Dons\Models\Don;
use App\Modules\Dons\Services\RecuDonService;

class RecuDonController extends Controller
{
    public function __construct(private RecuDonService $recus)
    {
    }

    /** Reçu imprimable du citoyen : seulement pour ses propres dons acceptés. */
    public function show(string $id)
    {
        $don = Don::where('_id', $id)
            ->where('user_id', auth()->user()->_id)
            ->with(['vetement', 'association', 'donateur'])
            ->firstOrFail();

        abort_unless($this->recus->disponible($don), 404);

        return view('front.dons-recu', [
            'don' => $don,
            'numero' => $this->recus->numero($don),
            'qr' => $this->recus->qrSvg($don),
            'urlVerification' => $this->recus->urlVerification($don),
        ]);
    }

    /** Page publique ouverte par le QR code (lien signé) : confirme l'authenticité, sans nom ni contact du donateur. */
    public function verifier(string $id)
    {
        $don = Don::where('_id', $id)->with(['vetement', 'association'])->first();

        return view('front.dons-recu-verification', [
            'don' => $don,
            'valide' => $don !== null && $this->recus->disponible($don),
            'numero' => $don !== null ? $this->recus->numero($don) : null,
        ]);
    }
}
