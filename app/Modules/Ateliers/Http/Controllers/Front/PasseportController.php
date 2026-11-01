<?php

namespace App\Modules\Ateliers\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Modules\Ateliers\Http\Requests\VerifierPasseportRequest;
use App\Modules\Ateliers\Services\PasseportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Passeports de réparation : page publique (ouverte par le QR code, sans connexion), image du QR,
 * et page « Mes passeports » du client connecté. Les pages ne sont pas indexées par les moteurs de recherche.
 */
class PasseportController extends Controller
{
    public function __construct(private PasseportService $passeports)
    {
    }

    public function show(string $code)
    {
        $passeport = $this->passeports->trouverParCode($code);

        // QR abîmé ou lien mal copié : une page qui explique et propose de saisir le code, plutôt qu'une 404 brute.
        if (! $passeport) {
            return $this->nonIndexe(response()->view('front.passeport-verifier', ['introuvable' => true], 404));
        }

        $historique = $passeport->estRevoque() ? collect() : $this->passeports->historique($passeport);

        $response = response()->view('front.passeport', [
            'passeport' => $passeport,
            'historique' => $historique,
            'ateliers' => $this->passeports->ateliersDe($historique),
        ]);

        return $this->nonIndexe($response);
    }

    /** Formulaire « Vérifier un passeport » (code ou adresse copiée sous le QR). */
    public function verifier()
    {
        return $this->nonIndexe(response()->view('front.passeport-verifier', ['introuvable' => false]));
    }

    public function rechercher(VerifierPasseportRequest $request)
    {
        $code = $request->code();

        if (! $this->passeports->trouverParCode($code)) {
            return redirect()->route('front.passeport.verifier')
                ->withInput()
                ->withErrors(['code' => 'Aucun passeport ne correspond à ce code. Vérifiez les majuscules et minuscules.']);
        }

        return redirect()->route('front.passeport', ['code' => $code]);
    }

    public function qr(string $code)
    {
        $passeport = $this->passeports->trouverParCode($code);
        abort_unless($passeport, 404);

        return $this->nonIndexe(response($this->passeports->qrSvg($passeport), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]));
    }

    public function mesPasseports(Request $request)
    {
        return $this->nonIndexe(response()->view('front.mes-passeports', [
            'passeports' => $this->passeports->pourClient($request->user()),
        ]));
    }

    private function nonIndexe(Response $response): Response
    {
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
