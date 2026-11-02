<?php

namespace Tests\Unit\RendezVous;

use App\Modules\Ateliers\Models\Service;
use App\Modules\RendezVous\Services\SuggestionCreneauService;
use App\Modules\Vetements\Models\Vetement;
use Tests\TestCase;

class SuggestionCreneauTest extends TestCase
{
    private SuggestionCreneauService $creneaux;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creneaux = new SuggestionCreneauService();
    }

    public function test_la_duree_est_arrondie_a_une_option_du_formulaire(): void
    {
        $this->assertSame(15, $this->creneaux->arrondirDuree(10));
        $this->assertSame(45, $this->creneaux->arrondirDuree(38));
        $this->assertSame(90, $this->creneaux->arrondirDuree(75));
        $this->assertSame(480, $this->creneaux->arrondirDuree(900));
    }

    public function test_sans_service_ni_vetement_la_duree_par_defaut_est_30_minutes(): void
    {
        $this->assertSame(30, $this->creneaux->estimerDuree(null, null)['minutes']);
    }

    public function test_une_piece_abimee_en_denim_demande_plus_de_temps(): void
    {
        $service = new Service(['nom' => 'Retouche denim', 'duree_estimee' => 60]);
        $vetement = new Vetement(['condition_label' => 'Abîmé', 'material' => 'Denim']);

        $estimation = $this->creneaux->estimerDuree($service, $vetement);

        // 60 min × 1,5 = 90, + 15 min de matière épaisse = 105, arrondi à 120.
        $this->assertSame(120, $estimation['minutes']);
        $this->assertCount(3, $estimation['raisons']);
    }

    public function test_un_commentaire_avec_plusieurs_trous_allonge_la_duree(): void
    {
        $service = new Service(['nom' => 'Recoudre une déchirure', 'duree_estimee' => 20]);

        $this->assertSame(30, $this->creneaux->estimerDuree($service, null)['minutes']);
        $this->assertSame(45, $this->creneaux->estimerDuree($service, null, 'Il y a plusieurs trous')['minutes']);
    }

    public function test_libelle_de_duree(): void
    {
        $this->assertSame('45 min', SuggestionCreneauService::libelleDuree(45));
        $this->assertSame('2h', SuggestionCreneauService::libelleDuree(120));
        $this->assertSame('1h30', SuggestionCreneauService::libelleDuree(90));
    }
}
