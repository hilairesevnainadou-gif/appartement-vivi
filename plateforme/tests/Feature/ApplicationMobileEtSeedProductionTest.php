<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Equipement;
use App\Models\FormuleAbonnement;
use App\Models\Logement;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Parametres;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationMobileEtSeedProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sans_lien_les_boutons_affichent_bientot_disponible(): void
    {
        $page = $this->get(route('accueil'))->assertOk();

        $page->assertSee('VIVI dans votre poche')
            ->assertSee('Bientôt disponible')
            ->assertSee('Google Play')
            ->assertSee('App Store')
            ->assertSee('APK Android')
            ->assertDontSee('play.google.com/store/apps');
    }

    public function test_les_liens_regles_rendent_les_boutons_actifs(): void
    {
        Parametres::enregistrer([
            'app_apk_lien' => '/telechargements/vivi-1.2.apk',
            'app_apk_version' => '1.2.0',
            'app_play_store' => 'https://play.google.com/store/apps/details?id=bj.vivi.app',
        ]);

        $page = $this->get(route('accueil'))->assertOk();

        $page->assertSee(url('/telechargements/vivi-1.2.apk'), false)
            ->assertSee('https://play.google.com/store/apps/details?id=bj.vivi.app', false)
            ->assertSee("(version 1.2.0)")
            ->assertSee('Bientôt disponible');   // App Store toujours absent
    }

    public function test_les_boutons_peuvent_etre_masques(): void
    {
        Parametres::enregistrer(['app_afficher' => false]);

        $this->get(route('accueil'))->assertOk()->assertDontSee('VIVI dans votre poche')->assertDontSee('APK Android');
    }

    public function test_le_pied_de_page_montre_adresse_telephone_reseaux_et_5_villes_au_plus(): void
    {
        Parametres::enregistrer([
            'contact_adresse' => 'Fidjrossè, Cotonou',
            'contact_telephone' => '+229 01 97 00 00 00',
            'reseau_facebook' => 'https://facebook.com/vivi',
            'reseau_instagram' => 'pas-un-lien',   // ignoré : pas une adresse https
            'villes' => ['Cotonou', 'Abomey-Calavi', 'Porto-Novo', 'Ouidah', 'Parakou', 'Natitingou', 'Bohicon'],
        ]);

        $page = $this->get(route('aide.conditions'))->assertOk();

        $page->assertSee('Fidjrossè, Cotonou')
            ->assertSee('tel:+2290197000000', false)
            ->assertSee('https://facebook.com/vivi', false)
            ->assertDontSee('VIVI sur Instagram')
            ->assertSee('Parakou')
            ->assertDontSee('Natitingou')
            ->assertSee('Toutes les villes (7)');
    }

    public function test_le_seed_de_production_ne_cree_ni_logement_ni_salle(): void
    {
        config(['app.env' => 'production']);
        putenv('ADMIN_EMAIL=admin@vivi.test');
        putenv('ADMIN_PASSWORD=mot-de-passe-solide-2026');

        try {
            $this->seed(ProductionSeeder::class);
            $this->seed(ProductionSeeder::class);   // relancé : rien en double
        } finally {
            putenv('ADMIN_EMAIL');
            putenv('ADMIN_PASSWORD');
        }

        $this->assertSame(0, Logement::count());
        $this->assertSame(0, Reservation::count());
        $this->assertSame(1, User::count());
        $this->assertTrue(User::first()->hasRole(Role::Administrateur->value));
        $this->assertSame(3, FormuleAbonnement::count());
        $this->assertTrue(Equipement::where('nom', 'Climatisation')->exists());
    }
}
