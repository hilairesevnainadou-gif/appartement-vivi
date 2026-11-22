<?php

namespace Tests\Feature;

use App\Sms\EchecEnvoiSms;
use App\Sms\JournalSms;
use App\Sms\Sms;
use App\Sms\SmsTo;
use App\Support\Telephone;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsTest extends TestCase
{
    public function test_les_numeros_beninois_sont_normalises(): void
    {
        $this->assertSame('0197000000', Telephone::normaliser('01 97 00 00 00'));
        $this->assertSame('0197000000', Telephone::normaliser('+229 01 97 00 00 00'));
        $this->assertSame('0197000000', Telephone::normaliser('229-0197000000'));
        $this->assertNull(Telephone::normaliser('97 00 00 00'));        // ancien format à 8 chiffres
        $this->assertNull(Telephone::normaliser('0297000000'));         // ne commence pas par 01
        $this->assertSame('+2290197000000', Telephone::international('0197000000'));
        $this->assertSame('01 •• •• •• 00', Telephone::masque('0197000000'));
    }

    public function test_le_pilote_est_choisi_dans_la_configuration(): void
    {
        config(['vivi.sms.pilote' => 'log']);
        $this->assertInstanceOf(JournalSms::class, app(Sms::class));

        config(['vivi.sms.pilote' => 'smsto', 'vivi.sms.smsto.cle' => 'cle-test']);
        $this->assertInstanceOf(SmsTo::class, app(Sms::class));
    }

    public function test_sms_to_recoit_le_bon_message(): void
    {
        Http::fake(['api.sms.to/*' => Http::response(['success' => true, 'message_id' => 'abc'])]);

        (new SmsTo('cle-test', 'VIVI', 'https://api.sms.to/sms/send'))->envoyer('+2290197000000', 'Votre code est 123456');

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.sms.to/sms/send'
            && $r->hasHeader('Authorization', 'Bearer cle-test')
            && $r['to'] === '+2290197000000'
            && $r['sender_id'] === 'VIVI'
            && $r['message'] === 'Votre code est 123456');
    }

    public function test_un_refus_de_sms_to_leve_une_erreur(): void
    {
        Http::fake(['api.sms.to/*' => Http::response(['success' => false, 'message' => 'Solde insuffisant'], 402)]);

        $this->expectException(EchecEnvoiSms::class);
        (new SmsTo('cle-test', 'VIVI', 'https://api.sms.to/sms/send'))->envoyer('+2290197000000', 'Test');
    }
}
