<?php

namespace App\Modules\Ateliers\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Ateliers\Http\Controllers\Back\Concerns\GereEspaceAtelier;
use App\Modules\Ateliers\Http\Requests\AssistantAtelierRequest;
use App\Modules\Ateliers\Services\AssistantAtelierService;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use Illuminate\Http\Request;

/**
 * Assistant de l'espace atelier (bouton flottant). Les données consultées sont toujours celles de l'atelier du
 * compte connecté : l'identifiant ne vient jamais de la question ni du formulaire.
 * Avec JavaScript, réponse JSON {html} ; sans JavaScript, page complète.
 */
class MonAssistantController extends Controller
{
    use GereEspaceAtelier;
    use RendersBackOffice;

    public function __construct(
        private AtelierService $ateliers,
        private AssistantAtelierService $assistant,
    ) {
        $this->reserverAuRoleAtelier();
    }

    public function create(Request $request)
    {
        $atelier = $this->monAtelier($request);

        if (! $atelier) {
            return $this->redirectionAtelierNonConfigure();
        }

        return $this->backView('back.ateliers.espace.assistant', [
            'pageTitle' => 'Mon atelier',
            'atelier' => $atelier,
            'nbServices' => $atelier->services->count(),
            'question' => null,
            'reponse' => null,
        ]);
    }

    public function store(AssistantAtelierRequest $request)
    {
        $atelier = $this->monAtelier($request);

        if (! $atelier) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Créez d\'abord la fiche de votre atelier.'], 409)
                : $this->redirectionAtelierNonConfigure();
        }

        $outil = $request->validated('outil');
        $question = $outil !== null ? AssistantAtelierService::RACCOURCIS[$outil] : $request->validated('question');
        $reponse = $this->assistant->repondre($atelier, $request->validated('question'), $outil);

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('back.ateliers.espace.partials.assistant-reponse', ['question' => $question, 'reponse' => $reponse])->render(),
            ]);
        }

        return $this->backView('back.ateliers.espace.assistant', [
            'pageTitle' => 'Mon atelier',
            'atelier' => $atelier,
            'nbServices' => $atelier->services->count(),
            'question' => $question,
            'reponse' => $reponse,
        ]);
    }
}
