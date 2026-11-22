<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\StatutLogement;
use App\Enums\TypeLogement;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Models\Logement;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role as RoleSpatie;
use Tests\TestCase;

/** Rôles et permissions de l'administration, journal d'activité. */
class PermissionsEtJournalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function utilisateur(string $role, string $nom = 'Personne Test'): User
    {
        return tap(User::factory()->create(['name' => $nom, 'actif' => true]), fn (User $u) => $u->assignRole($role));
    }

    public function test_la_caissiere_garde_ses_acces_de_depart(): void
    {
        $caissiere = $this->utilisateur(Role::Caissiere->value);
        $this->actingAs($caissiere);

        $this->get('/admin/retraits')->assertOk();
        $this->get('/admin/exports')->assertOk();
        $this->get('/admin/parametres')->assertForbidden();
        $this->get('/admin/roles')->assertForbidden();
        $this->get('/admin/journal')->assertForbidden();
    }

    public function test_un_role_cree_par_l_administrateur_donne_les_acces_coches(): void
    {
        $comptable = RoleSpatie::create(['name' => 'comptable', 'guard_name' => 'web']);
        $comptable->givePermissionTo(['administration.acceder', 'exports.telecharger', 'paiements.voir']);
        $user = $this->utilisateur('comptable', 'Aline Comptable');

        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
        $this->actingAs($user)->get('/admin/paiements')->assertOk();
        $this->get('/admin/exports')->assertOk();
        $this->get('/admin/logements')->assertForbidden();
        $this->get('/admin/retraits')->assertForbidden();

        // Sans « se connecter à l'administration » : pas d'accès du tout
        $comptable->revokePermissionTo('administration.acceder');
        $this->assertFalse($user->fresh()->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_l_administrateur_modifie_les_permissions_et_le_journal_le_note(): void
    {
        $admin = $this->utilisateur(Role::Administrateur->value, 'Admin VIVI');
        $this->actingAs($admin)->get('/admin/roles')->assertOk()->assertSee('Caissière')->assertSee('Toutes les permissions');

        $caissiere = RoleSpatie::findByName(Role::Caissiere->value);
        Livewire::test(EditRole::class, ['record' => $caissiere->getRouteKey()])
            ->fillForm(['permissions_administration' => ['parametres.gerer']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($caissiere->fresh()->hasPermissionTo('parametres.gerer'));
        $entree = Activity::where('event', 'permissions')->latest('id')->firstOrFail();
        $this->assertTrue($entree->causer->is($admin));
        $this->assertContains('Modifier les paramètres de la plateforme', $entree->properties['ajoutees']);

        $this->actingAs($this->utilisateur(Role::Caissiere->value))->get('/admin/parametres')->assertOk();
    }

    public function test_le_journal_garde_qui_a_modifie_quoi(): void
    {
        $admin = $this->utilisateur(Role::Administrateur->value, 'Admin VIVI');
        $proprio = $this->utilisateur(Role::Proprietaire->value);
        $this->actingAs($admin);

        $logement = Logement::create(['proprietaire_id' => $proprio->id, 'titre' => 'Studio Lagune', 'slug' => 'studio-lagune', 'type' => TypeLogement::Studio,
            'ville' => 'Cotonou', 'quartier' => 'Fidjrossè', 'capacite' => 2, 'statut' => StatutLogement::EnValidation]);
        $logement->update(['statut' => StatutLogement::Publie]);

        $modif = Activity::where('subject_type', 'logement')->where('event', 'updated')->firstOrFail();
        $this->assertSame('Modification', $modif->description);
        $this->assertTrue($modif->causer->is($admin));
        $this->assertSame('publie', $modif->properties['attributes']['statut']);
        $this->assertSame('en_validation', $modif->properties['old']['statut']);

        $this->get('/admin/journal')->assertOk()->assertSee('Logement Studio Lagune')->assertSee('Admin VIVI');
    }

    public function test_les_connexions_sont_journalisees(): void
    {
        $client = $this->utilisateur(Role::Client->value);
        auth()->login($client);
        $this->assertTrue(Activity::where('log_name', 'connexion')->where('event', 'connexion')->where('causer_id', $client->id)->exists());
    }
}
