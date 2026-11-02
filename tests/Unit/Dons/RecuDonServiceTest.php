<?php

namespace Tests\Unit\Dons;

use App\Modules\Dons\Models\Don;
use App\Modules\Dons\Services\RecuDonService;
use Tests\TestCase;

class RecuDonServiceTest extends TestCase
{
    private function don(string $statut, string $id = '65f1a2b3c4d5e6f7a8b9c0d1'): Don
    {
        $don = new Don(['statut' => $statut]);
        $don->_id = $id;
        $don->created_at = now()->setDate(2026, 11, 1);

        return $don;
    }

    public function test_le_recu_n_existe_que_pour_un_don_accepte(): void
    {
        $service = new RecuDonService();

        $this->assertTrue($service->disponible($this->don(Don::STATUT_ACCEPTE)));
        $this->assertFalse($service->disponible($this->don(Don::STATUT_EN_ATTENTE)));
        $this->assertFalse($service->disponible($this->don(Don::STATUT_REFUSE)));
    }

    public function test_le_numero_utilise_l_annee_et_la_fin_de_l_identifiant(): void
    {
        $this->assertSame('RC-2026-B9C0D1', (new RecuDonService())->numero($this->don(Don::STATUT_ACCEPTE)));
    }

    public function test_le_lien_de_verification_est_signe_et_sans_donnee_personnelle(): void
    {
        $url = (new RecuDonService())->urlVerification($this->don(Don::STATUT_ACCEPTE));

        $this->assertStringContainsString('/dons/recu/verifier/65f1a2b3c4d5e6f7a8b9c0d1', $url);
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_le_qr_code_est_un_svg(): void
    {
        $svg = (new RecuDonService())->qrSvg($this->don(Don::STATUT_ACCEPTE));

        $this->assertStringContainsString('<svg', $svg);
    }
}
