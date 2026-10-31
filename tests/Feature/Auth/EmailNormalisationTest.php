<?php

namespace Tests\Feature\Auth;

use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Auth\Http\Requests\StoreUserRequest;
use App\Modules\Auth\Http\Requests\UpdateUserRequest;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\UserService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\Concerns\FakesMongoPresenceVerifier;
use Tests\TestCase;

/**
 * L'e-mail est normalisé (trim + minuscules) avant la règle d'unicité, comme UserService
 * l'enregistre. Aucun accès à Atlas : unicité évaluée en mémoire, écritures simulées.
 */
class EmailNormalisationTest extends TestCase
{
    use BlocksRemoteMongo;
    use FakesMongoPresenceVerifier;

    private const ID_MODIFIE = '652f00000000000000000011';

    private const ID_AUTRE = '652f00000000000000000022';

    private const ID_ADMIN = '652f00000000000000000033';

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();

        $this->app['validator']->setPresenceVerifier($this->presenceVerifierEnMemoire([
            self::ID_MODIFIE => 'atelier@example.test',
            self::ID_AUTRE => 'autre@example.test',
        ]));
    }

    private function admin(): User
    {
        $admin = new User(['name' => 'Admin', 'email' => 'admin@example.test', 'role' => User::ROLE_ADMIN]);
        $admin->setAttribute('_id', self::ID_ADMIN);

        return $admin;
    }

    /**
     * Résout la FormRequest comme le ferait le conteneur (prepareForValidation, puis validation).
     *
     * @template T of FormRequest
     *
     * @param  class-string<T>  $classe
     * @return T
     */
    private function resoudre(string $classe, array $donnees, ?string $userIdRoute = null): FormRequest
    {
        $request = $classe::create('/test', 'POST', $donnees);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        $request->setUserResolver(fn () => $this->admin());

        if ($userIdRoute !== null) {
            $route = (new Route('PUT', '/admin/utilisateurs/{user}', []))->bind($request);
            $route->setParameter('user', $userIdRoute);
            $request->setRouteResolver(fn () => $route);
        }

        $this->actingAs($this->admin());
        $request->validateResolved();

        return $request;
    }

    private function donnees(string $email, array $override = []): array
    {
        return array_replace([
            'name' => 'Samia Ben Ali',
            'email' => $email,
            'password' => 'motDePasseSolide',
            'password_confirmation' => 'motDePasseSolide',
            'role' => User::ROLE_ATELIER,
        ], $override);
    }

    private function assertEmailRefuse(callable $resolution): void
    {
        try {
            $resolution();
            $this->fail("L'e-mail aurait dû être refusé.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
        }
    }

    /* ------------------------------------------------------ Normalisation */

    public function test_l_email_est_nettoye_avant_la_validation_dans_les_trois_requetes(): void
    {
        $brut = '  Nouveau.Compte@Example.TEST  ';

        $this->assertSame('nouveau.compte@example.test', $this->resoudre(StoreUserRequest::class, $this->donnees($brut))->validated()['email']);
        $this->assertSame('nouveau.compte@example.test', $this->resoudre(UpdateUserRequest::class, $this->donnees($brut), self::ID_MODIFIE)->validated()['email']);
        $this->assertSame('nouveau.compte@example.test', $this->resoudre(RegisterRequest::class, $this->donnees($brut, ['role' => User::ROLE_CITOYEN, 'phone' => '+216 22 123 456']))->validated()['email']);
    }

    public function test_un_email_absent_ou_non_textuel_n_est_pas_transforme(): void
    {
        $this->assertEmailRefuse(fn () => $this->resoudre(StoreUserRequest::class, $this->donnees('x', ['email' => null])));
        $this->assertEmailRefuse(fn () => $this->resoudre(StoreUserRequest::class, $this->donnees('x', ['email' => ['A@B.C']])));
    }

    /* ----------------------------------------------- Unicité insensible à la casse */

    public function test_creation_refuse_un_email_existant_ecrit_avec_une_autre_casse(): void
    {
        $this->assertEmailRefuse(fn () => $this->resoudre(StoreUserRequest::class, $this->donnees('Autre@Example.test')));
    }

    public function test_modification_refuse_l_email_d_un_autre_utilisateur_avec_une_autre_casse(): void
    {
        $this->assertEmailRefuse(fn () => $this->resoudre(UpdateUserRequest::class, $this->donnees('Autre@Example.test'), self::ID_MODIFIE));
    }

    public function test_inscription_refuse_un_email_existant_ecrit_avec_une_autre_casse(): void
    {
        $this->assertEmailRefuse(fn () => $this->resoudre(RegisterRequest::class, $this->donnees('AUTRE@example.test', ['role' => User::ROLE_CITOYEN])));
    }

    public function test_modification_en_gardant_son_propre_email_avec_une_autre_casse_est_acceptee(): void
    {
        $request = $this->resoudre(UpdateUserRequest::class, $this->donnees(' Atelier@EXAMPLE.test ', ['password' => null]), self::ID_MODIFIE);

        $this->assertSame('atelier@example.test', $request->validated()['email']);
    }

    /* ------------------------------------------------------------- HTTP */

    public function test_creation_par_l_admin_renvoie_une_erreur_de_validation_et_non_une_500(): void
    {
        $service = Mockery::mock(UserService::class);
        $service->shouldNotReceive('create');
        $this->app->instance(UserService::class, $service);

        $this->actingAs($this->admin())
            ->from('/admin/utilisateurs')
            ->post('/admin/utilisateurs', $this->donnees('Autre@Example.test'))
            ->assertRedirect('/admin/utilisateurs')
            ->assertSessionHasErrors(['email' => 'Cette adresse e-mail est déjà utilisée.']);
    }

    public function test_creation_par_l_admin_transmet_l_email_normalise_au_service(): void
    {
        $service = Mockery::mock(UserService::class);
        $service->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $data) => $data['email'] === 'nouveau@example.test')
            ->andReturn(new User());
        $this->app->instance(UserService::class, $service);

        $this->actingAs($this->admin())
            ->post('/admin/utilisateurs', $this->donnees('  Nouveau@Example.TEST '))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
    }

    public function test_inscription_renvoie_une_erreur_de_validation_et_non_une_500(): void
    {
        $service = Mockery::mock(UserService::class);
        $service->shouldNotReceive('create');
        $this->app->instance(UserService::class, $service);

        $this->from('/inscription')
            ->post('/inscription', $this->donnees('Autre@Example.test', ['role' => User::ROLE_CITOYEN]))
            ->assertRedirect('/inscription')
            ->assertSessionHasErrors(['email' => 'Cette adresse e-mail est déjà utilisée.']);
    }
}
