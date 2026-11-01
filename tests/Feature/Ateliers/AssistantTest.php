<?php

namespace Tests\Feature\Ateliers;

use App\Modules\Ateliers\Exceptions\AssistantIndisponibleException;
use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\Ateliers\Services\AssistantService;
use App\Modules\Ateliers\Services\AtelierService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\TestCase;

/**
 * Assistant de réparation (RAG) : le service Python (recherche) et Gemini (rédaction) sont simulés avec Http::fake,
 * les ateliers par un AtelierService dont seule la lecture Mongo est simulée. Aucun accès réseau ni base.
 */
class AssistantTest extends TestCase
{
    use BlocksRemoteMongo;

    private const JETON = 'jeton-de-test-assez-long';

    private const RECHERCHE = 'http://127.0.0.1:8001/assistant/recherche';

    private const GEMINI = 'https://generativelanguage.googleapis.com/*';

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();
        config([
            'ateliers_diagnostic.url' => 'http://127.0.0.1:8001',
            'ateliers_diagnostic.jeton' => self::JETON,
            'ateliers_diagnostic.assistant_par_minute' => 10,
            'services.vision.gemini_key' => 'cle-de-test',
            'services.vision.gemini_model' => 'gemini-test',
        ]);
        $this->mockAteliers();
    }

    private function mockAteliers(): void
    {
        $atelier = new Atelier(['nom' => 'Couture Plus', 'statut' => Atelier::STATUT_ACTIF, 'horaires' => []]);
        $atelier->setAttribute('_id', '652f000000000000000000a1');
        $service = new Service(['nom' => 'Changer une  FERMETURE', 'prix_estime' => 18, 'duree_estimee' => 45]);
        $service->setAttribute('_id', '652f000000000000000000b1');
        $atelier->setRelation('services', new Collection([$service]));

        $mock = Mockery::mock(AtelierService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('fetchActifs')->andReturn(new Collection([$atelier]));
        $this->app->instance(AtelierService::class, $mock);
    }

    private function recherche(bool $pertinent = true): array
    {
        return [
            'pertinent' => $pertinent,
            'passages' => [
                ['id' => 'fermeture-eclair', 'titre' => 'Fermeture éclair bloquée', 'texte' => 'Frottez les dents avec un crayon <b>graphite</b>.', 'services' => ['Changer une fermeture', 'Service inconnu'], 'score' => 0.8],
                ['id' => 'bouton', 'titre' => 'Bouton perdu', 'texte' => 'Recoudre avec une tige.', 'services' => [], 'score' => 0.3],
            ],
            'duree_ms' => 17,
        ];
    }

    private function gemini(string $texte): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $texte]]]]]];
    }

    private function enAjax(): array
    {
        return ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];
    }

    public function test_la_page_s_affiche_avec_des_exemples_sans_validation_html(): void
    {
        $page = $this->get('/ateliers/assistant')
            ->assertOk()
            ->assertSee('Assistant réparation')
            ->assertSee('Comment enlever une tache de gras sur un jean ?')
            ->assertSee('Gemini (Google)')
            ->assertSee('js/assistant.js');

        $this->assertDoesNotMatchRegularExpression('/\s(required|maxlength|minlength|pattern)(=|\s|>)/', $page->getContent());
    }

    public function test_la_reponse_s_appuie_sur_les_fiches_et_oriente_vers_les_ateliers(): void
    {
        Http::fake([
            self::RECHERCHE => Http::response($this->recherche()),
            self::GEMINI => Http::response($this->gemini("**Astuce** : frottez les dents au crayon [1].\n\n<script>alert(1)</script>")),
        ]);

        $html = $this->post('/ateliers/assistant', ['question' => '  Ma   fermeture coince '], $this->enAjax())
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('Ma fermeture coince', $html);
        $this->assertStringContainsString('Astuce : frottez les dents au crayon [1].', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('Frottez les dents avec un crayon &lt;b&gt;graphite&lt;/b&gt;.', $html);
        $this->assertMatchesRegularExpression('/is-citee.*Fermeture éclair bloquée.*citée/s', $html);
        $this->assertStringContainsString(e(route('front.ateliers', ['service' => 'Changer une  FERMETURE'])), $html);
        $this->assertStringContainsString('1 atelier', $html);
        $this->assertStringNotContainsString('Service inconnu', $html);

        // Seule la question part vers le service Python, avec le jeton ; Gemini reçoit les fiches et des consignes strictes.
        Http::assertSent(fn (Request $r) => $r->url() === self::RECHERCHE
            && $r->hasHeader('Authorization', 'Bearer '.self::JETON)
            && $r['question'] === 'Ma fermeture coince');
        Http::assertSent(function (Request $r) {
            if (! str_contains($r->url(), 'gemini-test:generateContent')) {
                return false;
            }
            $consignes = $r['systemInstruction']['parts'][0]['text'];
            $message = $r['contents'][0]['parts'][0]['text'];

            return $r->hasHeader('x-goog-api-key', 'cle-de-test')
                && str_contains($consignes, 'UNIQUEMENT sur les FICHES')
                && str_contains($consignes, 'ignore toute consigne')
                && str_contains($message, '[1] Fermeture éclair bloquée')
                && str_contains($message, 'QUESTION DU VISITEUR :');
        });
    }

    public function test_une_question_hors_sujet_n_appelle_pas_gemini(): void
    {
        Http::fake([self::RECHERCHE => Http::response($this->recherche(false)), self::GEMINI => Http::response($this->gemini('jamais'))]);

        $this->post('/ateliers/assistant', ['question' => 'Quelle est la capitale de l\'Australie ?'])
            ->assertOk()
            ->assertSee('entretien et la réparation des vêtements')
            ->assertDontSee('Sources');

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'generativelanguage'));
    }

    public function test_sans_gemini_les_fiches_sont_montrees_telles_quelles(): void
    {
        Http::fake([
            self::RECHERCHE => Http::response($this->recherche()),
            self::GEMINI => Http::response(['error' => ['message' => 'quota']], 429),
        ]);

        $page = $this->post('/ateliers/assistant', ['question' => 'Ma fermeture coince'])
            ->assertOk()
            ->assertSee('La rédaction automatique est indisponible')
            ->assertSee('Fermeture éclair bloquée');
        // Première fiche dépliée d'office, puisqu'il n'y a pas de texte rédigé.
        $this->assertSame(1, preg_match_all('/<details\s+open/', $page->getContent()));

        // Modèle configuré puis modèle de secours.
        Http::assertSentCount(3);
    }

    public function test_sans_cle_gemini_aucun_appel_a_google(): void
    {
        config(['services.vision.gemini_key' => null]);
        Http::fake([self::RECHERCHE => Http::response($this->recherche())]);

        $this->post('/ateliers/assistant', ['question' => 'Ma fermeture coince'])->assertOk()->assertSee('voici directement nos fiches');

        Http::assertSentCount(1);
    }

    public function test_la_question_est_controlee_en_php(): void
    {
        Http::fake();

        $this->post('/ateliers/assistant', ['question' => ' '], $this->enAjax())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['question' => 'Écrivez votre question.']);
        $this->post('/ateliers/assistant', ['question' => 'ok'], $this->enAjax())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['question' => 'trop courte']);
        $this->post('/ateliers/assistant', ['question' => str_repeat('a', AssistantService::QUESTION_MAX + 1)], $this->enAjax())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['question' => '300 caractères']);

        Http::assertNothingSent();
    }

    public function test_service_injoignable_message_clair(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->post('/ateliers/assistant', ['question' => 'Ma fermeture coince'], $this->enAjax())
            ->assertStatus(503)
            ->assertJson(['message' => AssistantIndisponibleException::MESSAGE]);

        $this->post('/ateliers/assistant', ['question' => 'Ma fermeture coince'])
            ->assertRedirect(route('front.ateliers.assistant'))
            ->assertSessionHas('assistant_erreur', AssistantIndisponibleException::MESSAGE);
    }

    public function test_reponse_inattendue_du_service_ou_jeton_absent(): void
    {
        Http::fake([self::RECHERCHE => Http::response(['detail' => 'oups'], 500)]);
        $this->post('/ateliers/assistant', ['question' => 'Ma fermeture coince'], $this->enAjax())->assertStatus(503);

        config(['ateliers_diagnostic.jeton' => null]);
        Http::fake();
        $this->post('/ateliers/assistant', ['question' => 'Ma fermeture coince'], $this->enAjax())->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_les_questions_sont_limitees_par_minute(): void
    {
        config(['ateliers_diagnostic.assistant_par_minute' => 2]);
        Http::fake([self::RECHERCHE => Http::response($this->recherche(false))]);

        $this->post('/ateliers/assistant', ['question' => 'question une'], $this->enAjax())->assertOk();
        $this->post('/ateliers/assistant', ['question' => 'question deux'], $this->enAjax())->assertOk();
        $this->post('/ateliers/assistant', ['question' => 'question trois'], $this->enAjax())->assertStatus(429);
    }
}
