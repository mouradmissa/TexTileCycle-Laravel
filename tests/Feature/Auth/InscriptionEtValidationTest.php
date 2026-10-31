<?php

namespace Tests\Feature\Auth;

use App\Modules\Associations\Services\AssociationService;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\UserService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\Concerns\FakesMongoPresenceVerifier;
use Tests\TestCase;

class InscriptionEtValidationTest extends TestCase
{
    use BlocksRemoteMongo;
    use FakesMongoPresenceVerifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();

        $this->app['validator']->setPresenceVerifier($this->presenceVerifierEnMemoire([
            '652f00000000000000000022' => 'pris@example.test',
        ]));
    }

    /**
     * @param  class-string<FormRequest>  $classe
     */
    private function resoudre(string $classe, array $donnees): FormRequest
    {
        $request = $classe::create('/inscription', 'POST', $donnees);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        $request->validateResolved();

        return $request;
    }

    private function payload(array $override = []): array
    {
        return array_replace([
            'name' => 'Samia Ben Ali',
            'email' => 'nouveau@example.test',
            'password' => 'motDePasseSolide',
            'password_confirmation' => 'motDePasseSolide',
            'role' => User::ROLE_CITOYEN,
            'phone' => '+216 22 123 456',
        ], $override);
    }

    public function test_l_inscription_accepte_citoyen_atelier_et_association(): void
    {
        foreach (User::ROLES_INSCRIPTION as $role) {
            $validated = $this->resoudre(RegisterRequest::class, $this->payload(['role' => $role]))->validated();
            $this->assertSame($role, $validated['role']);
            $this->assertSame(
                $role === User::ROLE_CITOYEN ? '+216 22 123 456' : null,
                $validated['phone']
            );
        }
    }

    public function test_l_inscription_refuse_le_role_administrateur(): void
    {
        try {
            $this->resoudre(RegisterRequest::class, $this->payload(['role' => User::ROLE_ADMIN]));
            $this->fail('Le rôle administrateur aurait dû être refusé.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('role', $e->errors());
        }
    }

    public function test_un_citoyen_est_connecte_des_l_inscription(): void
    {
        $user = new User([
            'name' => 'Samia Ben Ali',
            'email' => 'nouveau@example.test',
            'role' => User::ROLE_CITOYEN,
            'is_active' => true,
        ]);
        $user->setAttribute('_id', '652f000000000000000000aa');

        $service = Mockery::mock(UserService::class);
        $service->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $data) => $data['role'] === User::ROLE_CITOYEN && $data['phone'] === '+216 22 123 456')
            ->andReturn($user);
        $this->app->instance(UserService::class, $service);

        $this->post('/inscription', $this->payload())
            ->assertRedirect('/mes-vetements');

        $this->assertAuthenticated();
    }

    public function test_un_atelier_n_est_pas_connecte_tant_que_l_admin_n_a_pas_valide(): void
    {
        $user = new User([
            'name' => 'Atelier Medina',
            'email' => 'atelier@example.test',
            'role' => User::ROLE_ATELIER,
            'is_active' => false,
        ]);

        $service = Mockery::mock(UserService::class);
        $service->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $data) => $data['role'] === User::ROLE_ATELIER && ($data['phone'] ?? null) === null)
            ->andReturn($user);
        $this->app->instance(UserService::class, $service);

        $this->post('/inscription', $this->payload([
            'email' => 'atelier@example.test',
            'role' => User::ROLE_ATELIER,
        ]))
            ->assertRedirect('/connexion')
            ->assertSessionHas('success');

        $this->assertGuest();
    }

    public function test_une_association_n_est_pas_connectee_tant_que_l_admin_n_a_pas_valide(): void
    {
        $user = new User([
            'name' => 'Association Verte',
            'email' => 'asso@example.test',
            'role' => User::ROLE_ASSOCIATION,
            'is_active' => false,
        ]);

        $service = Mockery::mock(UserService::class);
        $service->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $data) => $data['role'] === User::ROLE_ASSOCIATION && ($data['phone'] ?? null) === null)
            ->andReturn($user);
        $this->app->instance(UserService::class, $service);

        $this->post('/inscription', $this->payload([
            'email' => 'asso@example.test',
            'role' => User::ROLE_ASSOCIATION,
        ]))
            ->assertRedirect('/connexion')
            ->assertSessionHas('success');

        $this->assertGuest();
    }

    public function test_connexion_refusee_si_le_compte_atelier_attend_la_validation(): void
    {
        $user = new User([
            'name' => 'Atelier Medina',
            'email' => 'atelier@example.test',
            'role' => User::ROLE_ATELIER,
            'is_active' => false,
        ]);

        $service = Mockery::mock(UserService::class);
        $service->shouldReceive('findAuthenticatable')
            ->once()
            ->andReturn($user);
        $this->app->instance(UserService::class, $service);

        $this->from('/connexion')
            ->post('/connexion', [
                'email' => 'atelier@example.test',
                'password' => 'motDePasseSolide',
            ])
            ->assertRedirect('/connexion')
            ->assertSessionHasErrors(['email']);

        $this->assertGuest();
        $this->assertStringContainsString(
            'validation administrative',
            session('errors')->first('email')
        );
    }

    public function test_connexion_atelier_valide_sans_fiche_vers_la_creation(): void
    {
        $user = new User([
            'name' => 'Atelier Medina',
            'email' => 'atelier@example.test',
            'role' => User::ROLE_ATELIER,
            'is_active' => true,
        ]);
        $user->setAttribute('_id', '652f000000000000000000ab');

        $users = Mockery::mock(UserService::class);
        $users->shouldReceive('findAuthenticatable')->once()->andReturn($user);
        $this->app->instance(UserService::class, $users);

        $ateliers = Mockery::mock(AtelierService::class);
        $ateliers->shouldReceive('findOwnedByUser')->with('652f000000000000000000ab')->andReturn(null);
        $this->app->instance(AtelierService::class, $ateliers);

        $this->post('/connexion', [
            'email' => 'atelier@example.test',
            'password' => 'motDePasseSolide',
        ])->assertRedirect('/admin/ateliers/mon-profil');

        $this->assertAuthenticated();
    }

    public function test_connexion_atelier_valide_avec_fiche_vers_le_back_office(): void
    {
        $user = new User([
            'name' => 'Atelier Medina',
            'email' => 'atelier@example.test',
            'role' => User::ROLE_ATELIER,
            'is_active' => true,
        ]);
        $user->setAttribute('_id', '652f000000000000000000ab');

        $users = Mockery::mock(UserService::class);
        $users->shouldReceive('findAuthenticatable')->once()->andReturn($user);
        $this->app->instance(UserService::class, $users);

        $ateliers = Mockery::mock(AtelierService::class);
        $ateliers->shouldReceive('findOwnedByUser')->with('652f000000000000000000ab')->andReturn(new \App\Modules\Ateliers\Models\Atelier(['nom' => 'Medina']));
        $this->app->instance(AtelierService::class, $ateliers);

        $this->post('/connexion', [
            'email' => 'atelier@example.test',
            'password' => 'motDePasseSolide',
        ])->assertRedirect('/admin');

        $this->assertAuthenticated();
    }

    public function test_connexion_association_validee_sans_fiche_vers_la_creation(): void
    {
        $user = new User([
            'name' => 'Association Verte',
            'email' => 'asso@example.test',
            'role' => User::ROLE_ASSOCIATION,
            'is_active' => true,
        ]);
        $user->setAttribute('_id', '652f000000000000000000ad');

        $users = Mockery::mock(UserService::class);
        $users->shouldReceive('findAuthenticatable')->once()->andReturn($user);
        $this->app->instance(UserService::class, $users);

        $associations = Mockery::mock(AssociationService::class);
        $associations->shouldReceive('findOwnedByUser')->with('652f000000000000000000ad')->andReturn(null);
        $this->app->instance(AssociationService::class, $associations);

        $this->post('/connexion', [
            'email' => 'asso@example.test',
            'password' => 'motDePasseSolide',
        ])->assertRedirect('/admin/associations/creer');

        $this->assertAuthenticated();
    }

    public function test_un_compte_en_attente_est_deconnecte_des_pages_protegees(): void
    {
        $user = new User([
            'name' => 'Atelier Medina',
            'email' => 'atelier@example.test',
            'role' => User::ROLE_ATELIER,
            'is_active' => false,
        ]);
        $user->setAttribute('_id', '652f000000000000000000ac');

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect('/connexion');

        $this->assertGuest();
    }
}
