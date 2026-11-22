<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Connexion et création de compte « avec Google » : clients seulement. Google est simulé (Http::fake). */
class ConnexionGoogleTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_WEB = 'web-123.apps.googleusercontent.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
        config(['services.google.client_id' => self::CLIENT_WEB, 'services.google.client_secret' => 'secret', 'services.google.clients_android' => []]);
    }

    /** Google répond : échange du code, puis vérification de l'ID token. */
    private function googleRepond(string $email = 'awa@gmail.com', bool $verifie = true, string $aud = self::CLIENT_WEB, string $sub = '1094'): void
    {
        // Nouvelle réponse de Google : les réponses simulées précédentes sont oubliées
        $this->app->forgetInstance(\Illuminate\Http\Client\Factory::class);
        Http::clearResolvedInstances();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['id_token' => 'jeton-id', 'access_token' => 'x']),
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'iss' => 'https://accounts.google.com', 'aud' => $aud, 'sub' => $sub, 'exp' => (string) (time() + 3600),
                'email' => $email, 'email_verified' => $verifie ? 'true' : 'false', 'name' => 'Awa Dossou',
            ]),
        ]);
    }

    /** Aller sur Google puis revenir avec un code, comme le ferait le navigateur. */
    private function retourDeGoogle()
    {
        $aller = $this->get('/connexion/google');
        parse_str((string) parse_url($aller->headers->get('Location'), PHP_URL_QUERY), $parametres);

        return $this->get('/connexion/google/retour?code=code-google&state='.$parametres['state']);
    }

    public function test_le_bouton_google_est_sur_la_connexion_et_l_inscription(): void
    {
        $this->get('/connexion')->assertOk()->assertSee('Se connecter avec Google')->assertSee('ou avec un code');
        $this->get('/connexion?mode=inscription')->assertOk()->assertSee('S’inscrire avec Google', false);

        // Identifiants Google pas encore saisis : message clair au lieu d'une erreur
        config(['services.google.client_id' => null]);
        $this->get('/connexion/google')->assertRedirect(route('connexion'))->assertSessionHasErrors('identifiant');
    }

    public function test_un_nouveau_client_cree_son_compte_avec_google(): void
    {
        $this->googleRepond();
        $aller = $this->get('/connexion/google');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $aller->headers->get('Location'));
        $this->assertStringContainsString(urlencode('/connexion/google/retour'), $aller->headers->get('Location'));

        $this->retourDeGoogle()->assertRedirect(route('espace.client'));

        $u = User::where('email', 'awa@gmail.com')->firstOrFail();
        $this->assertAuthenticatedAs($u);
        $this->assertSame('Awa Dossou', $u->name);
        $this->assertSame('1094', $u->google_id);
        $this->assertNotNull($u->email_verified_at);
        $this->assertSame(['client'], $u->getRoleNames()->all());
    }

    public function test_un_client_existant_est_relie_a_google(): void
    {
        $client = tap(User::factory()->create(['email' => 'awa@gmail.com', 'actif' => true]), fn (User $u) => $u->assignRole(Role::Client->value));
        $this->googleRepond();

        $this->retourDeGoogle()->assertRedirect();
        $this->assertAuthenticatedAs($client);
        $this->assertSame('1094', $client->fresh()->google_id);
        $this->assertSame(1, User::count());
    }

    public function test_les_comptes_professionnels_ne_peuvent_pas_utiliser_google(): void
    {
        foreach ([Role::Proprietaire, Role::Gestionnaire, Role::Administrateur] as $i => $role) {
            $email = "pro{$i}@gmail.com";
            tap(User::factory()->create(['email' => $email, 'actif' => true]), fn (User $u) => $u->assignRole($role->value));
            $this->googleRepond($email, sub: "pro-{$i}");

            $this->retourDeGoogle()->assertRedirect(route('connexion'))->assertSessionHasErrors('identifiant');
            $this->assertGuest();
        }
    }

    public function test_retour_refuse_sans_etat_valide_ou_email_non_verifie(): void
    {
        $this->googleRepond(verifie: false);
        $this->get('/connexion/google/retour?code=x&state=faux')->assertRedirect(route('connexion'))->assertSessionHasErrors('identifiant');
        $this->retourDeGoogle()->assertRedirect(route('connexion'))->assertSessionHasErrors('identifiant');
        $this->assertSame(0, User::count());
        $this->assertGuest();
    }

    public function test_l_application_se_connecte_avec_l_id_token_google(): void
    {
        $this->googleRepond();
        $this->getJson('/api/v1/configuration')->assertOk()
            ->assertJsonPath('data.connexion.google', true)->assertJsonPath('data.connexion.google_client_id', self::CLIENT_WEB);

        $jeton = $this->postJson('/api/v1/auth/google', ['id_token' => 'jeton-id', 'appareil' => 'Tecno Spark'])->assertOk()
            ->assertJsonPath('nouveau_compte', true)
            ->assertJsonPath('utilisateur.email', 'awa@gmail.com')
            ->assertJsonPath('utilisateur.espace', 'client')
            ->json('jeton');
        $this->withToken($jeton)->getJson('/api/v1/moi')->assertOk()->assertJsonPath('data.email', 'awa@gmail.com');

        // Jeton émis pour une autre application : refusé
        $this->googleRepond(aud: 'autre-appli.apps.googleusercontent.com');
        $this->postJson('/api/v1/auth/google', ['id_token' => 'jeton-id'])->assertUnprocessable()->assertJsonValidationErrors('id_token');
    }
}
