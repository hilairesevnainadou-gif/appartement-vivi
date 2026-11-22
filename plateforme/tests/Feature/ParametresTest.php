<?php

namespace Tests\Feature;

use App\Models\Parametre;
use App\Support\Parametres;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ParametresTest extends TestCase
{
    use RefreshDatabase;

    public function test_sans_reglage_enregistre_la_valeur_par_defaut_de_la_config_s_applique(): void
    {
        config(['vivi.commission_pourcentage' => 5]);

        $this->assertSame(5.0, Parametres::commissionPourcentage());
        $this->assertTrue(Parametres::get('notifications_sms'));
        $this->assertSame(3, Parametres::get('report_delai_minimum_heures'));
    }

    public function test_un_reglage_enregistre_remplace_la_valeur_par_defaut(): void
    {
        Parametres::enregistrer(['commission_pourcentage' => '6.5', 'notifications_sms' => false, 'villes' => ['Cotonou', '', 'Parakou']]);

        $this->assertSame(6.5, Parametres::commissionPourcentage());
        $this->assertFalse(Parametres::get('notifications_sms'));
        $this->assertSame(['Cotonou', 'Parakou'], Parametres::get('villes'));
        $this->assertSame(3, Parametre::count());
    }

    public function test_une_cle_inconnue_est_ignoree_a_l_enregistrement_et_refusee_a_la_lecture(): void
    {
        Parametres::enregistrer(['taux_inconnu' => 99]);
        $this->assertSame(0, Parametre::count());

        $this->expectException(InvalidArgumentException::class);
        Parametres::get('taux_inconnu');
    }
}
