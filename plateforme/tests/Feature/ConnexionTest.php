<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CodeConnexion;
use App\Models\User;
use App\Notifications\Bienvenue;
use App\Notifications\Canaux\CanalSms;
use App\Notifications\CodeDeConnexion;
use App\Support\Parametres;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ConnexionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
    }

    /** Envoie la demande de code et renvoie le code reçu (capturé dans la notification). */
    private function demanderCode(array $donnees): string
    {
        $this->post('/connexion', $donnees)->assertRedirect('/connexion/code');

        $code = null;
        Notification::assertSentOnDemand(CodeDeConnexion::class, function (CodeDeConnexion $n) use (&$code) {
            $code = $n->code;

            return true;
        });

        return $code;
    }

    private function utilisateur(Role $role, array $attributs = []): User
    {
        return tap(User::factory()->create($attributs), fn (User $u) => $u->assignRole($role->value));
    }

    public function test_un_client_cree_son_compte_avec_son_email(): void
    {
        $code = $this->demanderCode(['mode' => 'inscription', 'nom' => 'Mireille Adjovi', 'role' => 'client', 'identifiant' => ' Mireille@Exemple.com ']);

        Notification::assertSentOnDemand(CodeDeConnexion::class, fn ($n, $canaux, AnonymousNotifiable $dest) => $canaux === ['mail'] && $dest->routes['mail'] === 'mireille@exemple.com');

        $this->post('/connexion/code', ['code' => $code])->assertRedirect('/mon-compte');

        $client = User::where('email', 'mireille@exemple.com')->first();
        $this->assertNotNull($client);
        $this->assertTrue($client->hasRole('client'));
        $this->assertNotNull($client->email_verified_at);
        $this->assertAuthenticatedAs($client);
        Notification::assertSentTo($client, Bienvenue::class);
    }

    public function test_un_proprietaire_cree_son_compte_avec_son_telephone_par_sms(): void
    {
        $code = $this->demanderCode(['mode' => 'inscription', 'nom' => 'Rodrigue Houngbo', 'role' => 'proprietaire', 'identifiant' => '+229 01 97 00 00 01']);

        Notification::assertSentOnDemand(CodeDeConnexion::class, fn ($n, $canaux, AnonymousNotifiable $dest) => $canaux === [CanalSms::class] && $dest->routes['sms'] === '+2290197000001');

        $this->post('/connexion/code', ['code' => $code])->assertRedirect('/pro');

        $proprio = User::where('telephone', '0197000001')->first();
        $this->assertTrue($proprio->hasRole('proprietaire'));
        $this->assertNotNull($proprio->telephone_verified_at);
        $this->get('/pro')->assertOk()->assertSee('Rodrigue Houngbo');
    }

    public function test_un_compte_existant_se_connecte(): void
    {
        $client = $this->utilisateur(Role::Client, ['email' => 'geraud@exemple.com']);

        $code = $this->demanderCode(['mode' => 'connexion', 'identifiant' => 'geraud@exemple.com']);
        $this->post('/connexion/code', ['code' => $code])->assertRedirect('/mon-compte');

        $this->assertAuthenticatedAs($client);
    }

    public function test_un_mauvais_code_est_refuse_puis_bloque_apres_cinq_essais(): void
    {
        $this->utilisateur(Role::Client, ['email' => 'prisca@exemple.com']);
        $bon = $this->demanderCode(['mode' => 'connexion', 'identifiant' => 'prisca@exemple.com']);
        $faux = $bon === '000000' ? '111111' : '000000';

        for ($i = 1; $i <= 4; $i++) {
            $this->post('/connexion/code', ['code' => $faux])->assertSessionHasErrors(['code' => 'Ce code ne correspond pas. Vérifiez les 6 chiffres.']);
        }
        $this->post('/connexion/code', ['code' => $faux])->assertSessionHasErrors(['code' => 'Trop d’essais. Demandez un nouveau code.']);
        // Même le bon code ne passe plus
        $this->post('/connexion/code', ['code' => $bon])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_un_code_expire_est_refuse(): void
    {
        $this->utilisateur(Role::Client, ['email' => 'arnaud@exemple.com']);
        $code = $this->demanderCode(['mode' => 'connexion', 'identifiant' => 'arnaud@exemple.com']);

        $this->travel(11)->minutes();

        $this->post('/connexion/code', ['code' => $code])->assertSessionHasErrors(['code' => 'Ce code a expiré. Demandez un nouveau code.']);
        $this->assertGuest();
    }

    public function test_un_code_ne_sert_qu_une_fois_et_n_est_pas_stocke_en_clair(): void
    {
        $this->utilisateur(Role::Client, ['email' => 'fifame@exemple.com']);
        $code = $this->demanderCode(['mode' => 'connexion', 'identifiant' => 'fifame@exemple.com']);

        $this->assertStringNotContainsString($code, CodeConnexion::first()->getRawOriginal('empreinte'));

        $this->post('/connexion/code', ['code' => $code])->assertRedirect();
        $this->post('/deconnexion');
        $this->withSession(['connexion' => ['identifiant' => 'fifame@exemple.com', 'canal' => 'mail', 'mode' => 'connexion', 'nom' => null, 'role' => null]])
            ->post('/connexion/code', ['code' => $code])
            ->assertSessionHasErrors('code');
    }

    public function test_connexion_avec_un_identifiant_inconnu(): void
    {
        $this->post('/connexion', ['mode' => 'connexion', 'identifiant' => 'inconnu@exemple.com'])
            ->assertSessionHasErrors(['identifiant' => 'Aucun compte n’utilise cet identifiant. Vérifiez-le, ou créez un compte.']);
        Notification::assertNothingSent();
    }

    public function test_inscription_avec_un_identifiant_deja_utilise(): void
    {
        $this->utilisateur(Role::Client, ['telephone' => '0197000012']);

        $this->post('/connexion', ['mode' => 'inscription', 'nom' => 'Géraud Kiki', 'role' => 'client', 'identifiant' => '01 97 00 00 12'])
            ->assertSessionHasErrors(['identifiant' => 'Un compte existe déjà avec cet identifiant : connectez-vous.']);
    }

    public function test_un_compte_desactive_ne_recoit_pas_de_code(): void
    {
        $this->utilisateur(Role::Client, ['email' => 'bloque@exemple.com', 'actif' => false]);

        $this->post('/connexion', ['mode' => 'connexion', 'identifiant' => 'bloque@exemple.com'])->assertSessionHasErrors('identifiant');
        Notification::assertNothingSent();
    }

    public function test_un_numero_invalide_est_refuse(): void
    {
        $this->post('/connexion', ['mode' => 'connexion', 'identifiant' => '97 00 00 00'])
            ->assertSessionHasErrors('identifiant');
    }

    public function test_si_les_sms_sont_desactives_on_ne_peut_pas_se_connecter_par_telephone(): void
    {
        Parametres::enregistrer(['notifications_sms' => false]);
        $this->utilisateur(Role::Client, ['telephone' => '0197000013']);

        $this->post('/connexion', ['mode' => 'connexion', 'identifiant' => '0197000013'])
            ->assertSessionHasErrors(['identifiant' => 'L’envoi de codes par SMS est désactivé pour le moment : utilisez votre adresse email.']);
        Notification::assertNothingSent();
    }

    public function test_on_ne_peut_pas_redemander_un_code_immediatement(): void
    {
        $this->utilisateur(Role::Client, ['email' => 'presse@exemple.com']);
        $this->demanderCode(['mode' => 'connexion', 'identifiant' => 'presse@exemple.com']);

        $this->post('/connexion/renvoyer')->assertSessionHasErrors('code');

        $this->travel(31)->seconds();
        $this->post('/connexion/renvoyer')->assertSessionHasNoErrors();
        $this->assertSame(2, CodeConnexion::count());
    }

    public function test_un_envoi_rate_n_empeche_pas_de_reessayer(): void
    {
        $this->utilisateur(Role::Client, ['email' => 'panne@exemple.com']);
        Notification::shouldReceive('route->notifyNow')->andThrow(new \Symfony\Component\Mailer\Exception\TransportException('SMTP refusé'));

        $this->post('/connexion', ['mode' => 'connexion', 'identifiant' => 'panne@exemple.com'])
            ->assertSessionHasErrors(['identifiant' => 'Le code n’a pas pu être envoyé. Réessayez dans un instant.']);
        $this->assertSame(0, CodeConnexion::count());
    }

    public function test_sans_fournisseur_de_sms_le_numero_n_est_pas_propose_en_ligne(): void
    {
        config(['vivi.sms.pilote' => 'log']);
        $this->assertTrue(Parametres::smsActif());

        $this->app['env'] = 'production';
        $this->assertFalse(Parametres::smsActif());
        $this->getJson('/api/v1/configuration')->assertJsonPath('data.connexion.sms', false);

        config(['vivi.sms.pilote' => 'smsto']);
        $this->assertTrue(Parametres::smsActif());
        $this->app['env'] = 'testing';
    }

    public function test_les_pages_des_espaces_demandent_une_connexion_et_le_bon_role(): void
    {
        $this->get('/mon-compte')->assertRedirect('/connexion');
        $this->get('/pro')->assertRedirect('/connexion');

        $this->actingAs($this->utilisateur(Role::Client))->get('/pro')->assertForbidden();
    }

    public function test_une_personne_connectee_qui_ouvre_la_connexion_va_dans_son_espace(): void
    {
        $this->actingAs($this->utilisateur(Role::Proprietaire));

        $this->get('/connexion')->assertRedirect('/espace');
        $this->get('/espace')->assertRedirect('/pro');
    }
}
