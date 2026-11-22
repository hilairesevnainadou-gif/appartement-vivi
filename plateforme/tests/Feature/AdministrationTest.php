<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\StatutRetrait;
use App\Enums\TypeMouvement;
use App\Filament\Pages\Parametres as PageParametres;
use App\Filament\Resources\Retraits\Pages\ListRetraits;
use App\Filament\Resources\Users\UserResource;
use App\Models\Logement;
use App\Models\User;
use App\Services\Caisse;
use App\Support\Parametres;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    /** Écrans réservés à l'administrateur, et écrans ouverts aussi à la caissière. */
    private const ADMIN_SEULEMENT = ['users', 'parametres', 'equipements'];

    private const AUSSI_CAISSIERE = ['logements', 'services', 'reservations', 'paiements', 'retraits', 'abonnements', 'formule-abonnements'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, CatalogueSeeder::class]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function utilisateur(Role $role): User
    {
        return tap(User::factory()->create(), fn (User $u) => $u->assignRole($role->value));
    }

    public function test_l_administrateur_ouvre_tous_les_ecrans(): void
    {
        $this->actingAs($this->utilisateur(Role::Administrateur));

        foreach ([...self::ADMIN_SEULEMENT, ...self::AUSSI_CAISSIERE, 'avis'] as $ecran) {
            $this->get('/admin/'.$ecran)->assertOk();
        }
    }

    public function test_le_tableau_de_bord_s_adapte_au_role(): void
    {
        $admin = tap(User::factory()->create(['name' => 'Nadia Admin']), fn (User $u) => $u->assignRole(Role::Administrateur->value));
        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertSee('Bonjour Nadia')
            ->assertSee('Revenus VIVI du mois')
            ->assertSee('Retraits à valider')
            ->assertSee('Logements à valider')
            ->assertSee('Arrivées du jour')
            ->assertDontSee('ui-avatars.com');

        // La caissière ne suit pas la validation des logements, mais les encaissements du jour
        $this->actingAs($this->utilisateur(Role::Caissiere))->get('/admin')->assertOk()
            ->assertSee('Encaissé aujourd’hui')
            ->assertDontSee('Logements à valider');
    }

    public function test_la_caissiere_voit_les_finances_mais_pas_les_reglages(): void
    {
        $this->actingAs($this->utilisateur(Role::Caissiere));

        foreach (self::AUSSI_CAISSIERE as $ecran) {
            $this->get('/admin/'.$ecran)->assertOk();
        }
        foreach (self::ADMIN_SEULEMENT as $ecran) {
            $this->get('/admin/'.$ecran)->assertForbidden();
        }
    }

    public function test_la_caissiere_consulte_les_logements_sans_pouvoir_les_modifier(): void
    {
        $proprietaire = $this->utilisateur(Role::Proprietaire);
        $logement = Logement::create([
            'proprietaire_id' => $proprietaire->id, 'titre' => 'Studio test', 'slug' => 'studio-test',
            'type' => 'studio', 'ville' => 'Cotonou', 'quartier' => 'Fidjrossè',
        ]);
        $this->actingAs($this->utilisateur(Role::Caissiere));

        $this->get('/admin/logements')->assertOk()->assertSee('Studio test');
        $this->get('/admin/logements/'.$logement->id.'/edit')->assertForbidden();
        $this->get('/admin/logements/create')->assertForbidden();
        $this->get('/admin/reservations/create')->assertNotFound();
    }

    public function test_on_ne_peut_pas_supprimer_son_propre_compte(): void
    {
        $admin = $this->utilisateur(Role::Administrateur);
        $this->actingAs($admin);

        $this->assertFalse(UserResource::canDelete($admin));
        $this->assertTrue(UserResource::canDelete($this->utilisateur(Role::Client)));
    }

    public function test_clients_et_proprietaires_n_entrent_pas_dans_l_administration(): void
    {
        foreach ([Role::Client, Role::Proprietaire, Role::Gestionnaire] as $role) {
            $this->actingAs($this->utilisateur($role))->get('/admin')->assertForbidden();
        }
    }

    public function test_un_compte_desactive_n_entre_plus_dans_l_administration(): void
    {
        $admin = $this->utilisateur(Role::Administrateur);
        $admin->update(['actif' => false]);

        $this->actingAs($admin)->get('/admin')->assertForbidden();
    }

    public function test_l_administrateur_modifie_la_commission_depuis_la_page_parametres(): void
    {
        $this->actingAs($this->utilisateur(Role::Administrateur));

        Livewire::test(PageParametres::class)
            ->fillForm(['commission_pourcentage' => 7.5, 'notifications_email' => false, 'services_beneficiaire' => 'proprietaire',
                'commission_salles_tranches' => [['jusqua' => 200000, 'pourcentage' => 12], ['jusqua' => null, 'pourcentage' => 4]]])
            ->call('enregistrer')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertSame(7.5, Parametres::commissionPourcentage());
        $this->assertFalse(Parametres::get('notifications_email'));
        $this->assertSame('proprietaire', Parametres::get('services_beneficiaire'));
        $this->assertSame([12.0, 4.0], [Parametres::tauxSalle(200000), Parametres::tauxSalle(200001)]);
    }

    public function test_la_caissiere_valide_un_retrait_depuis_la_liste(): void
    {
        $proprietaire = $this->utilisateur(Role::Proprietaire);
        $proprietaire->mouvementsSolde()->create(['type' => TypeMouvement::CreditSejour, 'montant' => 25000, 'libelle' => 'Séjour']);
        $retrait = app(Caisse::class)->demanderRetrait($proprietaire, 25000);
        $this->actingAs($this->utilisateur(Role::Caissiere));

        Livewire::test(ListRetraits::class)
            ->assertCanSeeTableRecords([$retrait])
            ->callAction(TestAction::make('payer')->table($retrait), [
                'mode_reglement' => 'Moov Money',
                'reference_reglement' => 'MOOV-4455',
            ])
            ->assertHasNoFormErrors();

        $retrait->refresh();
        $this->assertSame(StatutRetrait::Paye, $retrait->statut);
        $this->assertSame('Moov Money', $retrait->mode_reglement);
    }
}
