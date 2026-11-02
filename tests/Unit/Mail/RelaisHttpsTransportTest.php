<?php

namespace Tests\Unit\Mail;

use App\Mail\Transport\RelaisHttpsTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class RelaisHttpsTransportTest extends TestCase
{
    public function test_le_message_html_et_son_image_deviennent_une_charge(): void
    {
        $image = (new DataPart('jpeg-bytes', 'avant.jpg', 'image/jpeg'))->asInline();
        $cid = $image->getContentId();
        $email = (new Email())
            ->from(new Address('expediteur@example.com', 'TexTileCycle'))
            ->to('citoyen@example.com')
            ->subject('Votre guide')
            ->text('Bonjour')
            ->html('<p>Bonjour</p><img src="cid:'.$cid.'">');
        $email->attachPart($image);

        $charge = (new RelaisHttpsTransport('https://example.test/exec', 'secret'))
            ->charge(new SentMessage($email, Envelope::create($email)));

        $this->assertSame('citoyen@example.com', $charge['to']);
        $this->assertSame('Votre guide', $charge['subject']);
        $this->assertSame('TexTileCycle', $charge['fromName']);
        $this->assertSame('expediteur@example.com', $charge['replyTo']);
        $this->assertSame('secret', $charge['secret']);
        $this->assertSame($cid, $charge['inline'][0]['cid']);
        $this->assertSame('avant.jpg', $charge['inline'][0]['name']);
        $this->assertSame('image/jpeg', $charge['inline'][0]['mime']);
        $this->assertSame(base64_encode('jpeg-bytes'), $charge['inline'][0]['data']);
        $this->assertSame([], $charge['attachments']);
    }

    public function test_un_relais_sans_adresse_refuse_l_envoi(): void
    {
        $email = (new Email())
            ->from('expediteur@example.com')
            ->to('citoyen@example.com')
            ->subject('Bonjour')
            ->text('Bonjour');
        $transport = new RelaisHttpsTransport('', '');

        $this->expectException(TransportException::class);
        $transport->send($email);
    }
}
