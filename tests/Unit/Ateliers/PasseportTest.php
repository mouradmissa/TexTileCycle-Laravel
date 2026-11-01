<?php

namespace Tests\Unit\Ateliers;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Passeport;
use App\Modules\Ateliers\Services\PasseportService;
use App\Modules\Auth\Models\User;
use App\Modules\RendezVous\Models\RendezVous;
use App\Modules\Vetements\Models\Vetement;
use Carbon\Carbon;
use Tests\TestCase;

class PasseportTest extends TestCase
{
    private function rdv(?string $serviceId = null): RendezVous
    {
        $rdv = new RendezVous([
            'atelier_id' => '652f000000000000000000a1',
            'user_id' => '652f000000000000000000c2',
            'service_id' => $serviceId,
            'statut' => RendezVous::STATUT_TERMINE,
            'date_rdv' => '2026-10-16 10:30',
            'commentaire' => 'Mon numéro : 22 333 444',
        ]);
        $rdv->setRelation('vetement', new Vetement(['type' => 'Jean', 'size' => 'M', 'material' => 'Denim']));
        $rdv->setRelation('user', new User(['name' => 'Karim Trabelsi', 'phone' => '22333444']));
        $rdv->setRelation('service', null);

        return $rdv;
    }

    public function test_l_instantane_ne_contient_que_des_informations_publiques(): void
    {
        $atelier = new Atelier(['nom' => 'Couture Medina', 'ville' => 'Tunis']);

        $rdv = $this->rdv('retouche-simple');
        $rdv->setRelation('service', new \App\Modules\Ateliers\Models\Service(['description' => ' Reprise soignée ', 'duree_estimee' => 90]));

        $instantane = PasseportService::instantane($atelier, $rdv, ['mot_atelier' => '  Coutures renforcées ', 'garantie_jours' => '30']);

        $this->assertSame([
            'mot_atelier' => 'Coutures renforcées',
            'garantie_jours' => 30,
            'service_description' => 'Reprise soignée',
            'service_duree' => 90,
            'vetement' => 'Jean',
            'taille' => 'M',
            'matiere' => 'Denim',
            'reparation' => 'Retouche simple',
            'atelier' => 'Couture Medina',
            'ville' => 'Tunis',
            'repare_le' => '2026-10-16',
        ], $instantane);

        $texte = json_encode($instantane);
        $this->assertStringNotContainsString('Karim', $texte);
        $this->assertStringNotContainsString('22', str_replace('2026-10-16', '', $texte));
    }

    public function test_un_service_supprime_n_affiche_pas_son_identifiant_brut(): void
    {
        $instantane = PasseportService::instantane(new Atelier(['nom' => 'A']), $this->rdv('652f0000000000000000beef'));

        $this->assertSame('', $instantane['reparation']);
        $this->assertSame('', PasseportService::instantane(new Atelier(['nom' => 'A']), $this->rdv())['reparation']);
    }

    public function test_affichages_du_passeport(): void
    {
        $passeport = new Passeport([
            'code' => 'Ab3dEf6hIj9kLm2nOp5qRs',
            'instantane' => ['vetement' => 'Jean', 'taille' => 'M', 'reparation' => '', 'repare_le' => '2026-10-16'],
        ]);

        $this->assertSame('AB3D-EF6H', $passeport->numeroCourt());
        $this->assertSame('Jean · Taille M', $passeport->vetementAffiche());
        $this->assertSame('Réparation', $passeport->reparationAffichee());
        $this->assertSame('16 octobre 2026', $passeport->dateReparationAffichee());
        $this->assertFalse($passeport->estRevoque());

        $passeport->revoque_le = Carbon::now();
        $this->assertTrue($passeport->estRevoque());
    }

    public function test_un_instantane_abime_reste_affichable(): void
    {
        $passeport = new Passeport(['code' => 'x', 'instantane' => 'pas un tableau']);

        $this->assertSame('Vêtement', $passeport->vetementAffiche());
        $this->assertSame('—', $passeport->dateReparationAffichee());
        $this->assertSame('', (new Passeport(['instantane' => ['repare_le' => 'n-importe-quoi']]))->info('atelier'));
        $this->assertSame('—', (new Passeport(['instantane' => ['repare_le' => 'n-importe-quoi']]))->dateReparationAffichee());
    }

