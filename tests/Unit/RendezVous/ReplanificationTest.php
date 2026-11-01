<?php

namespace Tests\Unit\RendezVous;

use App\Modules\RendezVous\Models\RendezVous;
use Tests\TestCase;

class ReplanificationTest extends TestCase
{
    public function test_seul_un_rdv_annule_non_remplace_peut_etre_reprogramme(): void
    {
        $this->assertTrue((new RendezVous(['statut' => RendezVous::STATUT_ANNULE]))->peutEtreReplanifie());
        $this->assertFalse((new RendezVous(['statut' => RendezVous::STATUT_ANNULE, 'replanifie_id' => 'abc']))->peutEtreReplanifie());
        $this->assertFalse((new RendezVous(['statut' => RendezVous::STATUT_CONFIRME]))->peutEtreReplanifie());
    }

    public function test_le_mail_de_remplacement_ne_concerne_que_les_annulations_de_l_atelier(): void
    {
        $this->assertTrue((new RendezVous(['annule_par' => 'atelier']))->annuleParLatelier());
        $this->assertTrue((new RendezVous(['annule_par' => 'admin']))->annuleParLatelier());
        $this->assertFalse((new RendezVous(['annule_par' => 'citoyen']))->annuleParLatelier());
        $this->assertFalse((new RendezVous())->annuleParLatelier());
    }
}
