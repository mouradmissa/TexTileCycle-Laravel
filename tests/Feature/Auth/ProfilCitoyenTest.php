<?php

namespace Tests\Feature\Auth;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\UserService;
use Mockery;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\Concerns\FakesMongoPresenceVerifier;
use Tests\TestCase;

class ProfilCitoyenTest extends TestCase
{
    use BlocksRemoteMongo;
    use FakesMongoPresenceVerifier;

    private const ID_USER = '652f000000000000000000aa';

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();
        $this->app['validator']->setPresenceVerifier($this->presenceVerifierEnMemoire([
            '652f00000000000000000022' => 'pris@example.test',
        ]));
    }

    public function test_un_invite_est_renvoye_vers_la_connexion(): void
    {
        $this->get('/mon-profil')->assertRedirect('/connexion');
    }

    public function test_un_atelier_ne_peut_pas_ouvrir_le_profil_client(): void
    {
        $this->actingAs($this->utilisateur(User::ROLE_ATELIER))
            ->get('/mon-profil')
            ->assertForbidden();
    }

    public function test_le_citoyen_voit_ses_informations(): void
    {
        $this->actingAs($this->utilisateur())
            ->get('/mon-profil')
            ->assertOk()
            ->assertSee('Mon profil')
            ->assertSee('Samia Ben Ali')
            ->assertSee('samia@example.test')
            ->assertSee('+216 22 123 456')
            ->assertSee('Modifier mes informations');
    }

    public function test_le_citoyen_enregistre_ses_informations_et_son_mot_de_passe(): void
    {
        $user = $this->utilisateur();

        $service = Mockery::mock(UserService::class);
        $service->shouldReceive('mettreAJourProfil')
            ->once()
            ->withArgs(function (User $compte, array $data) {
                return (string) $compte->getKey() === self::ID_USER
                    && $data['name'] === 'Yasmine Ben Ali'
                    && $data['email'] === 'yasmine@example.test'
                    && $data['phone'] === '+216 98 111 222'
                    && $data['password'] === 'nouveauMotDePasse';
            })
            ->andReturn($user);
        $this->app->instance(UserService::class, $service);

        $this->actingAs($user)
            ->put('/mon-profil', [
                'name' => 'Yasmine Ben Ali',
                'email' => 'Yasmine@Example.test',
                'phone' => '+216 98 111 222',
                'current_password' => 'motDePasseSolide',
                'password' => 'nouveauMotDePasse',
                'password_confirmation' => 'nouveauMotDePasse',
            ])
            ->assertRedirect('/mon-profil')
            ->assertSessionHas('success');
    }

    public function test_un_mot_de_passe_actuel_incorrect_est_refuse(): void
    {
        $service = Mockery::mock(UserService::class);
        $service->shouldNotReceive('mettreAJourProfil');
        $this->app->instance(UserService::class, $service);

        $this->actingAs($this->utilisateur())
            ->from('/mon-profil')
            ->put('/mon-profil', [
                'name' => 'Samia Ben Ali',
                'email' => 'samia@example.test',
                'phone' => '+216 22 123 456',
                'current_password' => 'mauvais',
                'password' => 'nouveauMotDePasse',
                'password_confirmation' => 'nouveauMotDePasse',
            ])
            ->assertRedirect('/mon-profil')
            ->assertSessionHasErrors('current_password');

        $this->get('/mon-profil')
            ->assertSee('Changer mes informations', false)
            ->assertSee('Le mot de passe actuel est incorrect.', false)
            ->assertSee('modal-backdrop open', false);
    }

    public function test_un_email_deja_utilise_est_refuse(): void
    {
        $service = Mockery::mock(UserService::class);
        $service->shouldNotReceive('mettreAJourProfil');
        $this->app->instance(UserService::class, $service);

        $this->actingAs($this->utilisateur())
            ->from('/mon-profil')
            ->put('/mon-profil', [
                'name' => 'Samia Ben Ali',
                'email' => 'pris@example.test',
                'phone' => '+216 22 123 456',
            ])
            ->assertRedirect('/mon-profil')
            ->assertSessionHasErrors('email');
    }

    private function utilisateur(string $role = User::ROLE_CITOYEN): User
    {
        $user = new User([
            'name' => 'Samia Ben Ali',
            'email' => 'samia@example.test',
            'role' => $role,
            'phone' => '+216 22 123 456',
            'is_active' => true,
            'password' => 'motDePasseSolide',
        ]);
        $user->setAttribute('_id', self::ID_USER);

        return $user;
    }
}
