<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\CodeDeConnexion;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Page « Mon profil » : nom, email et téléphone (changement confirmé par un code). */
class ProfilTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
        $this->client = tap(User::factory()->create(['name' => 'Mireille Adjovi', 'email' => null, 'telephone' => '0197000011', 'actif' => true]),
            fn (User $u) => $u->assignRole(Role::Client->value));
    }

    private function dernierCode(): string
    {
        $code = null;
        Notification::assertSentOnDemand(CodeDeConnexion::class, function (CodeDeConnexion $n) use (&$code) {
            $code = $n->code;

            return true;
        });

        return $code;
    }

    public function test_la_page_profil_et_le_nom(): void
    {
        $this->actingAs($this->client)->get('/mon-profil')->assertOk()
            ->assertSee('Mireille Adjovi')->assertSee('01 97 00 00 11')->assertSee('Ajouter mon email');

        $this->post('/mon-profil/nom', ['nom' => 'Mireille Adjovi-Houénou'])->assertSessionHas('statut');
        $this->assertSame('Mireille Adjovi-Houénou', $this->client->fresh()->name);
        $this->post('/mon-profil/nom', ['nom' => ''])->assertSessionHasErrors('nom');
    }

    public function test_ajouter_un_email_confirme_par_un_code(): void
    {
        $this->actingAs($this->client)->post('/mon-profil/changement', ['champ' => 'email', 'valeur' => 'Mireille@Exemple.com'])->assertSessionHas('statut');
        $this->assertNull($this->client->fresh()->email);   // pas avant le code
        $this->get('/mon-profil')->assertSee('Saisissez le code envoyé à mireille@exemple.com', false);

        $this->post('/mon-profil/changement/code', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/mon-profil/changement/code', ['code' => $this->dernierCode()])->assertRedirect('/mon-profil');

        $client = $this->client->fresh();
        $this->assertSame('mireille@exemple.com', $client->email);
        $this->assertNotNull($client->email_verified_at);
    }

    public function test_changer_de_numero_et_refus_d_une_valeur_deja_prise(): void
    {
        User::factory()->create(['telephone' => '0197000099', 'email' => 'pris@exemple.com']);
        $this->actingAs($this->client);

        $this->post('/mon-profil/changement', ['champ' => 'telephone', 'valeur' => '01 97 00 00 99'])->assertSessionHasErrors('valeur_telephone');
        $this->post('/mon-profil/changement', ['champ' => 'email', 'valeur' => 'pris@exemple.com'])->assertSessionHasErrors('valeur_email');
        $this->post('/mon-profil/changement', ['champ' => 'telephone', 'valeur' => '0197'])->assertSessionHasErrors('valeur_telephone');

        $this->post('/mon-profil/changement', ['champ' => 'telephone', 'valeur' => '01 97 00 00 55'])->assertSessionHas('statut');
        $this->post('/mon-profil/changement/code', ['code' => $this->dernierCode()]);
        $this->assertSame('0197000055', $this->client->fresh()->telephone);
    }

    public function test_le_proprietaire_et_le_gestionnaire_restent_dans_leur_espace(): void
    {
        foreach ([Role::Proprietaire, Role::Gestionnaire] as $role) {
            $pro = tap(User::factory()->create(['actif' => true]), fn (User $u) => $u->assignRole($role->value));
            $this->actingAs($pro)->get('/mon-compte')->assertRedirect(route('espace.proprietaire'));
            // Profil, notifications, parrainage : dans le gabarit de l'espace propriétaire (menu latéral)
            foreach (['/mon-profil', '/notifications', '/parrainage'] as $page) {
                $this->get($page)->assertOk()->assertSee('pro-side', false)->assertSee('Mon profil');
            }
        }
        // Le client garde le site client
        $this->actingAs($this->client)->get('/mon-profil')->assertOk()->assertDontSee('pro-side', false);
        $this->get('/mon-compte')->assertOk();
    }

    public function test_le_menu_mene_au_profil(): void
    {
        $this->actingAs($this->client)->get('/logements')->assertSee(route('profil'), false)->assertDontSee('data-bientot="Mon profil"', false);
    }
}
