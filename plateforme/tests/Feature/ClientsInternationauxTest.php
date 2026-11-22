<?php

namespace Tests\Feature;

use App\Enums\Formule;
use App\Enums\Role;
use App\Enums\StatutLogement;
use App\Enums\TypeLogement;
use App\Models\Logement;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Telephone;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AvecAbonnement;
use Tests\TestCase;

/** VIVI est international : numéro béninois au Bénin, email ailleurs ; numéro WhatsApp de tout pays à la réservation. */
class ClientsInternationauxTest extends TestCase
{
    use AvecAbonnement, RefreshDatabase;

    private User $client;

    private User $proprio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00'));
        Notification::fake();
        $this->proprio = tap(User::factory()->create(['name' => 'Rodrigue Houngbo', 'actif' => true]), fn (User $u) => $u->assignRole(Role::Proprietaire->value));
        $this->abonner($this->proprio);
        $this->client = tap(User::factory()->create(['name' => 'Claire Martin', 'telephone' => null, 'email' => 'claire@exemple.fr', 'actif' => true]),
            fn (User $u) => $u->assignRole(Role::Client->value));
        $logement = Logement::create([
            'proprietaire_id' => $this->proprio->id, 'titre' => 'Studio Lagune', 'slug' => 'studio-lagune', 'type' => TypeLogement::Studio,
            'ville' => 'Cotonou', 'quartier' => 'Fidjrossè', 'capacite' => 2, 'statut' => StatutLogement::Publie, 'temps_preparation_minutes' => 60,
        ]);
        $logement->tarifs()->create(['formule' => Formule::Heure, 'prix' => 3500, 'heure_ouverture' => '08:00', 'heure_fermeture' => '22:00', 'duree_minimum_heures' => 2]);
    }

    private function bloquer(): Reservation
    {
        $this->actingAs($this->client)->post('/reserver/studio-lagune', ['date' => '2026-11-13', 'formule' => 'heure', 'heure' => '14:00', 'duree' => 3, 'voyageurs' => 2])->assertRedirect();

        return Reservation::where('client_id', $this->client->id)->latest('id')->firstOrFail();
    }

    public function test_numeros_whatsapp_de_tous_pays(): void
    {
        $this->assertSame('+33612345678', Telephone::whatsapp('+33 6 12 34 56 78'));
        $this->assertSame('+33612345678', Telephone::whatsapp('0033 6 12 34 56 78'));
        $this->assertSame('+2290197000000', Telephone::whatsapp('01 97 00 00 00'));          // béninois sans indicatif
        $this->assertSame('+2290197000000', Telephone::whatsapp('+229 01 97 00 00 00'));
        $this->assertSame('+12025550123', Telephone::whatsapp('+1 (202) 555-0123'));
        $this->assertNull(Telephone::whatsapp('06 12 34 56 78'));                             // sans indicatif : pays inconnu
        $this->assertNull(Telephone::whatsapp('+12'));
        $this->assertSame('0197000000', Telephone::beninoisDe('+2290197000000'));
        $this->assertNull(Telephone::beninoisDe('+33612345678'));
        $this->assertSame('https://wa.me/33612345678', Telephone::lienWhatsapp('+33612345678'));
        $this->assertSame('+229 01 97 00 00 00', Telephone::lisibleInternational('+2290197000000'));
    }

    public function test_inscription_au_benin_par_numero_ou_a_l_international_par_email(): void
    {
        $this->get('/connexion?mode=inscription')->assertOk()
            ->assertSee('Au Bénin')->assertSee('International')->assertSee('Numéro béninois ou email')->assertSee('Adresse email')
            ->assertSee('Créer mon compte')->assertDontSee('Recevoir le code');
        $this->get('/connexion')->assertOk()->assertSee('Se connecter');

        $inscription = ['mode' => 'inscription', 'nom' => 'Claire Dupont', 'role' => 'client'];
        // Au Bénin : le numéro ou l'email, au choix
        $this->post('/connexion', $inscription + ['pays' => 'benin', 'identifiant' => '01 97 00 00 44'])->assertRedirect(route('connexion.code'));
        $this->post('/connexion', $inscription + ['pays' => 'benin', 'identifiant' => 'koffi@exemple.bj'])->assertRedirect(route('connexion.code'));
        $this->post('/connexion', $inscription + ['pays' => 'benin', 'identifiant' => '+33 6 12 34 56 78'])->assertSessionHasErrors('identifiant');

        // International : l'email seulement
        $this->post('/connexion', $inscription + ['pays' => 'international', 'identifiant' => '01 97 00 00 45'])
            ->assertSessionHasErrors(['identifiant' => 'Hors du Bénin, indiquez votre adresse email (ex. vous@exemple.com).']);
        $this->post('/connexion', $inscription + ['pays' => 'international', 'identifiant' => 'claire.dupont@exemple.fr'])->assertRedirect(route('connexion.code'));

        // Application : le même choix est accepté par l'API (facultatif)
        $this->postJson('/api/v1/auth/code', $inscription + ['pays' => 'international', 'identifiant' => '01 97 00 00 46'])->assertUnprocessable()->assertJsonValidationErrors('identifiant');
    }

    public function test_un_client_etranger_reserve_avec_son_numero_whatsapp(): void
    {
        $r = $this->bloquer();
        $this->post("/reservations/{$r->reference}/payer", ['nom' => 'Claire Martin', 'cgv' => '1'])->assertSessionHasErrors('whatsapp');
        $this->post("/reservations/{$r->reference}/payer", ['nom' => 'Claire Martin', 'whatsapp' => '06 12 34 56 78', 'cgv' => '1'])->assertSessionHasErrors('whatsapp');

        $this->post("/reservations/{$r->reference}/payer", ['nom' => 'Claire Martin', 'whatsapp' => '+33 6 12 34 56 78', 'cgv' => '1'])->assertRedirect();
        $this->assertSame('+33612345678', $r->fresh()->whatsapp);
        $this->assertSame('+33612345678', $this->client->fresh()->whatsapp);
        $this->assertNull($this->client->fresh()->telephone);   // pas de numéro béninois : pas de SMS

        // Réservation suivante : le numéro est proposé
        $this->get('/reservations/'.$this->bloquer()->reference.'/paiement')->assertOk()->assertSee('+33612345678')->assertSee('Numéro WhatsApp');

        // Le propriétaire le voit (réservation payée) avec un lien qui ouvre WhatsApp, sur le site et dans l'application
        $r->update(['statut' => \App\Enums\StatutReservation::Confirmee]);
        $this->actingAs($this->proprio)->get('/pro/reservations')->assertOk()->assertSee('https://wa.me/33612345678', false);
        Sanctum::actingAs($this->proprio);
        $this->getJson('/api/v1/pro/reservations/'.$r->reference)->assertOk()
            ->assertJsonPath('data.client.whatsapp', '+33612345678')->assertJsonPath('data.client.lien_whatsapp', 'https://wa.me/33612345678');
    }

    public function test_un_numero_whatsapp_beninois_devient_le_telephone_du_compte(): void
    {
        $r = $this->bloquer();
        Sanctum::actingAs($this->client);
        $this->postJson("/api/v1/reservations/{$r->reference}/payer", ['nom' => 'Claire Martin', 'whatsapp' => '01 97 00 00 55', 'cgv' => true])->assertOk();

        $this->assertSame('+2290197000055', $r->fresh()->whatsapp);
        $this->assertSame('0197000055', $this->client->fresh()->telephone);   // QR Code et rappels par SMS

        // Anciennes versions de l'application : « telephone » reste accepté
        $autre = $this->bloquer();
        Sanctum::actingAs($this->client);
        $this->postJson("/api/v1/reservations/{$autre->reference}/payer", ['nom' => 'Claire Martin', 'telephone' => '01 97 00 00 55', 'cgv' => true])->assertOk();
        $this->assertSame('+2290197000055', $autre->fresh()->whatsapp);
    }
}
