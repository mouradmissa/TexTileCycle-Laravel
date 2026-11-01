<?php

namespace Tests\Feature\Ateliers;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Passeport;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\Ateliers\Services\PasseportService;
use App\Modules\Auth\Models\User;
use App\Modules\RendezVous\Models\RendezVous;
use App\Modules\Vetements\Models\Vetement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Mockery;
use Mockery\MockInterface;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\TestCase;

/**
 * Passeports de réparation : espace atelier, page publique (QR), « Mes passeports » et administration.
 * AtelierService et PasseportService sont simulés (sauf le dessin du QR) : aucune lecture ni écriture sur Atlas.
 */
class PasseportTest extends TestCase
{
    use BlocksRemoteMongo;

    private const ID_USER_ATELIER = '652f000000000000000000c1';

    private const ID_CLIENT = '652f000000000000000000c2';

    private const ID_ATELIER = '652f000000000000000000a1';

    private const ID_RDV = '652f000000000000000000f1';

    private const ID_PASSEPORT = '652f000000000000000000e1';

    private const CODE = 'Ab3dEf6hIj9kLm2nOp5qRs';

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role, string $id, string $nom = 'Samia Ben Ali'): User
    {
        $user = new User(['name' => $nom, 'email' => $role.'@example.test', 'role' => $role, 'is_active' => true]);
        $user->setAttribute('_id', $id);

        return $user;
    }

    private function atelier(): Atelier
    {
        $atelier = new Atelier(['nom' => 'Couture Medina', 'ville' => 'Tunis', 'statut' => Atelier::STATUT_ACTIF]);
        $atelier->setAttribute('_id', self::ID_ATELIER);
        $atelier->user_id = self::ID_USER_ATELIER;

        return $atelier->setRelation('services', new Collection());
    }

    private function passeport(array $attributs = []): Passeport
    {
        $passeport = new Passeport(array_merge([
            'code' => self::CODE,
            'atelier_id' => self::ID_ATELIER,
            'rendez_vous_id' => self::ID_RDV,
            'vetement_id' => '652f000000000000000000b1',
            'user_id' => self::ID_CLIENT,
            'instantane' => [
                'vetement' => 'Jean <script>alert(1)</script>',
                'taille' => 'M',
                'matiere' => 'Denim',
                'reparation' => 'Changer une fermeture',
                'atelier' => 'Couture Medina',
                'ville' => 'Tunis',
                'repare_le' => '2026-10-16',
                'mot_atelier' => 'Fermeture <b>YKK</b> remplacée',
                'garantie_jours' => 30,
                'service_description' => 'Remplacement de la fermeture éclair',
                'service_duree' => 45,
            ],
            'revoque_le' => null,
        ], $attributs));
        $passeport->setAttribute('_id', self::ID_PASSEPORT);
        $passeport->created_at = Carbon::parse('2026-10-16 18:00');

        return $passeport;
    }

    private function rdvTermine(): RendezVous
    {
        $rdv = new RendezVous(['atelier_id' => self::ID_ATELIER, 'statut' => RendezVous::STATUT_TERMINE, 'duree' => 30, 'date_rdv' => '2026-10-16 10:00']);
        $rdv->setAttribute('_id', self::ID_RDV);
        $rdv->setRelation('vetement', new Vetement(['type' => 'Jean', 'size' => 'M']));
        $rdv->setRelation('user', $this->user(User::ROLE_CITOYEN, self::ID_CLIENT, 'Karim Trabelsi'));
        $rdv->setRelation('service', null);

        return $rdv;
    }

    private function mockAteliers(?Atelier $atelier): void
    {
        $mock = Mockery::mock(AtelierService::class)->makePartial();
        $mock->shouldReceive('findOwnedByUser')->andReturn($atelier);
        $this->app->instance(AtelierService::class, $mock);
    }

    /**
     * @return PasseportService&MockInterface
     */
    private function mockPasseports(): PasseportService
    {
        $mock = Mockery::mock(PasseportService::class)->makePartial();
        $this->app->instance(PasseportService::class, $mock);

        return $mock;
    }

    /* --------------------------------------------------------------- Espace atelier */

    public function test_l_atelier_voit_ses_reparations_terminees_et_peut_generer_un_passeport(): void
    {
        $this->mockAteliers($this->atelier());
        $avecPasseport = $this->rdvTermine();
        $sansPasseport = $this->rdvTermine();
        $sansPasseport->setAttribute('_id', '652f000000000000000000f2');
        $enCours = $this->rdvTermine();
        $enCours->statut = RendezVous::STATUT_CONFIRME;
        $enCours->setRelation('vetement', new Vetement(['type' => 'Veste <b>lin</b>', 'size' => 'L']));
        $passeports = $this->mockPasseports();
        $passeports->shouldReceive('reparationsDeLatelier')->andReturn(collect([
            ['rdv' => $avecPasseport, 'passeport' => $this->passeport()],
            ['rdv' => $sansPasseport, 'passeport' => null],
        ]));
        $passeports->shouldReceive('reparationsEnCours')->andReturn(new Collection([$enCours]));

        $response = $this->actingAs($this->user(User::ROLE_ATELIER, self::ID_USER_ATELIER))
            ->get('/admin/ateliers/mes-passeports');

        $response->assertOk()
            ->assertSee('Passeports de réparation')
            ->assertSee('aria-current="page"', false)
            ->assertSee('Karim Trabelsi')
            ->assertSee('AB3D-EF6H')
            ->assertSee('Non généré')
            ->assertSee('name="rendez_vous_id" value="652f000000000000000000f2"', false)
            ->assertSee('name="mot_atelier"', false)
            ->assertSee('<option value="30" >30 jours</option>', false)
            ->assertSee(route('back.ateliers.passeports.etiquette', ['code' => self::CODE]), false)
            ->assertSee('1</strong> / 2 certifiées', false)
            ->assertSee('Réparations en cours (1)')
            ->assertSee('Veste &lt;b&gt;lin&lt;/b&gt; · Taille L', false)
            ->assertSee(route('back.rdv', ['statut' => RendezVous::STATUT_CONFIRME]), false);

        // Saisie contrôlée en PHP uniquement.
        $this->assertDoesNotMatchRegularExpression('/\s(required|maxlength|minlength|pattern)(=|\s|>)/', $response->getContent());
    }

    public function test_les_reparations_en_cours_s_affichent_meme_sans_reparation_terminee(): void
    {
        $this->mockAteliers($this->atelier());
        $enCours = $this->rdvTermine();
        $enCours->statut = RendezVous::STATUT_CONFIRME;
        $passeports = $this->mockPasseports();
        $passeports->shouldReceive('reparationsDeLatelier')->andReturn(collect());
        $passeports->shouldReceive('reparationsEnCours')->andReturn(new Collection([$enCours]));

        $this->actingAs($this->user(User::ROLE_ATELIER, self::ID_USER_ATELIER))
            ->get('/admin/ateliers/mes-passeports')
            ->assertOk()
            ->assertSee("Aucune réparation terminée pour l'instant", false)
            ->assertSee('Réparations en cours (1)')
            ->assertSee('En réparation');
    }

    public function test_l_espace_passeports_est_reserve_au_role_atelier(): void
    {
        $this->mockAteliers($this->atelier());
        $this->mockPasseports()->shouldNotReceive('reparationsDeLatelier', 'generer');

        $this->actingAs($this->user(User::ROLE_ADMIN, '652f000000000000000000d1'))
            ->get('/admin/ateliers/mes-passeports')
            ->assertForbidden();
    }

    public function test_generer_un_passeport(): void
    {
        $atelier = $this->atelier();
        $this->mockAteliers($atelier);
        $this->mockPasseports()->shouldReceive('generer')
            ->once()
            ->withArgs(fn (Atelier $a, string $rdvId, array $complements) => (string) $a->getKey() === self::ID_ATELIER
                && $rdvId === self::ID_RDV
                && $complements === ['mot_atelier' => 'Fermeture YKK remplacée', 'garantie_jours' => '30'])
            ->andReturn($this->passeport());

        $this->actingAs($this->user(User::ROLE_ATELIER, self::ID_USER_ATELIER))
            ->post('/admin/ateliers/mes-passeports', [
                'rendez_vous_id' => self::ID_RDV,
                'mot_atelier' => '  Fermeture YKK remplacée  ',
                'garantie_jours' => '30',
                'instantane' => 'piratage',
            ])
            ->assertRedirect(route('back.ateliers.passeports'))
            ->assertSessionHas('success', 'Passeport AB3D-EF6H prêt pour « Jean <script>alert(1)</script> · Taille M » : imprimez l\'étiquette et cousez-la sur la pièce.');
    }

    public function test_le_mot_et_la_garantie_sont_controles_en_php(): void
    {
        $this->mockAteliers($this->atelier());
        $this->mockPasseports()->shouldNotReceive('generer');

        $this->actingAs($this->user(User::ROLE_ATELIER, self::ID_USER_ATELIER))
            ->post('/admin/ateliers/mes-passeports', [
                'rendez_vous_id' => self::ID_RDV,
                'mot_atelier' => str_repeat('a', 301),
                'garantie_jours' => '45',
            ])
            ->assertSessionHasErrors([
                'mot_atelier' => 'Le mot sur la réparation ne doit pas dépasser 300 caractères.',
                'garantie_jours' => 'Choisissez une garantie dans la liste.',
            ]);
    }

    public function test_generer_refuse_un_identifiant_invalide_et_un_rendez_vous_d_un_autre_atelier(): void
    {
        $this->mockAteliers($this->atelier());
        $passeports = $this->mockPasseports();
        $passeports->shouldReceive('generer')->once()->andThrow(new ModelNotFoundException());

        $this->actingAs($this->user(User::ROLE_ATELIER, self::ID_USER_ATELIER))
            ->post('/admin/ateliers/mes-passeports', ['rendez_vous_id' => 'pas-un-id'])
            ->assertSessionHasErrors(['rendez_vous_id' => 'Cette réparation est introuvable.']);

        $this->actingAs($this->user(User::ROLE_ATELIER, self::ID_USER_ATELIER))
            ->post('/admin/ateliers/mes-passeports', ['rendez_vous_id' => '652f000000000000000000f9'])
            ->assertNotFound();
    }

    public function test_l_etiquette_s_imprime_et_reste_privee_a_l_atelier(): void
    {
        $this->mockAteliers($this->atelier());
        $passeports = $this->mockPasseports();
        $passeports->shouldReceive('trouverParCode')->with(self::CODE)->andReturn($this->passeport());

        $this->actingAs($this->user(User::ROLE_ATELIER, self::ID_USER_ATELIER))
            ->get('/admin/ateliers/mes-passeports/'.self::CODE.'/etiquette')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('src="'.route('front.passeport.qr', ['code' => self::CODE]).'"', false)
            ->assertSee('AB3D-EF6H')
            ->assertSee('Jean &lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('passeport-etiquette.js', false);

        $passeports->shouldReceive('trouverParCode')->with('Zz3dEf6hIj9kLm2nOp5qRs')->andReturn($this->passeport(['atelier_id' => '652f000000000000000000a9']));

        $this->actingAs($this->user(User::ROLE_ATELIER, self::ID_USER_ATELIER))
            ->get('/admin/ateliers/mes-passeports/Zz3dEf6hIj9kLm2nOp5qRs/etiquette')
            ->assertNotFound();
    }

    /* --------------------------------------------------------------- Page publique */

    public function test_la_page_publique_montre_l_historique_sans_donnee_personnelle(): void
    {
        $passeports = $this->mockPasseports();
        $actuel = $this->passeport();
        $ancien = $this->passeport(['code' => 'Old3dEf6hIj9kLm2nOp5qR', 'instantane' => ['vetement' => 'Jean', 'reparation' => 'Ourlet', 'atelier' => 'Fil & Aiguille', 'repare_le' => '2025-03-02']]);
        $ancien->setAttribute('_id', '652f000000000000000000e0');
        $passeports->shouldReceive('trouverParCode')->with(self::CODE)->andReturn($actuel);
        $passeports->shouldReceive('historique')->andReturn(collect([$ancien, $actuel]));
        $atelierActif = $this->atelier();
        $atelierActif->note_moyenne = 4.6;
        $atelierActif->nb_avis = 12;
        $passeports->shouldReceive('ateliersDe')->andReturn(collect([self::ID_ATELIER => $atelierActif]));
        Carbon::setTestNow(Carbon::parse('2026-10-17 12:00'));

        $this->get('/passeport/'.self::CODE)
            ->assertOk()
            ->assertSee('Remplacement de la fermeture éclair')
            ->assertSee('45 min')
            ->assertSee('Fermeture &lt;b&gt;YKK&lt;/b&gt; remplacée', false)
            ->assertSee('4,6')
            ->assertSee('12 avis')
            ->assertSee('Garantie 30 jours')
            ->assertSee('valable jusqu&#039;au', false)
            ->assertSee('15 novembre 2026')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('Pièce réparée et certifiée')
            ->assertSee('AB3D-EF6H')
            ->assertSee('Jean &lt;script&gt;alert(1)&lt;/script&gt; · Taille M', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Denim')
            ->assertSee('2 réparations certifiées')
            ->assertSee('2 mars 2025')
            ->assertSee('16 octobre 2026')
            ->assertSee('Fil &amp; Aiguille', false)
            ->assertSee('Ce passeport')
            ->assertSee(route('front.ateliers.show', ['id' => self::ID_ATELIER]), false)
            ->assertDontSee(self::ID_CLIENT)
            ->assertDontSee(self::ID_RDV);
    }

    public function test_un_passeport_revoque_n_affiche_plus_l_historique(): void
    {
        $passeports = $this->mockPasseports();
        $passeports->shouldReceive('trouverParCode')->andReturn($this->passeport(['revoque_le' => Carbon::parse('2026-10-17')]));
        $passeports->shouldNotReceive('historique');

        $this->get('/passeport/'.self::CODE)
            ->assertOk()
            ->assertSee('Passeport révoqué')
            ->assertSee("n'est plus valide", false)
            ->assertSee('17 octobre 2026')
            ->assertDontSee('Historique des réparations');
    }

    public function test_un_code_inconnu_ou_mal_forme_donne_une_404(): void
    {
        $passeports = $this->mockPasseports();
        $passeports->shouldReceive('trouverParCode')->andReturnNull();

        $this->get('/passeport/'.self::CODE)->assertNotFound();
        $this->get('/passeport/trop-court')->assertNotFound();
        $this->get('/passeport/'.self::CODE.'/qr.svg')->assertNotFound();
    }

    public function test_le_qr_est_une_image_svg_qui_pointe_vers_la_page_publique(): void
    {
        $this->mockPasseports()->shouldReceive('trouverParCode')->with(self::CODE)->andReturn($this->passeport());

        $response = $this->get('/passeport/'.self::CODE.'/qr.svg');

        $response->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $response->getContent());
        $this->assertStringContainsString('max-age=86400', $response->headers->get('Cache-Control'));
    }

    public function test_les_pages_publiques_sont_limitees_par_minute(): void
    {
        $this->mockPasseports()->shouldReceive('trouverParCode')->andReturnNull();

        for ($i = 0; $i < 60; $i++) {
            $this->get('/passeport/'.self::CODE)->assertNotFound();
        }

        $this->get('/passeport/'.self::CODE)->assertStatus(429);
    }

    /* --------------------------------------------------------------- Client */

    public function test_mes_passeports_demande_une_connexion(): void
    {
        $this->mockPasseports()->shouldNotReceive('pourClient');

        $this->get('/mes-passeports')->assertRedirect(route('front.login'));
    }

    public function test_le_client_voit_ses_passeports_et_peut_telecharger_le_qr(): void
    {
        $client = $this->user(User::ROLE_CITOYEN, self::ID_CLIENT, 'Karim Trabelsi');
        $this->mockPasseports()->shouldReceive('pourClient')
            ->once()
            ->withArgs(fn (User $user) => (string) $user->getKey() === self::ID_CLIENT)
            ->andReturn(new Collection([$this->passeport(), $this->passeport(['revoque_le' => Carbon::now()])]));

        $this->actingAs($client)
            ->get('/mes-passeports')
            ->assertOk()
            ->assertSee('Mes passeports de réparation')
            ->assertSee('Changer une fermeture · Couture Medina')
            ->assertSee('download="passeport-AB3D-EF6H.svg"', false)
            ->assertSee('Révoqué')
            ->assertSee('href="'.route('front.passeports').'"', false);
    }

    public function test_un_client_sans_passeport_voit_un_etat_vide(): void
    {
        $this->mockPasseports()->shouldReceive('pourClient')->andReturn(new Collection());

        $this->actingAs($this->user(User::ROLE_CITOYEN, self::ID_CLIENT))
            ->get('/mes-passeports')
            ->assertOk()
            ->assertSee("Aucun passeport pour l'instant", false);
    }

    /* --------------------------------------------------------------- Administration */

    public function test_l_admin_liste_les_passeports(): void
    {
        $this->mockPasseports()->shouldReceive('pourAdmin')->andReturn(new Collection([
            $this->passeport(),
            $this->passeport(['revoque_le' => Carbon::now()]),
        ]));

        $this->actingAs($this->user(User::ROLE_ADMIN, '652f000000000000000000d1'))
            ->get('/admin/ateliers/passeports')
            ->assertOk()
            ->assertSee('AB3D-EF6H')
            ->assertSee('1 révoqué')
            ->assertSee('Rétablir')
            ->assertSee('Révoquer')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_l_admin_revoque_et_retablit(): void
    {
        $passeports = $this->mockPasseports();
        $passeports->shouldReceive('changerRevocation')->once()->with(self::ID_PASSEPORT, true)
            ->andReturn($this->passeport(['revoque_le' => Carbon::now()]));
        $passeports->shouldReceive('changerRevocation')->once()->with(self::ID_PASSEPORT, false)
            ->andReturn($this->passeport());

        $admin = $this->user(User::ROLE_ADMIN, '652f000000000000000000d1');

        $this->actingAs($admin)
            ->patch('/admin/ateliers/passeports/'.self::ID_PASSEPORT.'/revocation', ['revoquer' => '1'])
            ->assertRedirect(route('back.ateliers.passeports.admin'))
            ->assertSessionHas('success', 'Passeport AB3D-EF6H révoqué : sa page publique indique qu\'il n\'est plus valide.');

        $this->actingAs($admin)
            ->patch('/admin/ateliers/passeports/'.self::ID_PASSEPORT.'/revocation', ['revoquer' => '0'])
            ->assertSessionHas('success', 'Passeport AB3D-EF6H rétabli.');

        $this->actingAs($admin)
            ->patch('/admin/ateliers/passeports/'.self::ID_PASSEPORT.'/revocation', ['revoquer' => 'peut-etre'])
            ->assertSessionHasErrors(['revoquer' => 'Action invalide.']);
    }

    public function test_un_atelier_ne_peut_pas_revoquer(): void
    {
        $this->mockAteliers($this->atelier());
        $this->mockPasseports()->shouldNotReceive('changerRevocation', 'pourAdmin');

        $atelier = $this->user(User::ROLE_ATELIER, self::ID_USER_ATELIER);

        $this->actingAs($atelier)
            ->patch('/admin/ateliers/passeports/'.self::ID_PASSEPORT.'/revocation', ['revoquer' => '1'])
            ->assertForbidden();
        $this->actingAs($atelier)->get('/admin/ateliers/passeports')->assertForbidden();
    }

    /* --------------------------------------------------------------- Affichage enrichi */

    public function test_la_page_publique_resume_la_piece_et_propose_de_faire_jouer_la_garantie(): void
    {
        $passeports = $this->mockPasseports();
        $passeports->shouldReceive('trouverParCode')->with(self::CODE)->andReturn($this->passeport());
        $passeports->shouldReceive('historique')->andReturn(collect([$this->passeport()]));
        $passeports->shouldReceive('ateliersDe')->andReturn(collect([self::ID_ATELIER => $this->atelier()]));
        Carbon::setTestNow(Carbon::parse('2026-10-17 12:00'));

        $this->get('/passeport/'.self::CODE)
            ->assertOk()
            ->assertSee('Résumé de la pièce')
            ->assertSee('Réparations certifiées')
            ->assertSee("Jusqu'au 15 novembre 2026", false)
            ->assertSee('Faire jouer la garantie')
            ->assertSee(route('front.rdv.create', ['atelier_id' => self::ID_ATELIER]), false)
            ->assertSee('value="'.route('front.passeport', ['code' => self::CODE]).'" readonly', false)
            ->assertSee('data-pp-actions hidden', false)
            ->assertSee('js/passeport.js')
            ->assertSee(route('front.passeport.verifier'), false);
    }

    public function test_sans_garantie_valable_ni_atelier_actif_pas_de_reclamation(): void
    {
        $passeports = $this->mockPasseports();
        $passeports->shouldReceive('trouverParCode')->with(self::CODE)->andReturn($this->passeport());
        $passeports->shouldReceive('historique')->andReturn(collect([$this->passeport()]));
        $passeports->shouldReceive('ateliersDe')->andReturn(collect([self::ID_ATELIER => $this->atelier()]));
        Carbon::setTestNow(Carbon::parse('2026-12-01 12:00'));

        $this->get('/passeport/'.self::CODE)
            ->assertOk()
            ->assertSee('Aucune en cours')
            ->assertSee('expirée depuis le')
            ->assertDontSee('Faire jouer la garantie');
    }

    public function test_garantie_valable_chez_un_atelier_suspendu_pas_de_lien_de_rdv(): void
    {
        $passeports = $this->mockPasseports();
        $passeports->shouldReceive('trouverParCode')->with(self::CODE)->andReturn($this->passeport());
        $passeports->shouldReceive('historique')->andReturn(collect([$this->passeport()]));
        // ateliersDe ne rend que les ateliers actifs.
        $passeports->shouldReceive('ateliersDe')->andReturn(collect());
        Carbon::setTestNow(Carbon::parse('2026-10-17 12:00'));

        $this->get('/passeport/'.self::CODE)
            ->assertOk()
            ->assertSee('Valable')
            ->assertDontSee('Faire jouer la garantie')
            ->assertDontSee(route('front.rdv.create'), false);
    }

    public function test_un_code_inconnu_affiche_une_page_d_aide_en_404(): void
    {
        $this->mockPasseports()->shouldReceive('trouverParCode')->andReturnNull();

        $this->get('/passeport/'.self::CODE)
            ->assertNotFound()
            ->assertSee('Ce passeport est introuvable')
            ->assertSee('name="code"', false)
            ->assertSee(route('front.passeport.rechercher'), false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_verifier_un_passeport_par_code_ou_par_adresse(): void
    {
        $passeports = $this->mockPasseports();
        $passeports->shouldReceive('trouverParCode')->with(self::CODE)->andReturn($this->passeport());

        $this->get('/passeport')->assertOk()->assertSee('Vérifier un passeport')->assertDontSee('introuvable');

        $this->get('/passeport/recherche?code='.self::CODE)
            ->assertRedirect(route('front.passeport', ['code' => self::CODE]));

        $this->get('/passeport/recherche?code='.urlencode('  http://exemple.tn/passeport/'.self::CODE.'?utm=qr '))
            ->assertRedirect(route('front.passeport', ['code' => self::CODE]));
    }

    public function test_verifier_refuse_un_code_mal_forme_ou_inconnu(): void
    {
        $passeports = $this->mockPasseports();
        $passeports->shouldReceive('trouverParCode')->andReturnNull();

        $this->get('/passeport/recherche?code=AB3D-EF6H')
            ->assertRedirect(route('front.passeport.verifier'))
            ->assertSessionHasErrors(['code' => 'Ce n\'est pas un code de passeport : il compte 22 lettres et chiffres (ou collez l\'adresse complète écrite sous le QR).']);

        $this->get('/passeport/recherche')
            ->assertRedirect(route('front.passeport.verifier'))
            ->assertSessionHasErrors('code');

        $this->get('/passeport/recherche?code='.self::CODE)
            ->assertRedirect(route('front.passeport.verifier'))
            ->assertSessionHasErrors(['code' => 'Aucun passeport ne correspond à ce code. Vérifiez les majuscules et minuscules.']);

        $page = $this->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag())->put('default', new \Illuminate\Support\MessageBag(['code' => 'Code <b>faux</b>']))])
            ->get('/passeport');
        $page->assertSee('aria-invalid="true"', false)->assertSee('Code &lt;b&gt;faux&lt;/b&gt;', false);
        $this->assertDoesNotMatchRegularExpression('/\s(required|maxlength|minlength|pattern)(=|\s|>)/', $page->getContent());
    }

    public function test_un_rdv_date_d_un_jour_futur_ne_propose_pas_de_generer(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-01 09:00'));
        $this->mockAteliers($this->atelier());
        $futur = $this->rdvTermine();
        $futur->date_rdv = '2026-11-09 10:00';
        $passeports = $this->mockPasseports();
        $passeports->shouldReceive('reparationsDeLatelier')->andReturn(collect([['rdv' => $futur, 'passeport' => null]]));
        $passeports->shouldReceive('reparationsEnCours')->andReturn(new Collection());

        $this->actingAs($this->user(User::ROLE_ATELIER, self::ID_USER_ATELIER))
            ->get('/admin/ateliers/mes-passeports')
            ->assertOk()
            ->assertSee('Possible le 09/11/2026')
            ->assertDontSee('name="rendez_vous_id"', false);
    }

    public function test_mes_passeports_resume_la_garantie_et_propose_de_copier_le_lien(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 12:00'));
        $this->mockPasseports()->shouldReceive('pourClient')->andReturn(new Collection([$this->passeport()]));

        $this->actingAs($this->user(User::ROLE_CITOYEN, self::ID_CLIENT))
            ->get('/mes-passeports')
            ->assertOk()
            ->assertSee('encore sous garantie')
            ->assertSee("Garantie jusqu'au 15 nov. 2026", false)
            ->assertSee('data-pp-url="'.route('front.passeport', ['code' => self::CODE]).'"', false)
            ->assertSee('Copier le lien');
    }
}
