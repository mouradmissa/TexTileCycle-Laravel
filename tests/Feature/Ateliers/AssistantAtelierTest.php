<?php

namespace Tests\Feature\Ateliers;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Services\ActiviteAtelierService;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\Ateliers\Services\PasseportService;
use App\Modules\Auth\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Mockery\MockInterface;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\TestCase;

/**
 * Assistant de l'espace atelier (appel de fonctions) : Gemini est simulé (Http::fake), les données de l'atelier
 * aussi (ActiviteAtelierService). Vérifie que l'IA ne fait que choisir un outil de la liste, que l'atelier vient
 * toujours du compte connecté et qu'aucune donnée de la base ne part chez Google.
 */
class AssistantAtelierTest extends TestCase
{
    use BlocksRemoteMongo;

    private const ID_USER_ATELIER = '652f000000000000000000c1';

    private const ID_ATELIER = '652f000000000000000000a1';

    private const GEMINI = 'https://generativelanguage.googleapis.com/*';

    /** @var ActiviteAtelierService&MockInterface */
    private $activite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();
        config(['services.vision.gemini_key' => 'cle-de-test', 'services.vision.gemini_model' => 'gemini-test']);

        $atelier = new Atelier(['nom' => 'Couture Medina', 'ville' => 'Tunis', 'statut' => Atelier::STATUT_ACTIF]);
        $atelier->setAttribute('_id', self::ID_ATELIER);
        $atelier->user_id = self::ID_USER_ATELIER;
        $atelier->setRelation('services', new Collection());

        $ateliers = Mockery::mock(AtelierService::class)->makePartial();
        $ateliers->shouldReceive('findOwnedByUser')->with(self::ID_USER_ATELIER)->andReturn($atelier);
        $this->app->instance(AtelierService::class, $ateliers);

        $this->activite = Mockery::mock(ActiviteAtelierService::class);
        $this->app->instance(ActiviteAtelierService::class, $this->activite);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function compte(string $role = User::ROLE_ATELIER, string $id = self::ID_USER_ATELIER): User
    {
        $user = new User(['name' => 'Hédi Gharbi', 'email' => 'atelier@example.test', 'role' => $role, 'is_active' => true]);
        $user->setAttribute('_id', $id);

        return $user;
    }

