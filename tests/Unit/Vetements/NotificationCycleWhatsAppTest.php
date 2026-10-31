<?php

namespace Tests\Unit\Vetements;

use App\Modules\Auth\Models\User;
use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Vetements\Models\Vetement;
use App\Modules\Vetements\Services\NotificationCycleWhatsApp;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationCycleWhatsAppTest extends TestCase
{
    private NotificationCycleWhatsApp $notifications;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.token' => 'jeton-test',
            'services.whatsapp.phone_id' => '1398895369978434',
            'services.whatsapp.graph_version' => 'v22.0',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200),
        ]);

        $this->notifications = new NotificationCycleWhatsApp();
    }

    public function test_un_don_accepte_envoie_un_remerciement(): void
    {
        $vetement = $this->piece(Vetement::STATUS_DONNE);

        $this->notifications->apresCloture(
            $vetement,
            Vetement::STATUS_DONNE,
            'Donné',
            'L\'association Atelier solidaire a accepté votre don.'
        );

        Http::assertSent(function ($request) {
            $corps = $request->data();

            return $request->url() === 'https://graph.facebook.com/v22.0/1398895369978434/messages'
                && ($corps['to'] ?? null) === '21622123456'
                && str_contains($corps['text']['body'] ?? '', 'Merci pour votre don')
                && str_contains($corps['text']['body'] ?? '', 'chaque don compte')
                && str_contains($corps['text']['body'] ?? '', 'Atelier solidaire');
        });
    }

    public function test_une_reparation_terminee_decrit_la_piece(): void
    {
        $vetement = $this->piece(Vetement::STATUS_REPARE);
        $vetement->setRelation('atelier', new Atelier(['nom' => 'Atelier El Medina']));

        $this->notifications->apresCloture(
            $vetement,
            Vetement::STATUS_REPARE,
            'Réparé',
            'La fermeture éclair a été remplacée.'
        );

        Http::assertSent(function ($request) {
            $texte = $request->data()['text']['body'] ?? '';

            return str_contains($texte, 'Votre pièce est réparée : Veste · Taille M.')
                && str_contains($texte, 'Matière : Laine')
                && str_contains($texte, 'La fermeture éclair a été remplacée.')
                && str_contains($texte, 'Analyse : Remplacer la fermeture.')
                && str_contains($texte, 'Atelier : Atelier El Medina');
        });
    }

    public function test_une_prise_en_charge_n_envoie_pas_de_message(): void
    {
        $this->notifications->apresCloture(
            $this->piece(Vetement::STATUS_EN_REPARATION),
            Vetement::STATUS_EN_REPARATION,
            'Réparation en cours',
            'L\'atelier a pris en charge cette pièce.'
        );

        Http::assertNothingSent();
    }

    public function test_sans_telephone_aucun_message_ne_part(): void
    {
        $vetement = $this->piece(Vetement::STATUS_DONNE);
        $vetement->owner->phone = '';

        $this->notifications->apresCloture($vetement, Vetement::STATUS_DONNE, 'Donné', null);

        Http::assertNothingSent();
    }

    private function piece(string $statut): Vetement
    {
        $vetement = new Vetement([
            'type' => 'Veste',
            'size' => 'M',
            'material' => 'Laine',
            'condition_label' => 'À réparer',
            'description' => 'Fermeture éclair cassée.',
            'status' => $statut,
            'analyse_ia' => ['resume' => 'Remplacer la fermeture.'],
        ]);
        $vetement->setRelation('owner', new User([
            'name' => 'Samia Ben Ali',
            'phone' => '+216 22 123 456',
        ]));

        return $vetement;
    }
}
