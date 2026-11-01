<?php

namespace App\Modules\Ateliers\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Modules\Ateliers\Exceptions\AssistantIndisponibleException;
use App\Modules\Ateliers\Http\Requests\AssistantRequest;
use App\Modules\Ateliers\Services\AssistantService;

/**
 * Assistant de réparation (RAG). Avec JavaScript (js/assistant.js), la question part sans recharger la page et la
 * réponse revient déjà mise en forme (vue partielle) ; sans JavaScript, la page est rendue avec la réponse.
 */
class AssistantController extends Controller
{
    /** Questions proposées sous le champ, pour montrer ce que l'assistant sait faire. */
    public const EXEMPLES = [
        'Comment enlever une tache de gras sur un jean ?',
        'Ma fermeture éclair ne ferme plus',
        'Il y a un trou de mite dans mon pull',
        'Réparer ou donner un vêtement abîmé ?',
    ];

    public function __construct(private AssistantService $assistant)
    {
    }

    public function create()
    {
        return view('front.assistant', ['question' => null, 'resultat' => null, 'exemples' => self::EXEMPLES]);
    }

    public function store(AssistantRequest $request)
    {
        $question = $request->validated('question');

        try {
            $resultat = $this->assistant->repondre($question);
        } catch (AssistantIndisponibleException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 503);
            }

            return redirect()
                ->route('front.ateliers.assistant')
                ->withInput()
                ->with('assistant_erreur', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('front.partials.assistant-reponse', ['question' => $question, 'resultat' => $resultat])->render(),
            ]);
        }

        return view('front.assistant', ['question' => $question, 'resultat' => $resultat, 'exemples' => self::EXEMPLES]);
    }
}
