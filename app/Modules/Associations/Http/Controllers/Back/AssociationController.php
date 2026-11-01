<?php

namespace App\Modules\Associations\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Associations\Models\Association;
use App\Modules\Associations\Services\AssociationService;
use App\Modules\Associations\Services\BesoinsTexteLibreService;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\UserService;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssociationController extends Controller
{
    use RendersBackOffice;

    public function __construct(
        private AssociationService $associations,
        private BesoinsTexteLibreService $besoinsTexte
    ) {
    }

    /** Convertit une description libre en lignes de besoins (JSON). Rien n'est enregistré. */
    public function besoinsDepuisTexte(Request $request)
    {
        $data = $request->validate([
            'texte' => [
                'required', 'string',
                'min:'.BesoinsTexteLibreService::LONGUEUR_MIN,
                'max:'.BesoinsTexteLibreService::LONGUEUR_MAX,
            ],
        ]);

        return response()->json($this->besoinsTexte->extraire($data['texte']));
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if ($user?->role === User::ROLE_ASSOCIATION) {
            $owned = $this->associations->findOwnedByUser((string) $user->getKey());

            if (! $owned) {
                return redirect()->route('back.associations.create');
            }

            return $this->backView('back.associations.index', [
                'pageTitle' => 'Mon association',
                'associations' => collect([$owned->load('owner')]),
                'statuts' => Association::STATUTS,
                'statut' => null,
                'espaceAssociation' => true,
            ]);
        }

        abort_unless($user?->isAdmin(), 403);

        $statut = $request->get('statut');
        $query = Association::with('owner')->orderBy('created_at', 'desc');

        if ($statut && in_array($statut, Association::STATUTS, true)) {
            $query->where('statut', $statut);
        }

        return $this->backView('back.associations.index', [
            'pageTitle' => 'Associations',
            'associations' => $query->get(),
            'statuts' => Association::STATUTS,
            'statut' => $statut,
            'espaceAssociation' => false,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $premiereFiche = false;

        if ($user?->role === User::ROLE_ASSOCIATION) {
            $owned = $this->associations->findOwnedByUser((string) $user->getKey());
            if ($owned) {
                return redirect()->route('back.associations.edit', $owned->getKey());
            }
            $premiereFiche = true;
        } else {
            abort_unless($user?->isAdmin(), 403);
        }

        return $this->backView('back.associations.form', [
            'pageTitle' => $premiereFiche ? 'Créer mon association' : 'Nouvelle association',
            'association' => null,
            'typesTextile' => Association::TYPES_TEXTILE,
            'tailles' => Association::TAILLES,
            'premiereFiche' => $premiereFiche,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $estAssociation = $user?->role === User::ROLE_ASSOCIATION;

        if (! $estAssociation) {
            abort_unless($user?->isAdmin(), 403);
        }

        $data = $this->validerFiche($request, ! $estAssociation);
        $data['besoins'] = $this->associations->normaliserBesoins($data['besoins'] ?? []);

        if ($estAssociation) {
            $this->associations->createOwn((string) $user->getKey(), $data);

            return redirect()
                ->route('back.associations')
                ->with('success', 'Votre association a été créée. Elle sera visible après validation administrative.');
        }

        $data['user_id'] = (string) $user->getKey();
        $association = Association::create($data);

        app(UserService::class)->syncActiveFromPartnerStatut(
            $association->user_id,
            $association->statut
        );

        return redirect()->route('back.associations')->with('success', 'Association créée avec succès.');
    }

    public function edit(Request $request, string $id)
    {
        $association = $this->associationAutorisee($request, $id);

        return $this->backView('back.associations.form', [
            'pageTitle' => 'Modifier l\'association',
            'association' => $association,
            'typesTextile' => Association::TYPES_TEXTILE,
            'tailles' => Association::TAILLES,
            'premiereFiche' => false,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $association = $this->associationAutorisee($request, $id);
        $estAdmin = $request->user()?->isAdmin() === true;

        $data = $this->validerFiche($request, $estAdmin);
        $data['besoins'] = $this->associations->normaliserBesoins($data['besoins'] ?? []);

        if (! $estAdmin) {
            unset($data['statut']);
        }

        $association->update($data);

        if ($estAdmin) {
            app(UserService::class)->syncActiveFromPartnerStatut(
                $association->user_id !== null ? (string) $association->user_id : null,
                $association->statut
            );
        }

        return redirect()->route('back.associations')->with('success', 'Association mise à jour.');
    }

    public function destroy(Request $request, string $id)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $association = Association::findOrFail($id);
        $association->delete();

        return redirect()->route('back.associations')->with('success', 'Association supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validerFiche(Request $request, bool $avecStatut): array
    {
        $rules = [
            'nom' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'adresse' => ['required', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'besoins' => ['nullable', 'array'],
            'besoins.*.type' => ['required', 'string'],
            'besoins.*.taille' => ['nullable', 'string'],
            'besoins.*.quantite' => ['nullable', 'integer', 'min:1'],
        ];

        if ($avecStatut) {
            $rules['statut'] = ['required', Rule::in(Association::STATUTS)];
        }

        return $request->validate($rules);
    }

    private function associationAutorisee(Request $request, string $id): Association
    {
        $association = Association::findOrFail($id);
        $user = $request->user();

        if ($user?->isAdmin()) {
            return $association;
        }

        abort_unless(
            $user?->role === User::ROLE_ASSOCIATION
            && (string) $association->user_id === (string) $user->getKey(),
            403,
            'Vous ne pouvez modifier que votre propre association.'
        );

        return $association;
    }
}
