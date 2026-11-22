<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\StatutLogement;
use App\Enums\TypeLogement;
use App\Filament\Resources\Alertes\Pages\ListAlertes;
use App\Models\AlerteSysteme;
use App\Models\Logement;
use App\Models\User;
use App\Notifications\AlerteSystemeNotification;
use App\Services\Surveillance;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/** Surveillance (alertes de panne et d'attaque) et carte des logements. */
class SurveillanceEtCarteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Notification::fake();
        $this->admin = tap(User::factory()->create(['name' => 'Admin VIVI', 'email' => 'admin@exemple.com', 'actif' => true]),
            fn (User $u) => $u->assignRole(Role::Administrateur->value));
    }

    public function test_un_robot_qui_cherche_un_fichier_sensible_declenche_une_alerte(): void
    {
        $this->get('/.env')->assertNotFound();
        $this->get('/.env')->assertNotFound();

        $alerte = AlerteSysteme::where('type', 'chemin_attaque')->sole();   // regroupée : une seule alerte
        $this->assertSame(AlerteSysteme::AVERTISSEMENT, $alerte->niveau);
        $this->assertSame(2, $alerte->occurrences);
        $this->assertSame('127.0.0.1', $alerte->source);
        Notification::assertSentToTimes($this->admin, AlerteSystemeNotification::class, 1);   // pas de nouveau message dans les 30 min
    }

    public function test_mots_de_passe_faux_en_serie_sur_l_administration(): void
    {
        for ($i = 0; $i < 5; $i++) {
            event(new Failed('web', null, ['email' => 'pirate@exemple.com', 'password' => 'x']));
        }
        $alerte = AlerteSysteme::where('type', 'connexion_admin')->sole();
        $this->assertSame(AlerteSysteme::CRITIQUE, $alerte->niveau);
        Notification::assertSentTo($this->admin, AlerteSystemeNotification::class, fn ($n, $canaux) => in_array('database', $canaux, true) && in_array('mail', $canaux, true));
    }

    public function test_webhook_falsifie(): void
    {
        config(['vivi.paiement.fedapay.cle_webhook' => 'wh_sandbox_secret']);
        $this->postJson('/webhooks/fedapay', ['entity' => ['id' => 1]], ['X-FEDAPAY-SIGNATURE' => 't=1,s=faux'])->assertStatus(400);

        $this->assertSame(AlerteSysteme::CRITIQUE, AlerteSysteme::where('type', 'webhook')->sole()->niveau);
    }

    public function test_erreurs_en_serie(): void
    {
        $surveillance = app(Surveillance::class);
        for ($i = 0; $i < 9; $i++) {
            $surveillance->erreur(new RuntimeException('Panne de test'));
        }
        $this->assertSame(0, AlerteSysteme::count());   // sous le seuil
        $surveillance->erreur(new RuntimeException('Panne de test'));
        $this->assertSame(AlerteSysteme::CRITIQUE, AlerteSysteme::where('type', 'erreurs_serveur')->sole()->niveau);
    }

    public function test_l_administrateur_voit_et_traite_les_alertes(): void
    {
        $alerte = app(Surveillance::class)->signaler('exploration', AlerteSysteme::AVERTISSEMENT, 'Test', '10.0.0.1');
        $this->actingAs($this->admin);

        $this->get('/admin')->assertOk()->assertSee('1 alerte à examiner');
        Livewire::test(ListAlertes::class)
            ->assertCanSeeTableRecords([$alerte])
            ->callAction(TestAction::make('traiter')->table($alerte));
        $this->assertNotNull($alerte->fresh()->resolue_le);
        $this->get('/admin')->assertSee('Surveillance : tout est normal.');

        // La caissière ne voit pas les alertes (permission non donnée au départ)
        $caissiere = tap(User::factory()->create(['actif' => true]), fn (User $u) => $u->assignRole(Role::Caissiere->value));
        $this->actingAs($caissiere)->get('/admin/alertes')->assertForbidden();
    }

    public function test_controle_de_sante(): void
    {
        $this->assertSame([], app(Surveillance::class)->controler());
        $this->artisan('vivi:surveiller')->assertSuccessful();
    }

    public function test_carte_des_logements_et_progression(): void
    {
        $proprio = tap(User::factory()->create(['name' => 'Rodrigue Houngbo']), fn (User $u) => $u->assignRole(Role::Proprietaire->value));
        foreach ([['Studio Lagune', 'Cotonou', 'Fidjrossè', StatutLogement::Publie, 6.353], ['Villa Ouidah', 'Ouidah', 'Djègbadji', StatutLogement::EnValidation, 6.329]] as [$titre, $ville, $quartier, $statut, $lat]) {
            Logement::create(['proprietaire_id' => $proprio->id, 'titre' => $titre, 'slug' => \Illuminate\Support\Str::slug($titre), 'type' => TypeLogement::Studio,
                'ville' => $ville, 'quartier' => $quartier, 'capacite' => 2, 'statut' => $statut, 'latitude' => $lat, 'longitude' => 2.2]);
        }
        Logement::create(['proprietaire_id' => $proprio->id, 'titre' => 'Sans position', 'slug' => 'sans-position', 'type' => TypeLogement::Studio,
            'ville' => 'Cotonou', 'quartier' => 'Akpakpa', 'capacite' => 2, 'statut' => StatutLogement::Brouillon]);

        $this->actingAs($this->admin)->get('/admin/carte')->assertOk()
            ->assertSee('Logements et salles inscrits')->assertSee('Couverture du territoire')
            ->assertSee('1 fiche(s) sans position', false)
            ->assertSee('Studio Lagune')->assertSee('Rodrigue Houngbo');

        $caissiere = tap(User::factory()->create(['actif' => true]), fn (User $u) => $u->assignRole(Role::Caissiere->value));
        $this->actingAs($caissiere)->get('/admin/carte')->assertForbidden();
    }
}
