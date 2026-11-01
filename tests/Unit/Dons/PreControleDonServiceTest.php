<?php

namespace Tests\Unit\Dons;

use App\Modules\Associations\Models\Association;
use App\Modules\Dons\Services\MatchingDonService;
use App\Modules\Dons\Services\PreControleDonService;
use App\Modules\Vetements\Models\Vetement;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PreControleDonServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.vision.gemini_key' => '', 'services.dons.gemini_key' => '']);
    }

    private function service(): PreControleDonService
    {
        return new PreControleDonService(new MatchingDonService());
    }

    private function vetement(array $attributs = []): Vetement
    {
        $vetement = new Vetement($attributs + ['type' => 'Manteau', 'size' => 'S', 'condition_label' => 'Bon état']);
        $vetement->_id = 'v1';

        return $vetement;
    }

    private function association(string $id, string $nom, array $besoins): Association
    {
        $association = new Association(['nom' => $nom, 'besoins' => $besoins]);
        $association->_id = $id;

        return $association;
    }

    private function manteaux(): Association
    {
        return $this->association('a1', 'test', [['type' => 'Manteaux & vestes', 'taille' => 'S', 'quantite' => 10]]);
    }

    public function test_un_don_qui_correspond_est_adapte(): void
    {
        $asso = $this->manteaux();

        $r = $this->service()->verifier($this->vetement(), $asso, collect([$asso]));

        $this->assertSame('adapte', $r['niveau']);
        $this->assertNull($r['alternative']);
        $this->assertFalse($r['photo_analysee']);
    }

    public function test_un_type_non_recherche_est_peu_adapte_et_propose_une_alternative(): void
    {
        $robes = $this->association('a2', 'broudCown', [['type' => 'Robes & jupes', 'taille' => 'S', 'quantite' => 20]]);
        $manteaux = $this->manteaux();

        $r = $this->service()->verifier($this->vetement(), $robes, collect([$robes, $manteaux]));

        $this->assertSame('peu_adapte', $r['niveau']);
        $this->assertSame('a1', $r['alternative']['association_id']);
    }

    public function test_une_photo_qui_contredit_letat_declare_degrade_le_niveau(): void
    {
        $asso = $this->manteaux();
        $vetement = $this->vetement(['analyse_ia' => [
            'disponible' => true, 'est_vetement' => true, 'type' => 'Manteau', 'etat' => 'Abîmé',
            'defauts' => ['trou large', 'tissu déchiré'], 'gravite' => 'severe',
        ]]);

        $r = $this->service()->verifier($vetement, $asso, collect([$asso]));

        $this->assertNotSame('adapte', $r['niveau']);
        $this->assertTrue($r['photo_analysee']);
        $this->assertStringContainsString('trou large', implode(' ', $r['messages']));
    }

    public function test_une_photo_coherente_ne_change_pas_le_verdict(): void
    {
        $asso = $this->manteaux();
        $vetement = $this->vetement(['analyse_ia' => [
            'disponible' => true, 'est_vetement' => true, 'type' => 'Veste', 'etat' => 'Bon état', 'defauts' => [], 'gravite' => 'aucune',
        ]]);

        $this->assertSame('adapte', $this->service()->verifier($vetement, $asso, collect([$asso]))['niveau']);
    }

    public function test_une_photo_qui_nest_pas_un_vetement_est_signalee(): void
    {
        $asso = $this->manteaux();
        $vetement = $this->vetement(['analyse_ia' => ['disponible' => true, 'est_vetement' => false, 'etat' => null]]);

        $r = $this->service()->verifier($vetement, $asso, collect([$asso]));

        $this->assertNotSame('adapte', $r['niveau']);
        $this->assertStringContainsString('ne montre pas clairement', implode(' ', $r['messages']));
    }

    public function test_un_type_different_sur_la_photo_est_signale(): void
    {
        $asso = $this->manteaux();
        $vetement = $this->vetement(['analyse_ia' => [
            'disponible' => true, 'est_vetement' => true, 'type' => 'Robe', 'etat' => 'Bon état', 'defauts' => [],
        ]]);

        $r = $this->service()->verifier($vetement, $asso, collect([$asso]));

        $this->assertStringContainsString('ressemble plutôt à', implode(' ', $r['messages']));
    }

    public function test_un_vetement_declare_abime_ne_peut_pas_etre_adapte(): void
    {
        $asso = $this->association('a1', 'test', [['type' => 'Manteaux & vestes', 'taille' => 'S', 'quantite' => 99]]);

        $r = $this->service()->verifier($this->vetement(['condition_label' => 'Abîmé']), $asso, collect([$asso]));

        $this->assertNotSame('adapte', $r['niveau']);
    }
}
