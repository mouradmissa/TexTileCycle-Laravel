<?php

namespace Tests\Unit\Dons;

use App\Modules\Associations\Models\Association;
use App\Modules\Dons\Services\MatchingDonService;
use App\Modules\Vetements\Models\Vetement;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MatchingDonServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.vision.gemini_key' => '', 'services.dons.gemini_key' => '']);
    }

    private function vetement(array $attributs = []): Vetement
    {
        return new Vetement($attributs + [
            'type' => 'Pull',
            'size' => 'L',
            'condition_label' => 'Bon état',
        ]);
    }

    private function association(string $id, string $nom, array $besoins): Association
    {
        $association = new Association(['nom' => $nom, 'besoins' => $besoins]);
        $association->_id = $id;

        return $association;
    }

    public function test_le_besoin_qui_correspond_au_type_et_a_la_taille_obtient_le_meilleur_score(): void
    {
        $service = new MatchingDonService();
        $ideale = $this->association('a1', 'Solidarité Mode', [['type' => 'Pulls & sweats', 'taille' => 'L', 'quantite' => 6]]);
        $hors = $this->association('a2', 'Chaussures Pour Tous', [['type' => 'Chaussures', 'taille' => 'L', 'quantite' => 6]]);

        $classement = $service->classer($this->vetement(), [$hors, $ideale]);

        $this->assertSame('a1', $classement[0]['association_id']);
        $this->assertGreaterThanOrEqual(90, $classement[0]['score']);
        $this->assertLessThan(40, $classement[1]['score']);
    }

    public function test_une_mauvaise_taille_fait_baisser_le_score(): void
    {
        $service = new MatchingDonService();
        $bonne = $this->association('a1', 'A', [['type' => 'Pulls & sweats', 'taille' => 'L', 'quantite' => 2]]);
        $mauvaise = $this->association('a2', 'B', [['type' => 'Pulls & sweats', 'taille' => 'S', 'quantite' => 2]]);

        $this->assertGreaterThan(
            $service->evaluer($this->vetement(), $mauvaise)['score'],
            $service->evaluer($this->vetement(), $bonne)['score']
        );
    }

    public function test_un_vetement_abime_est_moins_bien_note(): void
    {
        $service = new MatchingDonService();
        $asso = $this->association('a1', 'A', [['type' => 'Pulls & sweats', 'taille' => 'L', 'quantite' => 2]]);

        $this->assertGreaterThan(
            $service->evaluer($this->vetement(['condition_label' => 'Abîmé']), $asso)['score'],
            $service->evaluer($this->vetement(['condition_label' => 'Excellent']), $asso)['score']
        );
    }

    public function test_une_association_sans_besoin_a_un_score_faible_et_ne_plante_pas(): void
    {
        $resultat = (new MatchingDonService())->evaluer($this->vetement(), $this->association('a1', 'Vide', []));

        $this->assertLessThan(30, $resultat['score']);
        $this->assertNotEmpty($resultat['raisons']);
    }

    public function test_le_score_reste_entre_0_et_100(): void
    {
        $service = new MatchingDonService();
        $asso = $this->association('a1', 'A', [['type' => 'Pulls & sweats', 'taille' => 'Toutes tailles', 'quantite' => 99]]);

        $score = $service->evaluer($this->vetement(['condition_label' => 'Excellent']), $asso)['score'];

        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function test_le_top_trois_utilise_la_phrase_gemini_quand_elle_est_disponible(): void
    {
        config(['services.dons.gemini_key' => 'clef']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['a1' => 'Parfait pour leurs pulls taille L.'])]]]]],
        ])]);
        $asso = $this->association('a1', 'Solidarité Mode', [['type' => 'Pulls & sweats', 'taille' => 'L', 'quantite' => 3]]);

        $top = (new MatchingDonService())->topTrois($this->vetement(), collect([$asso]));

        $this->assertSame('Parfait pour leurs pulls taille L.', $top[0]['phrase']);
    }

    public function test_le_top_trois_retombe_sur_la_phrase_locale_si_gemini_echoue(): void
    {
        config(['services.dons.gemini_key' => 'clef']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 503)]);
        $asso = $this->association('a1', 'Solidarité Mode', [['type' => 'Pulls & sweats', 'taille' => 'L', 'quantite' => 3]]);

        $top = (new MatchingDonService())->topTrois($this->vetement(), collect([$asso]));

        $this->assertStringContainsString('Solidarité Mode', $top[0]['phrase']);
        $this->assertStringContainsString('%', $top[0]['phrase']);
    }

    public function test_la_cle_dediee_aux_dons_est_utilisee_en_priorite(): void
    {
        config(['services.vision.gemini_key' => 'cle-partagee', 'services.dons.gemini_key' => 'cle-dons']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 503)]);
        $asso = $this->association('a1', 'A', [['type' => 'Pulls & sweats', 'taille' => 'L', 'quantite' => 3]]);

        (new MatchingDonService())->topTrois($this->vetement(), collect([$asso]));

        Http::assertSent(fn ($request) => $request->header('x-goog-api-key') === ['cle-dons']);
    }

    public function test_la_cle_partagee_nest_jamais_utilisee(): void
    {
        config(['services.vision.gemini_key' => 'cle-partagee', 'services.dons.gemini_key' => '']);
        Http::fake();
        $asso = $this->association('a1', 'A', [['type' => 'Pulls & sweats', 'taille' => 'L', 'quantite' => 3]]);

        $top = (new MatchingDonService())->topTrois($this->vetement(), collect([$asso]));

        Http::assertNothingSent();
        $this->assertStringContainsString('%', $top[0]['phrase']);
    }
}
