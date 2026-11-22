<?php

namespace Tests\Unit;

use App\Paiement\FedaPayPasserelle;
use PHPUnit\Framework\TestCase;

class FedaPayEnvironnementTest extends TestCase
{
    public function test_la_cle_secrete_decide_de_l_environnement(): void
    {
        $this->assertSame('live', FedaPayPasserelle::environnement('sk_live_x', 'sandbox'));
        $this->assertSame('live', FedaPayPasserelle::environnement(' sk_live_x ', 'pk_live_x'));   // clé collée au mauvais endroit
        $this->assertSame('sandbox', FedaPayPasserelle::environnement('sk_sandbox_x', 'live'));
        $this->assertSame('live', FedaPayPasserelle::environnement('', 'production'));
        $this->assertSame('sandbox', FedaPayPasserelle::environnement('', 'n’importe quoi'));
    }
}
