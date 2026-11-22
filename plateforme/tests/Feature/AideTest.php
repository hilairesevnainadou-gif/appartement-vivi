<?php

namespace Tests\Feature;

use App\Models\FormuleAbonnement;
use App\Models\User;
use App\Support\Parametres;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pages d'aide publiques et lien WhatsApp paramétrable. */
class AideTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_pages_d_aide_suivent_les_parametres(): void
    {
        Parametres::enregistrer(['commission_pourcentage' => 7.5, 'report_delai_minimum_heures' => 4]);

        $this->get('/aide/annulation-et-report')->assertOk()
            ->assertSee('7,5 %')->assertSee('jamais remboursée', false)->assertSee('4 h avant l’arrivée', false);
        $this->get('/aide/conditions-generales')->assertOk()->assertSee('commission VIVI</strong> s’y ajoute : 7,5 %', false);
    }

    public function test_la_page_proprietaires_presente_les_formules_actives(): void
    {
        FormuleAbonnement::create(['nom' => 'Essentiel', 'prix_mensuel' => 5000, 'logements_max' => 1, 'gestionnaires_max' => 0, 'avantages' => ['1 logement'], 'actif' => true, 'ordre' => 1]);
        FormuleAbonnement::create(['nom' => 'Premium', 'prix_mensuel' => 25000, 'logements_max' => 10, 'gestionnaires_max' => 5, 'avantages' => ['10 logements', 'Gestionnaires illimités'], 'actif' => true, 'ordre' => 3]);
        FormuleAbonnement::create(['nom' => 'Ancienne', 'prix_mensuel' => 1000, 'logements_max' => 1, 'gestionnaires_max' => 0, 'avantages' => [], 'actif' => false, 'ordre' => 0]);

        $this->get('/proprietaires')->assertOk()
            ->assertSeeInOrder(['Essentiel', "5\u{00A0}000", 'Premium', "25\u{00A0}000", 'Gestionnaires illimités'])
            ->assertDontSee('Ancienne')
            ->assertSee('À partir de', false)
            ->assertSee('suite=%2Fpro%2Fabonnement', false);   // l'inscription ramène au choix de la formule

        $this->get('/')->assertSee(route('proprietaires').'#formules', false);
    }

    public function test_le_proprietaire_connecte_est_envoye_vers_son_abonnement(): void
    {
        $this->seed(RoleSeeder::class);
        $proprio = tap(User::factory()->create(['actif' => true]), fn (User $u) => $u->assignRole('proprietaire'));
        FormuleAbonnement::create(['nom' => 'Confort', 'prix_mensuel' => 12000, 'logements_max' => 3, 'gestionnaires_max' => 1, 'avantages' => ['3 logements'], 'actif' => true, 'ordre' => 2]);

        $this->actingAs($proprio)->get('/proprietaires')->assertOk()
            ->assertSee(route('pro.abonnement'), false)->assertSee('Choisir Confort');
    }

    public function test_sans_numero_le_lien_whatsapp_est_masque(): void
    {
        $this->assertNull(Parametres::lienWhatsapp());
        $this->get('/aide/annulation-et-report')->assertDontSee('wa.me');
        $this->get('/')->assertDontSee('Nous écrire sur WhatsApp', false);
    }

    public function test_le_numero_whatsapp_regle_par_l_administrateur(): void
    {
        Parametres::enregistrer(['whatsapp_numero' => '01 97 00 00 00', 'whatsapp_message' => 'Bonjour VIVI']);
        $this->assertSame('https://wa.me/2290197000000?text=Bonjour%20VIVI', Parametres::lienWhatsapp());

        foreach (['+229 01 97 00 00 00', '00229 0197000000', '2290197000000'] as $saisie) {
            Parametres::enregistrer(['whatsapp_numero' => $saisie, 'whatsapp_message' => '']);
            $this->assertSame('https://wa.me/2290197000000', Parametres::lienWhatsapp(), $saisie);
        }

        $this->get('/')->assertSee('https://wa.me/2290197000000', false);
        $this->get('/aide/conditions-generales')->assertSee('Nous écrire sur WhatsApp', false);
    }
}