    public function test_garantie_et_duree_du_service(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-01 09:00'));

        $passeport = new Passeport(['instantane' => ['repare_le' => '2026-10-16', 'garantie_jours' => 30, 'service_duree' => 90]]);
        $this->assertSame(30, $passeport->garantieJours());
        $this->assertSame('2026-11-15', $passeport->garantieFin()->toDateString());
        $this->assertTrue($passeport->garantieActive());
        $this->assertSame('1 h 30', $passeport->dureeServiceAffichee());

        Carbon::setTestNow(Carbon::parse('2026-11-16 09:00'));
        $this->assertFalse($passeport->garantieActive());

        // Valeur hors liste (instantané abîmé ou modifié à la main) : traitée comme « sans garantie ».
        $bizarre = new Passeport(['instantane' => ['repare_le' => '2026-10-16', 'garantie_jours' => 45, 'service_duree' => 120]]);
        $this->assertSame(0, $bizarre->garantieJours());
        $this->assertNull($bizarre->garantieFin());
        $this->assertSame('2 h', $bizarre->dureeServiceAffichee());
        $this->assertSame('', (new Passeport(['instantane' => []]))->dureeServiceAffichee());
        $this->assertSame('45 min', (new Passeport(['instantane' => ['service_duree' => 45]]))->dureeServiceAffichee());

        Carbon::setTestNow();
    }

    public function test_le_code_respecte_le_format_des_routes(): void
    {
        $this->assertMatchesRegularExpression('/^'.Passeport::FORMAT_CODE.'$/', \Illuminate\Support\Str::random(Passeport::LONGUEUR_CODE));
    }

    public function test_un_rdv_date_d_un_jour_futur_est_a_venir(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-01 09:00'));

        $this->assertTrue(PasseportService::estAVenir(new RendezVous(['date_rdv' => '2026-11-02 08:00'])));
        // Le jour même reste possible : la réparation peut finir avant l'heure prévue.
        $this->assertFalse(PasseportService::estAVenir(new RendezVous(['date_rdv' => '2026-11-01 18:00'])));
        $this->assertFalse(PasseportService::estAVenir(new RendezVous(['date_rdv' => '2026-10-20 10:00'])));
        $this->assertFalse(PasseportService::estAVenir(new RendezVous()));

        Carbon::setTestNow();
    }

    public function test_le_code_est_extrait_d_une_saisie_libre(): void
    {
        $code = 'Ab3dEf6hIj9kLm2nOp5qRs';

        $this->assertSame($code, PasseportService::extraireCode("  {$code} "));
        $this->assertSame($code, PasseportService::extraireCode("https://textilecycle.tn/passeport/{$code}"));
        $this->assertSame($code, PasseportService::extraireCode("http://192.168.1.20:8000/passeport/{$code}?source=qr"));
        $this->assertSame($code, PasseportService::extraireCode("http://127.0.0.1:8000/passeport/{$code}/qr.svg"));
        $this->assertNull(PasseportService::extraireCode('AB3D-EF6H'));
        $this->assertNull(PasseportService::extraireCode($code.'X'));
        $this->assertNull(PasseportService::extraireCode("https://textilecycle.tn/passeport/{$code}X"));
        $this->assertNull(PasseportService::extraireCode(''));
    }

    public function test_le_qr_est_un_svg_avec_correction_d_erreurs(): void
    {
        $passeport = new Passeport(['code' => 'Ab3dEf6hIj9kLm2nOp5qRs']);

        $svg = app(PasseportService::class)->qrSvg($passeport, 200);

        $this->assertStringStartsWith('<?xml', $svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('width="200"', $svg);
        $this->assertStringEndsWith('/passeport/Ab3dEf6hIj9kLm2nOp5qRs', app(PasseportService::class)->url($passeport));
    }
}
