<?php

namespace Tests\Feature\Vetements;

use App\Modules\Auth\Models\User;
use App\Modules\Vetements\Models\Vetement;
use App\Modules\Vetements\Services\VetementService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\TestCase;

class RestyleVetementTest extends TestCase
{
    use BlocksRemoteMongo;

    private const ID_USER = '652f000000000000000000c9';

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();
        Cache::flush();
        config([
            'services.vision.provider' => 'gemini',
            'services.vision.gemini_key' => 'clef-test',
            'services.vision.gemini_model' => 'gemini-3.5-flash-lite',
            'services.vision.openai_key' => '',
        ]);
    }

    public function test_un_invite_ne_peut_pas_restyler(): void
    {
        $this->postJson('/mes-vetements/restyle', [
            'instruction' => 'Reprendre l\'ourlet décousu au fil assorti',
        ])->assertRedirect('/connexion');
    }

    public function test_sans_cle_le_restyle_est_indisponible(): void
    {
        config(['services.vision.gemini_key' => '']);

        $this->actingAs($this->citoyen())
            ->postJson('/mes-vetements/restyle', [
                'photo' => $this->photo(),
                'instruction' => 'Reprendre l\'ourlet décousu au fil assorti',
            ])
            ->assertStatus(503)
            ->assertJsonPath('disponible', false);

        Http::assertNothingSent();
    }

    public function test_la_photo_part_avec_l_instruction_et_revient_en_image(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'inlineData' => [
                            'mimeType' => 'image/png',
                            'data' => base64_encode('image-reparee'),
                        ],
                    ]]],
                ]],
            ], 200),
        ]);

        $instruction = 'Reprendre l\'ourlet décousu au fil assorti';

        $reponse = $this->actingAs($this->citoyen())
            ->postJson('/mes-vetements/restyle', [
                'photo' => $this->photo(),
                'instruction' => $instruction,
            ]);

        $reponse->assertOk()
            ->assertJsonPath('instruction', $instruction)
            ->assertJsonPath('image', 'data:image/png;base64,'.base64_encode('image-reparee'));
        $this->assertMatchesRegularExpression('/^vetement-restyle:[a-f0-9]{64}$/', (string) $reponse->json('jeton'));

        Http::assertSent(function ($request) use ($instruction) {
            $texte = data_get($request->data(), 'contents.0.parts.0.text');
            $photo = data_get($request->data(), 'contents.0.parts.1.inlineData.data');

            return is_string($texte)
                && str_contains($texte, $instruction)
                && str_contains($texte, 'Conserver la forme')
                && is_string($photo)
                && $photo !== '';
        });
    }

    public function test_sans_quota_gemini_le_generateur_gratuit_reprend_la_photo(): void
    {
        $image = 'data:image/webp;base64,'.base64_encode('image-gratuite');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'quota']], 429),
            'stablehorde.net/*' => Http::sequence()
                ->push(['id' => 'job-gratuit', 'kudos' => 1], 202)
                ->push([
                    'done' => true,
                    'faulted' => false,
                    'wait_time' => 0,
                    'generations' => [[
                        'img' => $image,
                    ]],
                ], 200),
        ]);

        $instruction = 'Reprendre l\'ourlet décousu au fil assorti';

        $this->actingAs($this->citoyen())
            ->postJson('/mes-vetements/restyle', [
                'photo' => $this->photo(),
                'instruction' => $instruction,
            ])
            ->assertOk()
            ->assertJsonPath('instruction', $instruction)
            ->assertJsonPath('image', $image);

        Http::assertSent(function ($request) use ($instruction) {
            if (! str_contains($request->url(), 'stablehorde.net/api/v2/generate/async')) {
                return false;
            }

            return data_get($request->data(), 'source_processing') === 'img2img'
                && data_get($request->data(), 'censor_nsfw') === false
                && is_string(data_get($request->data(), 'source_image'))
                && str_contains((string) data_get($request->data(), 'prompt'), $instruction);
        });
    }

    public function test_une_image_censuree_n_est_pas_affichee_comme_reparation(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'quota']], 429),
            'stablehorde.net/*' => Http::sequence()
                ->push(['id' => 'job-censure', 'kudos' => 1], 202)
                ->push([
                    'done' => true,
                    'faulted' => false,
                    'wait_time' => 0,
                    'generations' => [[
                        'img' => 'data:image/png;base64,aW1hZ2UtY2Vuc3VyZWU=',
                        'censored' => true,
                    ]],
                ], 200),
        ]);

        $this->actingAs($this->citoyen())
            ->postJson('/mes-vetements/restyle', [
                'photo' => $this->photo(),
                'instruction' => 'Reprendre l\'ourlet décousu au fil assorti',
            ])
            ->assertStatus(502)
            ->assertJsonPath('message', 'Le générateur a bloqué le rendu de cette photo. Recadrez le vêtement seul, sans visage ni mains, puis réessayez.');
    }

    public function test_la_page_propose_le_restyle(): void
    {
        $vetement = new Vetement([
            'type' => 'Veste',
            'size' => 'M',
            'status' => Vetement::STATUS_EN_ATTENTE,
            'intended_action' => Vetement::ACTION_REPARATION,
            'image_path' => 'vetements/veste.jpg',
        ]);
        $vetement->setAttribute('_id', '652f000000000000000000e9');
        $vetement->setRelation('cycleEvents', new Collection());

        $service = Mockery::mock(VetementService::class);
        $service->shouldReceive('listActifsForOwner')->once()->andReturn(collect([$vetement]));
        $this->app->instance(VetementService::class, $service);

        $this->actingAs($this->citoyen())
            ->get('/mes-vetements')
            ->assertOk()
            ->assertSee('Restyle')
            ->assertSee('Voir la réparation', false)
            ->assertSee('Enregistrer le guide', false)
            ->assertSee('/mes-vetements/restyle/guide', false)
            ->assertSee('/mes-vetements/restyle', false)
            ->assertSee('restyle-vetement.js', false)
            ->assertSee('/mes-vetements/652f000000000000000000e9/restyle', false);
    }

    private function citoyen(): User
    {
        $user = new User([
            'name' => 'Yasmine Ben Ali',
            'email' => 'yasmine@example.test',
            'role' => User::ROLE_CITOYEN,
            'is_active' => true,
        ]);
        $user->setAttribute('_id', self::ID_USER);

        return $user;
    }

    private function photo(): UploadedFile
    {
        $chemin = storage_path('framework/testing/vetement.png');
        if (! is_dir(dirname($chemin))) {
            mkdir(dirname($chemin), 0777, true);
        }

        file_put_contents($chemin, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        return new UploadedFile($chemin, 'vetement.png', 'image/png', UPLOAD_ERR_OK, true);
    }
}
