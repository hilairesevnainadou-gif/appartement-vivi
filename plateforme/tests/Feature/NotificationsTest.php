<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\Bienvenue;
use App\Notifications\NotificationEquipe;
use App\Services\Caisse;
use App\Enums\TypeMouvement;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Espace Notifications du site, notifications de l'équipe, emails aux couleurs de VIVI. */
class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function utilisateur(Role $role, array $attributs = []): User
    {
        return tap(User::factory()->create($attributs + ['actif' => true]), fn (User $u) => $u->assignRole($role->value));
    }

    public function test_les_notifications_envoyees_restent_dans_l_espace_notifications(): void
    {
        $client = $this->utilisateur(Role::Client, ['name' => 'Mireille Adjovi']);
        $client->notify(new Bienvenue(Role::Client));

        $n = $client->notificationsSite()->sole();
        $this->assertSame('vivi', $n->data['format']);
        $this->assertNotEmpty($n->data['titre']);
        $this->assertNull($n->read_at);

        $this->actingAs($client)->get('/logements')->assertOk()->assertSee('cloche__compteur', false);
        $this->get('/notifications')->assertOk()->assertSee($n->data['titre'])->assertSee('1 non lue');

        $this->get('/notifications/'.$n->id)->assertRedirect();
        $this->assertNotNull($n->fresh()->read_at);

        // Les notifications d'un autre ne s'ouvrent pas
        $autre = $this->utilisateur(Role::Client);
        $this->actingAs($autre)->get('/notifications/'.$n->id)->assertNotFound();
    }

    public function test_tout_marquer_comme_lu(): void
    {
        $client = $this->utilisateur(Role::Client);
        $client->notify(new Bienvenue(Role::Client));
        $client->notify(new Bienvenue(Role::Client));

        $this->actingAs($client)->post('/notifications/tout-lu')->assertSessionHas('statut');
        $this->assertSame(0, $client->notificationsSite()->whereNull('read_at')->count());
    }

    public function test_l_equipe_est_prevenue_d_un_retrait_demande(): void
    {
        $admin = $this->utilisateur(Role::Administrateur);
        $caissiere = $this->utilisateur(Role::Caissiere);
        $client = $this->utilisateur(Role::Client);
        $proprio = $this->utilisateur(Role::Proprietaire, ['name' => 'Rodrigue Houngbo']);
        $proprio->mouvementsSolde()->create(['type' => TypeMouvement::Ajustement, 'montant' => 50000, 'libelle' => 'Solde']);

        app(Caisse::class)->demanderRetrait($proprio, 20000);

        foreach ([$admin, $caissiere] as $membre) {
            $n = $membre->notifications()->sole();
            $this->assertSame(NotificationEquipe::class, $n->type);
            $this->assertSame('filament', $n->data['format']);   // cloche de l'administration
            $this->assertStringContainsString('Retrait demandé', $n->data['title']);
        }
        $this->assertSame(0, $client->notifications()->count());
        // Et elle n'apparaît pas dans l'espace Notifications du site
        $this->assertSame(0, $admin->notificationsSite()->count());
    }

    public function test_les_emails_sont_aux_couleurs_de_vivi(): void
    {
        $client = $this->utilisateur(Role::Client, ['name' => 'Mireille Adjovi', 'email' => 'mireille@exemple.com']);
        $html = (string) (new Bienvenue(Role::Client))->toMail($client)->render();

        $this->assertStringContainsString('images/logo/vivi-logo-1200.png', $html);
        $this->assertStringContainsString('#136f6e', $html);           // turquoise de la charte (bouton, liens)
        $this->assertStringContainsString('L’équipe VIVI', $html);
        $this->assertStringContainsString('Appartements meublés', $html);
        $this->assertStringNotContainsString('Laravel', $html);
        $this->assertStringNotContainsString('All rights reserved', $html);
    }
}