    private function enAjax(): array
    {
        return ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];
    }

    private function appel(string $nom, array $args = []): array
    {
        return ['candidates' => [['content' => ['parts' => [['functionCall' => ['name' => $nom, 'args' => $args]]]]]]];
    }

    private function ouvert(): array
    {
        return [
            'jours' => 7,
            'rdv' => ['en_attente' => 2, 'confirme' => 1, 'annule' => 0, 'termine' => 4],
            'passeports' => 3,
        ];
    }

    public function test_la_page_et_le_bouton_flottant_sont_reserves_a_l_atelier(): void
    {
        $page = $this->actingAs($this->compte())->get('/admin/ateliers/mon-assistant')
            ->assertOk()
            ->assertSee('Assistant de mon atelier')
            ->assertSee('Garanties qui expirent')
            ->assertSee('js/assistant-atelier.js');
        $this->assertDoesNotMatchRegularExpression('/\s(required|maxlength|minlength|pattern)(=|\s|>)/', $page->getContent());

        // Bouton flottant sur les autres pages de l'espace (ici : Passeports).
        $passeports = Mockery::mock(PasseportService::class)->makePartial();
        $passeports->shouldReceive('reparationsDeLatelier')->andReturn(collect());
        $passeports->shouldReceive('reparationsEnCours')->andReturn(new Collection());
        $this->app->instance(PasseportService::class, $passeports);
        $this->actingAs($this->compte())->get('/admin/ateliers/mes-passeports')
            ->assertOk()
            ->assertSee('data-aa-flottant', false)
            ->assertSee('href="'.route('back.ateliers.assistant').'"', false);

        // L'admin passe le contrôle du back office mais pas celui du rôle atelier ; un citoyen est renvoyé hors du back office.
        $this->actingAs($this->compte(User::ROLE_ADMIN, '652f000000000000000000d1'))->get('/admin/ateliers/mon-assistant')->assertForbidden();
        $this->actingAs($this->compte(User::ROLE_ADMIN, '652f000000000000000000d1'))
            ->post('/admin/ateliers/mon-assistant', ['outil' => 'avis_clients'])
            ->assertForbidden();
        $this->actingAs($this->compte(User::ROLE_CITOYEN, '652f000000000000000000c9'))->get('/admin/ateliers/mon-assistant')->assertRedirect();
    }

    public function test_un_raccourci_appelle_l_outil_sans_passer_par_l_ia(): void
    {
        Http::fake();
        $this->activite->shouldReceive('garantiesQuiExpirent')
            ->once()
            ->with(Mockery::on(fn ($a) => (string) $a->getKey() === self::ID_ATELIER), 30)
            ->andReturn([['numero' => 'AB3D-EF6H', 'piece' => 'Jean <b>M</b>', 'reparation' => 'Ourlet', 'fin' => Carbon::parse('2026-11-20')]]);

        $html = $this->actingAs($this->compte())
            ->post('/admin/ateliers/mon-assistant', ['outil' => 'garanties_qui_expirent'], $this->enAjax())
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('Garanties qui expirent', $html);
        $this->assertStringContainsString('1 garantie se termine dans les 30 prochains jours', $html);
        $this->assertStringContainsString('20/11/2026 · Jean &lt;b&gt;M&lt;/b&gt; · Ourlet (n° AB3D-EF6H)', $html);
        Http::assertNothingSent();
    }

    public function test_l_ia_choisit_l_outil_et_le_code_repond_avec_les_vrais_chiffres(): void
    {
        Http::fake([self::GEMINI => Http::response($this->appel('resume_activite', ['periode' => 'semaine']))]);
        $this->activite->shouldReceive('resume')
            ->once()
            ->with(Mockery::on(fn ($a) => (string) $a->getKey() === self::ID_ATELIER), 'semaine')
            ->andReturn($this->ouvert());

        $html = $this->actingAs($this->compte())
            ->post('/admin/ateliers/mon-assistant', ['question' => 'Combien de rendez-vous cette semaine ?'], $this->enAjax())
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('Votre activité sur les 7 derniers jours', $html);
        $this->assertStringContainsString('7 rendez-vous sur les 7 derniers jours, dont 4 terminés. Passeports créés : 3.', $html);
        $this->assertStringContainsString('Question comprise par l&#039;IA', $html);

        // Gemini reçoit la question, la liste fermée des outils et l'obligation d'en choisir un ; aucune donnée de l'atelier.
        Http::assertSent(function (Request $r) {
            $corps = $r->data();
            $outils = collect($corps['tools'][0]['functionDeclarations'])->pluck('name')->all();

            return str_contains($r->url(), 'gemini-test:generateContent')
                && $corps['contents'][0]['parts'][0]['text'] === 'Combien de rendez-vous cette semaine ?'
                && $corps['toolConfig']['functionCallingConfig']['mode'] === 'ANY'
                && in_array('garanties_qui_expirent', $outils, true)
                && in_array('hors_sujet', $outils, true)
                && ! str_contains(json_encode($corps), 'Couture Medina')
                && ! str_contains(json_encode($corps), self::ID_ATELIER);
        });
    }

    public function test_un_parametre_hors_liste_prend_la_valeur_par_defaut(): void
    {
        Http::fake([self::GEMINI => Http::response($this->appel('prochains_rendez_vous', ['jours' => '365']))]);
        $this->activite->shouldReceive('prochainsRendezVous')->once()->with(Mockery::any(), 7)->andReturn([]);

        $this->actingAs($this->compte())
            ->post('/admin/ateliers/mon-assistant', ['question' => 'mes rendez-vous'], $this->enAjax())
            ->assertOk()
            ->assertJsonPath('html', fn (string $html) => str_contains($html, 'Aucun rendez-vous prévu dans les 7 prochains jours.'));
    }

    public function test_un_outil_inconnu_propose_par_l_ia_est_ignore(): void
    {
        // L'IA « invente » un outil : on retombe sur l'aiguillage par mots-clés.
        Http::fake([self::GEMINI => Http::response($this->appel('supprimer_tous_les_clients'))]);
        $this->activite->shouldReceive('avis')->once()->andReturn(['moyenne' => 4.5, 'total' => 2, 'derniers' => []]);

        $this->actingAs($this->compte())
            ->post('/admin/ateliers/mon-assistant', ['question' => 'Quelle est ma note ?'], $this->enAjax())
            ->assertOk()
            ->assertJsonPath('html', fn (string $html) => str_contains($html, 'Note moyenne 4,5 sur 5, sur 2 avis vérifiés.')
                && str_contains($html, 'Compris par mots-clés'));
    }

    public function test_hors_sujet_donne_l_aide_sans_lire_les_donnees(): void
    {
        Http::fake([self::GEMINI => Http::response($this->appel('hors_sujet'))]);
        $this->activite->shouldNotReceive('resume', 'avis', 'garantiesQuiExpirent', 'servicesLesPlusDemandes', 'prochainsRendezVous');

        $this->actingAs($this->compte())
            ->post('/admin/ateliers/mon-assistant', ['question' => 'Écris un poème sur la mer'], $this->enAjax())
            ->assertOk()
            ->assertJsonPath('html', fn (string $html) => str_contains($html, 'Je réponds aux questions sur votre activité'));
    }

    public function test_sans_gemini_l_aiguillage_par_mots_cles_prend_le_relais(): void
    {
        Http::fake([self::GEMINI => Http::response(['error' => 'quota'], 429)]);
        $this->activite->shouldReceive('servicesLesPlusDemandes')->once()->with(Mockery::any(), 3)
            ->andReturn(['total' => 6, 'services' => [['nom' => 'Raccourcir un ourlet', 'nb' => 4], ['nom' => 'Changer une fermeture', 'nb' => 2]]]);

        $this->actingAs($this->compte())
            ->post('/admin/ateliers/mon-assistant', ['question' => 'Quel service est le plus demandé ?'], $this->enAjax())
            ->assertOk()
            ->assertJsonPath('html', fn (string $html) => str_contains($html, 'Sur 6 réparations terminées, la plus demandée est « Raccourcir un ourlet » (4).'));

        // Modèle configuré puis modèle de secours, tous deux refusés.
        Http::assertSentCount(2);
    }

    public function test_sans_cle_et_sans_mot_cle_l_assistant_explique_ce_qu_il_sait_faire(): void
    {
        config(['services.vision.gemini_key' => null]);
        Http::fake();

        $this->actingAs($this->compte())
            ->post('/admin/ateliers/mon-assistant', ['question' => 'Bonjour toi'], $this->enAjax())
            ->assertOk()
            ->assertJsonPath('html', fn (string $html) => str_contains($html, 'Je peux vous aider sur'));
        Http::assertNothingSent();
    }

    public function test_la_saisie_est_controlee_en_php(): void
    {
        Http::fake();

        $this->actingAs($this->compte())->post('/admin/ateliers/mon-assistant', [], $this->enAjax())
            ->assertStatus(422)->assertJsonValidationErrors(['question' => 'Écrivez votre question ou choisissez un raccourci.']);
        $this->actingAs($this->compte())->post('/admin/ateliers/mon-assistant', ['outil' => 'supprimer'], $this->enAjax())
            ->assertStatus(422)->assertJsonValidationErrors(['outil' => 'Ce raccourci est inconnu.']);
        $this->actingAs($this->compte())->post('/admin/ateliers/mon-assistant', ['question' => str_repeat('a', 301)], $this->enAjax())
            ->assertStatus(422)->assertJsonValidationErrors('question');

        Http::assertNothingSent();
    }

    public function test_sans_javascript_la_reponse_s_affiche_sur_la_page(): void
    {
        Http::fake();
        $this->activite->shouldReceive('avis')->once()->andReturn(['moyenne' => 0.0, 'total' => 0, 'derniers' => []]);

        $this->actingAs($this->compte())
            ->post('/admin/ateliers/mon-assistant', ['outil' => 'avis_clients'])
            ->assertOk()
            ->assertSee('Mes avis clients')
            ->assertSee('Aucun avis vérifié pour l&#039;instant', false);
    }
}
