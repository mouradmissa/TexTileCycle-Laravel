<?php

namespace App\Modules\Vetements\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Modules\Vetements\Exceptions\AnalyseImageException;
use App\Modules\Vetements\Exceptions\ChatVetementException;
use App\Modules\Vetements\Http\Requests\AnalyserImageVetementRequest;
use App\Modules\Vetements\Http\Requests\DiscuterVetementRequest;
use App\Modules\Vetements\Exceptions\StyleVetementException;
use App\Modules\Vetements\Http\Requests\EnregistrerGuideRestyleRequest;
use App\Modules\Vetements\Http\Requests\ProposerStyleVetementRequest;
use App\Modules\Vetements\Http\Requests\RestylerPieceVetementRequest;
use App\Modules\Vetements\Http\Requests\RestylerVetementRequest;
use App\Modules\Vetements\Http\Requests\StoreVetementRequest;
use App\Modules\Vetements\Services\GuideRestyleService;
use App\Modules\Vetements\Services\PropositionStyleVetementService;
use App\Modules\Vetements\Http\Requests\UpdateIntendedActionRequest;
use App\Modules\Vetements\Models\Vetement;
use App\Modules\Vetements\Services\AnalyseImageVetementService;
use App\Modules\Vetements\Services\ChatVetementService;
use App\Modules\Vetements\Services\VetementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class VetementController extends Controller
{
    public function __construct(
        private VetementService $vetements,
        private AnalyseImageVetementService $analyses,
        private ChatVetementService $chat,
        private PropositionStyleVetementService $styles,
        private GuideRestyleService $guides
    ) {
    }

    public function index()
    {
        $vetements = $this->vetements->listActifsForOwner(auth()->user());
        $analysePhotoDisponible = $this->analyses->estConfigure();
        $chatDisponible = $this->chat->estConfigure();
        $styleDisponible = $this->styles->estConfigure();

        return view('front.vetements', compact('vetements', 'analysePhotoDisponible', 'chatDisponible', 'styleDisponible'));
    }

    public function historique(Request $request)
    {
        $filtre = $request->query('statut');
        $vetements = $this->vetements->listHistoriqueForOwner(auth()->user(), $filtre);

        return view('front.historique', [
            'vetements' => $vetements,
            'filtre' => $filtre,
            'chatDisponible' => $this->chat->estConfigure(),
            'filtres' => [
                '' => 'Tous',
                Vetement::STATUS_REPARE => 'Réparés',
                Vetement::STATUS_DONNE => 'Donnés',
                Vetement::STATUS_RECYCLE => 'Recyclés',
            ],
        ]);
    }

    public function store(StoreVetementRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['photo']);

        $info = null;

        if ($request->hasFile('photo')) {
            $data['image_path'] = $request->file('photo')->store('vetements', 'public');
            [$analyse, $info] = $this->analysePhoto($request->file('photo'));
            if ($analyse !== null) {
                $data['analyse_ia'] = $analyse;
            }
        }

        $vetement = $this->vetements->declare(auth()->user(), $data);

        $message = '« '.$vetement->displayName().' » a été déclaré — parcours '.$vetement->intendedActionLabel().'.';
        if (! empty($data['analyse_ia']['resume'])) {
            $message .= ' '.$data['analyse_ia']['resume'];
        }

        return $this->redirectApresParcours($vetement, $message, $info);
    }

    public function analyser(AnalyserImageVetementRequest $request): JsonResponse
    {
        if (! $this->analyses->estConfigure()) {
            return response()->json([
                'disponible' => false,
                'message' => 'L\'analyse photo n\'est pas activée sur cette installation.',
            ], 503);
        }

        try {
            $resultat = $this->analyses->analyser($request->file('photo'));
        } catch (AnalyseImageException $exception) {
            report($exception);

            return response()->json([
                'disponible' => false,
                'message' => $exception->getMessage(),
            ], 502);
        }

        return response()->json($resultat->toArray());
    }

    public function discuter(DiscuterVetementRequest $request): JsonResponse
    {
        if (! $this->chat->estConfigure()) {
            return response()->json([
                'disponible' => false,
                'message' => 'Le conseiller textile n\'est pas activé sur cette installation.',
            ], 503);
        }

        try {
            $resultat = $this->chat->repondre(
                $request->validated('message'),
                $request->validated('historique') ?? [],
                $this->vetements->contexteChat($request->user())
            );
        } catch (ChatVetementException $exception) {
            report($exception);

            return response()->json([
                'disponible' => false,
                'message' => $exception->getMessage(),
            ], 502);
        }

        return response()->json($resultat);
    }

    public function proposerStyle(ProposerStyleVetementRequest $request): JsonResponse
    {
        return $this->reponseStyle($request->file('photo'));
    }

    public function proposerStylePiece(string $vetement): JsonResponse
    {
        $model = $this->vetements->findOwnedByUser($vetement, auth()->user());

        if (! $model) {
            abort(404);
        }

        if (empty($model->image_path)) {
            return response()->json([
                'est_vetement' => false,
                'message' => 'Ajoutez une photo pour proposer un nouveau style.',
            ], 422);
        }

        $absolu = Storage::disk('public')->path($model->image_path);
        if (! is_file($absolu)) {
            return response()->json([
                'est_vetement' => false,
                'message' => 'La photo de cette pièce est introuvable.',
            ], 422);
        }

        $mime = mime_content_type($absolu);

        return $this->reponseStyle(new UploadedFile(
            $absolu,
            basename($absolu),
            is_string($mime) ? $mime : null,
            UPLOAD_ERR_OK,
            true
        ));
    }

    private function reponseStyle(UploadedFile $photo): JsonResponse
    {
        if (! $this->styles->estConfigure()) {
            return response()->json([
                'disponible' => false,
                'message' => 'Le nouveau style n\'est pas activé sur cette installation.',
            ], 503);
        }

        try {
            $resultat = $this->styles->proposer($photo);
        } catch (StyleVetementException $exception) {
            report($exception);

            return response()->json([
                'disponible' => false,
                'message' => $exception->getMessage(),
            ], 502);
        }

        if (empty($resultat['est_vetement'])) {
            return response()->json($resultat, 422);
        }

        return response()->json($resultat);
    }

    public function restyler(RestylerVetementRequest $request): JsonResponse
    {
        return $this->reponseRestyle($request->file('photo'), (string) $request->validated()['instruction']);
    }

    public function restylerPiece(RestylerPieceVetementRequest $request, string $vetement): JsonResponse
    {
        $model = $this->vetements->findOwnedByUser($vetement, auth()->user());

        if (! $model) {
            abort(404);
        }

        if (empty($model->image_path)) {
            return response()->json([
                'message' => 'Ajoutez une photo pour visualiser la réparation.',
            ], 422);
        }

        $absolu = Storage::disk('public')->path($model->image_path);
        if (! is_file($absolu)) {
            return response()->json([
                'message' => 'La photo de cette pièce est introuvable.',
            ], 422);
        }

        $mime = mime_content_type($absolu);

        return $this->reponseRestyle(new UploadedFile(
            $absolu,
            basename($absolu),
            is_string($mime) ? $mime : null,
            UPLOAD_ERR_OK,
            true
        ), (string) $request->validated()['instruction']);
    }

    private function reponseRestyle(UploadedFile $photo, string $instruction): JsonResponse
    {
        if (! $this->styles->estConfigure()) {
            return response()->json([
                'disponible' => false,
                'message' => 'Le restyle n\'est pas activé sur cette installation.',
            ], 503);
        }

        try {
            $resultat = $this->styles->restyler($photo, $instruction);
        } catch (StyleVetementException $exception) {
            report($exception);

            return response()->json([
                'disponible' => false,
                'message' => $exception->getMessage(),
            ], 502);
        }

        if (! empty($resultat['en_attente'])) {
            return response()->json($resultat, 202);
        }

        return response()->json($resultat);
    }

    public function enregistrerGuide(EnregistrerGuideRestyleRequest $request): JsonResponse
    {
        $vetementId = $request->validated('vetement_id');

        if ($request->hasFile('photo')) {
            $photo = $request->file('photo');
            $vetementId = null;
        } else {
            $model = $this->vetements->findOwnedByUser((string) $vetementId, $request->user());
            if (! $model) {
                abort(404);
            }

            $photo = $this->fichierPhoto($model);
            if (! $photo instanceof UploadedFile) {
                return response()->json([
                    'message' => 'La photo de cette pièce est introuvable.',
                ], 422);
            }

            $vetementId = (string) $model->getKey();
        }

        try {
            $resultat = $this->guides->enregistrer(
                $request->user(),
                $photo,
                (string) $request->validated('instruction'),
                $request->validated('jeton'),
                $request->validated('image_apres'),
                is_string($vetementId) ? $vetementId : null
            );
        } catch (StyleVetementException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Le guide n\'a pas pu être enregistré. Réessayez.',
            ], 500);
        }

        return response()->json($resultat, $resultat['envoye'] ? 200 : 502);
    }

    private function fichierPhoto(Vetement $model): ?UploadedFile
    {
        if (empty($model->image_path)) {
            return null;
        }

        $absolu = Storage::disk('public')->path($model->image_path);
        if (! is_file($absolu)) {
            return null;
        }

        $mime = mime_content_type($absolu);

        return new UploadedFile(
            $absolu,
            basename($absolu),
            is_string($mime) ? $mime : null,
            UPLOAD_ERR_OK,
            true
        );
    }

    public function analyserPiece(string $vetement): RedirectResponse
    {
        $model = $this->vetements->findOwnedByUser($vetement, auth()->user());

        if (! $model) {
            abort(404);
        }

        if (! $this->analyses->estConfigure()) {
            return back()->with('error', 'L\'analyse photo n\'est pas activée sur cette installation.');
        }

        if (empty($model->image_path)) {
            return back()->with('error', 'Ajoutez une photo pour analyser l\'état de cette pièce.');
        }

        $absolu = Storage::disk('public')->path($model->image_path);
        if (! is_file($absolu)) {
            return back()->with('error', 'La photo de cette pièce est introuvable.');
        }

        $mime = mime_content_type($absolu);

        try {
            $fichier = new UploadedFile(
                $absolu,
                basename($absolu),
                is_string($mime) ? $mime : null,
                UPLOAD_ERR_OK,
                true
            );
            $resultat = $this->analyses->analyser($fichier);
        } catch (AnalyseImageException $exception) {
            report($exception);

            return back()->with('error', $exception->getMessage());
        }

        $this->vetements->attacherAnalyse($model, $resultat->toArray());

        return back()->with('success', $resultat->toArray()['resume'] ?? 'Analyse enregistrée.');
    }

    /**
     * @return array{0: array<string, mixed>|null, 1: string|null}
     */
    private function analysePhoto(UploadedFile $photo): array
    {
        if (! $this->analyses->estConfigure()) {
            return [null, null];
        }

        try {
            return [$this->analyses->analyser($photo)->toArray(), null];
        } catch (AnalyseImageException $exception) {
            report($exception);

            return [null, 'La photo est enregistrée, mais l\'analyse de l\'état n\'a pas abouti. Vous pouvez la relancer depuis la fiche.'];
        }
    }

    public function updateAction(UpdateIntendedActionRequest $request, string $vetement): RedirectResponse
    {
        $model = $this->vetements->findOwnedByUser($vetement, auth()->user());

        if (! $model) {
            abort(404);
        }

        $model = $this->vetements->applyIntendedAction($model, $request->validated('intended_action'));

        return $this->redirectApresParcours($model, 'Parcours mis à jour : '.$model->intendedActionLabel().'.');
    }

    private function redirectApresParcours(Vetement $vetement, string $message, ?string $info = null): RedirectResponse
    {
        if ($vetement->intended_action === Vetement::ACTION_REPARATION) {
            $redirect = redirect()
                ->route('front.ateliers', ['vetement_id' => (string) $vetement->getKey()])
                ->with('success', $message.' Choisissez un atelier : le rendez-vous sera prérempli.');
        } else {
            $redirect = redirect()
                ->route('front.vetements')
                ->with('success', $message);
        }

        return $info ? $redirect->with('info', $info) : $redirect;
    }
}
