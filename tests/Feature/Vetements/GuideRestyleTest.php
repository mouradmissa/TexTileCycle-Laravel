<?php

namespace Tests\Feature\Vetements;

use App\Mail\GuideRestyleMail;
use App\Modules\Auth\Models\User;
use App\Modules\Vetements\Models\GuideRestyle;
use App\Modules\Vetements\Services\GuideRestyleService;
use App\Modules\Vetements\Services\PropositionStyleVetementService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\TestCase;

class GuideRestyleTest extends TestCase
{
    use BlocksRemoteMongo;

    private const ID_USER = '652f000000000000000000c9';

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();
        Cache::flush();
        Mail::fake();
        Storage::fake('public');
        config([
            'services.vision.provider' => 'gemini',
            'services.vision.gemini_key' => 'clef-test',
            'services.vision.gemini_model' => 'gemini-3.5-flash-lite',
            'services.vision.openai_key' => '',
            'mail.from.address' => 'noreply@textilecycle.tn',
            'mail.from.name' => 'TexTileCycle',
        ]);
        $this->app->bind(GuideRestyleService::class, function ($app) {
            return new class($app->make(PropositionStyleVetementService::class)) extends GuideRestyleService
            {
                protected function persister(array $attributs): GuideRestyle
                {
                    $guide = new GuideRestyle($attributs);
                    $guide->setAttribute('_id', '652f000000000000000000aa');

                    return $guide;
                }

                protected function marquerEnvoi(GuideRestyle $guide): void
                {
                    $guide->envoye_le = now();
                }
            };
        });
    }

    public function test_un_invite_ne_peut_pas_enregistrer_le_guide(): void
    {
        $this->postJson('/mes-vetements/restyle/guide', [
            'instruction' => 'Reprendre l\'ourlet décousu au fil assorti',
        ])->assertRedirect('/connexion');
    }

    public function test_le_guide_enregistre_la_description_et_les_etats_puis_part_par_email(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'titre' => 'Guide : ourlet repris',
                            'description' => 'Le jean a un ourlet décousu. La réparation reprend le bord au fil assorti, sans changer la coupe ni la couleur.',
                            'etat_avant' => 'Ourlet décousu, fil lâché, denim encore solide.',
                            'etat_apres' => 'Ourlet repris, couture régulière, même jean.',
                        ], JSON_UNESCAPED_UNICODE),
                    ]]],
                ]],
            ], 200),
        ]);

        $instruction = 'Reprendre l\'ourlet décousu au fil assorti';
        $jeton = 'vetement-restyle:abc123';
        Cache::put($jeton, 'data:image/png;base64,'.base64_encode('image-reparee'), now()->addMinutes(20));

        $this->actingAs($this->citoyen())
            ->postJson('/mes-vetements/restyle/guide', [
                'photo' => $this->photo(),
                'instruction' => $instruction,
                'jeton' => $jeton,
            ])
            ->assertOk()
            ->assertJsonPath('envoye', true)
            ->assertJsonPath('email', 'yasmine@example.test')
            ->assertJsonPath('guide.titre', 'Guide : ourlet repris')
            ->assertJsonPath('guide.description', 'Le jean a un ourlet décousu. La réparation reprend le bord au fil assorti, sans changer la coupe ni la couleur.')
            ->assertJsonPath('guide.etat_avant', 'Ourlet décousu, fil lâché, denim encore solide.')
            ->assertJsonPath('guide.etat_apres', 'Ourlet repris, couture régulière, même jean.');

        Mail::assertSent(GuideRestyleMail::class, function (GuideRestyleMail $mail) use ($instruction) {
            return $mail->hasTo('yasmine@example.test')
                && $mail->guide->instruction === $instruction
                && str_contains($mail->guide->description, 'ourlet décousu')
                && $mail->guide->etat_avant !== ''
                && $mail->guide->etat_apres !== ''
                && Storage::disk('public')->exists($mail->guide->image_avant)
                && Storage::disk('public')->exists($mail->guide->image_apres);
        });

        Http::assertSent(function ($request) use ($instruction) {
            $parties = data_get($request->data(), 'contents.0.parts', []);

            return str_contains((string) data_get($parties, '0.text'), $instruction)
                && data_get($parties, '1.inlineData.data')
                && data_get($parties, '2.inlineData.data');
        });
    }

    public function test_sans_modele_le_guide_decrit_quand_meme_la_reparation_et_part(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'quota']], 429),
        ]);

        $instruction = 'Reprendre l\'ourlet décousu au fil assorti';

        $this->actingAs($this->citoyen())
            ->postJson('/mes-vetements/restyle/guide', [
                'photo' => $this->photo(),
                'instruction' => $instruction,
                'image_apres' => 'data:image/png;base64,'.base64_encode('image-reparee'),
            ])
            ->assertOk()
            ->assertJsonPath('envoye', true)
            ->assertJsonPath('guide.titre', 'Guide de réparation');

        Mail::assertSent(GuideRestyleMail::class, function (GuideRestyleMail $mail) use ($instruction) {
            return str_contains($mail->guide->description, $instruction)
                && str_contains($mail->guide->etat_avant, $instruction)
                && $mail->guide->etat_apres !== '';
        });
    }

    public function test_un_jeton_inconnu_est_refuse(): void
    {
        Http::fake();

        $this->actingAs($this->citoyen())
            ->postJson('/mes-vetements/restyle/guide', [
                'photo' => $this->photo(),
                'instruction' => 'Reprendre l\'ourlet décousu au fil assorti',
                'jeton' => 'autre-cache:secret',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ce rendu ne correspond pas à un restyle.');

        Mail::assertNothingSent();
        Http::assertNothingSent();
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
        $chemin = storage_path('framework/testing/vetement-guide.png');
        if (! is_dir(dirname($chemin))) {
            mkdir(dirname($chemin), 0777, true);
        }

        file_put_contents($chemin, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        return new UploadedFile($chemin, 'vetement.png', 'image/png', UPLOAD_ERR_OK, true);
    }
}
