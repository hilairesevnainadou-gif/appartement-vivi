<?php

namespace Tests\Feature;

use App\Enums\Formule;
use App\Enums\Role;
use App\Enums\StatutLogement;
use App\Enums\StatutReservation;
use App\Enums\TypeLogement;
use App\Enums\TypeMarketing;
use App\Enums\TypeMouvement;
use App\Filament\Resources\Logements\Pages\EditLogement;
use App\Filament\Resources\Offres\Pages\ManageOffres;
use App\Models\Appareil;
use App\Models\Logement;
use App\Models\Offre;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\CommissionAgent;
use App\Notifications\DecisionLogement;
use App\Notifications\OffreVivi;
use App\Notifications\RetraitTraite;
use App\Notifications\SejourCommence;
use App\Services\AgentsMarketing;
use App\Services\Arrivees;
use App\Services\Caisse;
use App\Services\Offres;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as RequeteHttp;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Concerns\AvecAbonnement;
use Tests\TestCase;

/** Espace Notifications de l'application : nouveaux événements, catégories et écrans, push Firebase, offres. */
class NotificationsApplicationTest extends TestCase
{
    use AvecAbonnement, RefreshDatabase;

    private User $proprio;

    private User $client;

    private Logement $logement;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00'));

        $this->proprio = tap(User::factory()->create(['name' => 'Rodrigue Houngbo', 'actif' => true]), fn (User $u) => $u->assignRole(Role::Proprietaire->value));
        $this->abonner($this->proprio);
        $this->client = tap(User::factory()->create(['name' => 'Mireille Adjovi', 'actif' => true]), fn (User $u) => $u->assignRole(Role::Client->value));
        $this->logement = Logement::create([
            'proprietaire_id' => $this->proprio->id, 'titre' => 'Studio Lagune', 'slug' => 'studio-lagune', 'type' => TypeLogement::Studio,
            'ville' => 'Cotonou', 'quartier' => 'Fidjrossè', 'capacite' => 2, 'statut' => StatutLogement::Publie, 'temps_preparation_minutes' => 60,
            'marketing_type' => TypeMarketing::Pourcentage, 'marketing_valeur' => 10,
        ]);
        $this->logement->tarifs()->create(['formule' => Formule::Heure, 'prix' => 3500, 'heure_ouverture' => '08:00', 'heure_fermeture' => '22:00', 'duree_minimum_heures' => 2]);
    }

    /** Réservation par l'API (avec ou sans lien d'agent), payée, puis QR Code scanné par le propriétaire. */
    private function sejourScanne(?string $code = null): Reservation
    {
        Sanctum::actingAs($this->client);
        $reference = $this->postJson("/api/v1/logements/{$this->logement->slug}/reserver", [
            'date' => '2026-11-13', 'formule' => 'heure', 'heure' => '14:00', 'duree' => 3, 'voyageurs' => 2, 'agent' => $code,
        ])->assertCreated()->json('data.reference');
        $r = Reservation::where('reference', $reference)->firstOrFail();
        $r->update(['statut' => StatutReservation::Confirmee, 'debut' => now()->addMinutes(30), 'fin' => now()->addHours(3)]);
        app(Arrivees::class)->demarrer($this->proprio, $r);

        return $r->fresh();
    }

    /** Compte de service Firebase de test (clé RSA jetable des tests, pour que la signature du jeton fonctionne). */
    private function firebaseConfigure(): void
    {
        $pem = file_get_contents(base_path('tests/Fixtures/firebase-test.pem'));
        $chemin = storage_path('framework/testing/firebase-test.json');
        @mkdir(dirname($chemin), 0777, true);
        file_put_contents($chemin, json_encode(['client_email' => 'vivi@test.iam.gserviceaccount.com', 'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token']));
        config(['services.firebase.projet' => 'vivi-test', 'services.firebase.compte_service' => $chemin]);
    }

    public function test_le_scan_previent_le_client_et_l_agent(): void
    {
        Notification::fake();
        $agent = app(AgentsMarketing::class)->designer($this->proprio, 'Awa Dossou', 'awa@exemple.com', [$this->logement->id]);

        $r = $this->sejourScanne($agent->code_agent);

        Notification::assertSentTo($this->client, SejourCommence::class, fn ($n) => $n->reservation->is($r));
        Notification::assertSentTo($agent, CommissionAgent::class, fn ($n) => $n->montant === 1050);
    }

    public function test_l_application_lit_categories_ecrans_compteur_et_suppression(): void
    {
        $r = $this->sejourScanne();   // vraie notification « séjour commencé » enregistrée pour le client
        $this->client->notify(new OffreVivi(Offre::create(['titre' => 'Promo week-end', 'texte' => '-20 % samedi.', 'cible' => 'clients'])));

        Sanctum::actingAs($this->client);
        $liste = $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('meta.non_lues', 2)->json('data');
        $sejour = collect($liste)->firstWhere('type', 'SejourCommence');
        $this->assertSame('reservation', $sejour['categorie']);
        $this->assertSame(['ecran' => 'reservation', 'reference' => $r->reference], $sejour['cible']);
        $this->assertStringContainsString('Studio Lagune', $sejour['texte']);
        $this->assertSame(['ecran' => 'offre', 'id' => Offre::first()->id], collect($liste)->firstWhere('type', 'OffreVivi')['cible']);

        $this->getJson('/api/v1/notifications?categorie=offre')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.titre', 'Promo week-end');
        $this->getJson('/api/v1/notifications/compteur')->assertOk()
            ->assertJsonPath('data.non_lues', 2)->assertJsonPath('data.par_categorie.reservation', 1)->assertJsonPath('data.par_categorie.offre', 1);
        $this->getJson('/api/v1/notifications?depuis='.urlencode(now()->addMinute()->toAtomString()))->assertOk()->assertJsonCount(0, 'data');

        $this->postJson('/api/v1/notifications/'.$sejour['id'].'/lu')->assertOk();
        $this->getJson('/api/v1/notifications?non_lues=1')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson('/api/v1/notifications/'.$sejour['id'])->assertOk();
        $this->getJson('/api/v1/notifications/'.$sejour['id'])->assertNotFound();
    }

    public function test_le_telephone_s_enregistre_et_recoit_le_push(): void
    {
        $this->firebaseConfigure();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'jeton-google', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::sequence()
                ->push(['name' => 'projects/vivi-test/messages/1'])
                ->push(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404),
        ]);

        Sanctum::actingAs($this->client);
        $this->getJson('/api/v1/configuration')->assertOk()->assertJsonPath('data.push', true);
        $jeton = str_repeat('fcm-jeton-', 5);
        $this->postJson('/api/v1/appareils', ['jeton' => $jeton, 'nom' => 'Tecno Spark 10'])->assertCreated()->assertJsonPath('data.push_actif', true);
        $this->postJson('/api/v1/appareils', ['jeton' => $jeton])->assertOk();   // même téléphone : pas de doublon
        $this->assertSame(1, Appareil::count());

        $r = $this->sejourScanne();

        Http::assertSent(function (RequeteHttp $req) use ($jeton, $r) {
            if (! str_contains($req->url(), 'fcm.googleapis.com/v1/projects/vivi-test/messages:send')) {
                return false;
            }
            $data = (array) $req['message']['data'];

            return $req['message']['token'] === $jeton
                && str_starts_with($req['message']['notification']['title'], 'Bienvenue, votre séjour a commencé')
                && $data['ecran'] === 'reservation' && $data['reference'] === $r->reference && $data['categorie'] === 'reservation'
                && $data['type'] === 'SejourCommence' && isset($data['notification_id']);
        });

        // Application désinstallée : Firebase répond UNREGISTERED, le téléphone est oublié
        $this->client->notify(new SejourCommence($r));
        $this->assertSame(0, Appareil::count());
    }

    public function test_deconnexion_et_retrait_du_telephone(): void
    {
        Sanctum::actingAs($this->client);
        $jeton = str_repeat('a', 40);
        $this->postJson('/api/v1/appareils', ['jeton' => $jeton])->assertCreated();
        $this->deleteJson('/api/v1/appareils', ['jeton' => $jeton])->assertOk();
        $this->assertSame(0, Appareil::count());

        // Un autre compte ne peut pas retirer le téléphone de quelqu'un d'autre
        $this->postJson('/api/v1/appareils', ['jeton' => $jeton])->assertCreated();
        Sanctum::actingAs($this->proprio);
        $this->deleteJson('/api/v1/appareils', ['jeton' => $jeton])->assertOk();
        $this->assertSame(1, Appareil::count());

        // Déconnexion avec jeton_push
        $token = $this->client->createToken('Tecno')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/auth/deconnexion', ['jeton_push' => $jeton])->assertOk();
        $this->assertSame(0, Appareil::count());
    }

    public function test_les_offres_vont_aux_clients_qui_les_acceptent_sans_sms_ni_email(): void
    {
        Notification::fake();
        $refuse = tap(User::factory()->create(['actif' => true, 'recevoir_offres' => false]), fn (User $u) => $u->assignRole(Role::Client->value));

        $offre = Offre::create(['titre' => 'Promo', 'texte' => '-20 %', 'cible' => 'clients']);
        $this->assertSame(1, app(Offres::class)->envoyer($offre));

        Notification::assertSentTo($this->client, OffreVivi::class, fn ($n, array $canaux) => $canaux === ['database']);
        Notification::assertNotSentTo([$refuse, $this->proprio], OffreVivi::class);
        $this->assertSame(1, $offre->fresh()->destinataires);

        // Le client coupe les offres depuis l'application, puis les réactive sur le site
        Sanctum::actingAs($this->client);
        $this->putJson('/api/v1/profil/preferences', ['recevoir_offres' => false])->assertOk()->assertJsonPath('data.recevoir_offres', false);
        $this->assertSame(0, app(Offres::class)->envoyer(Offre::create(['titre' => 'Autre', 'texte' => 'x', 'cible' => 'clients'])));
        $this->actingAs($this->client, 'web')->post('/mon-profil/preferences', ['recevoir_offres' => '1'])->assertRedirect();
        $this->assertTrue($this->client->fresh()->recevoir_offres);
    }

    public function test_l_administrateur_envoie_une_offre_depuis_l_administration(): void
    {
        Notification::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = tap(User::factory()->create(['actif' => true]), fn (User $u) => $u->assignRole(Role::Administrateur->value));
        $this->actingAs($admin);

        Livewire::test(ManageOffres::class)
            ->callAction('create', ['titre' => 'Nuit à -15 %', 'texte' => 'Ce vendredi seulement.', 'cible' => 'clients', 'lien' => url('/logements/studio-lagune')])
            ->assertHasNoFormErrors()
            ->assertNotified('Offre envoyée à 1 personne');

        $offre = Offre::firstOrFail();
        $this->assertSame($admin->id, $offre->envoyee_par);
        Notification::assertSentTo($this->client, OffreVivi::class);

        // Lien hors VIVI refusé
        Livewire::test(ManageOffres::class)
            ->callAction('create', ['titre' => 'x', 'texte' => 'y', 'cible' => 'clients', 'lien' => 'https://ailleurs.example/'])
            ->assertHasFormErrors(['lien']);
    }

    public function test_retrait_traite_et_decision_sur_le_logement_previennent_le_proprietaire(): void
    {
        Notification::fake();
        $this->proprio->mouvementsSolde()->create(['type' => TypeMouvement::CreditSejour, 'montant' => 30000, 'libelle' => 'Séjour']);
        $caisse = app(Caisse::class);
        $admin = tap(User::factory()->create(['actif' => true]), fn (User $u) => $u->assignRole(Role::Administrateur->value));

        $caisse->validerRetrait($caisse->demanderRetrait($this->proprio, 10000), $admin, 'MTN Mobile Money', 'MTN-1');
        $caisse->refuserRetrait($caisse->demanderRetrait($this->proprio, 5000), $admin, 'Numéro incorrect');
        Notification::assertSentToTimes($this->proprio, RetraitTraite::class, 2);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);
        $this->logement->update(['statut' => StatutLogement::EnValidation]);
        Livewire::test(EditLogement::class, ['record' => $this->logement->getKey()])
            ->callAction(TestAction::make('refuser'), ['motif' => 'Photos floues']);
        Notification::assertSentTo($this->proprio, DecisionLogement::class, fn ($n) => $n->logement->statut === StatutLogement::Refuse
            && str_contains($n->toMail($this->proprio)->subject, 'à corriger'));
    }
}
