<?php

namespace Tests\Feature;

use App\Enums\Formule;
use App\Enums\Role;
use App\Enums\StatutLogement;
use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Enums\TypeLogement;
use App\Models\Logement;
use App\Models\Paiement;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use App\Notifications\NouvelleReservation;
use App\Notifications\ReservationConfirmee;
use App\Paiement\EtatTransaction;
use App\Paiement\Passerelle;
use App\Services\Reservations;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\AvecAbonnement;
use Tests\TestCase;

/** Tunnel de réservation et paiement (lot 4). Aujourd'hui = jeudi 12 novembre 2026, 09:00. */
class PaiementTest extends TestCase
{
    use AvecAbonnement, RefreshDatabase;

    private Logement $logement;

    private User $client;

    private User $proprio;

    private Service $petitDej;

    /** Séjour demandé depuis la fiche : vendredi 13, 14:00, 3 heures. */
    private const DEMANDE = ['date' => '2026-11-13', 'formule' => 'heure', 'heure' => '14:00', 'duree' => 3, 'voyageurs' => 2];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00'));
        Notification::fake();

        $this->proprio = tap(User::factory()->create(['name' => 'Rodrigue Houngbo']), fn (User $u) => $u->assignRole(Role::Proprietaire->value));
        $this->abonner($this->proprio);
        $this->client = tap(User::factory()->create(['name' => 'Mireille Adjovi', 'telephone' => null]), fn (User $u) => $u->assignRole(Role::Client->value));

        $this->logement = Logement::create([
            'proprietaire_id' => $this->proprio->id, 'titre' => 'Studio Lagune', 'slug' => 'studio-lagune', 'type' => TypeLogement::Studio,
            'ville' => 'Cotonou', 'quartier' => 'Fidjrossè', 'adresse' => 'Rue 12.345', 'capacite' => 2, 'statut' => StatutLogement::Publie,
            'temps_preparation_minutes' => 60,
        ]);
        $this->logement->tarifs()->create(['formule' => Formule::Heure, 'prix' => 3500, 'heure_ouverture' => '08:00', 'heure_fermeture' => '22:00', 'duree_minimum_heures' => 2]);
        $this->petitDej = Service::create(['nom' => 'Petit-déjeuner', 'prix' => 2500, 'unite' => 'par personne', 'actif' => true]);
        $this->logement->services()->attach($this->petitDej);
    }

    private function bloquer(?User $qui = null): Reservation
    {
        $this->actingAs($qui ?? $this->client)->post('/reserver/studio-lagune', self::DEMANDE)->assertRedirect();

        return Reservation::where('client_id', ($qui ?? $this->client)->id)->latest('id')->firstOrFail();
    }

    public function test_l_email_donne_au_paiement_recoit_la_confirmation_avec_l_adresse_et_l_itineraire(): void
    {
        $this->logement->update(['latitude' => 6.353, 'longitude' => 2.379]);
        $this->client->update(['email' => null]);   // inscrit par téléphone
        $r = $this->bloquer();
        $this->post("/reservations/{$r->reference}/payer", [
            'nom' => 'Mireille Adjovi', 'telephone' => '01 97 00 00 11', 'email' => 'Mireille@Exemple.com', 'cgv' => '1',
        ])->assertRedirect();
        $this->assertSame('mireille@exemple.com', $this->client->fresh()->email);

        app(Reservations::class)->confirmer($r->paiements()->latest('id')->first(), new EtatTransaction(EtatTransaction::PAYEE));

        Notification::assertSentTo($this->client->fresh(), ReservationConfirmee::class, function (ReservationConfirmee $n, array $canaux, $notifiable) {
            $html = (string) $n->toMail($notifiable)->render();

            return in_array('mail', $canaux, true) && in_array('database', $canaux, true)
                && str_contains($html, 'Rue 12.345, Fidjrossè, Cotonou')
                && str_contains($html, 'google.com/maps/dir/?api=1&amp;destination=6.353')
                && str_contains($n->toSms($notifiable), 'Adresse : Rue 12.345');
        });

        // Un email déjà pris par un autre compte est refusé
        User::factory()->create(['email' => 'pris@exemple.com']);
        $autre = tap(User::factory()->create(['telephone' => null, 'email' => null]), fn (User $u) => $u->assignRole(Role::Client->value));
        $r2 = $this->actingAs($autre)->post('/reserver/studio-lagune', ['date' => '2026-11-14'] + self::DEMANDE);
        $res = Reservation::where('client_id', $autre->id)->latest('id')->first();
        $this->post("/reservations/{$res->reference}/payer", ['nom' => 'Autre', 'telephone' => '01 97 00 00 12', 'email' => 'pris@exemple.com', 'cgv' => '1'])
            ->assertSessionHasErrors('email');
    }

    private function payer(Reservation $r, array $services = []): Paiement
    {
        $this->post("/reservations/{$r->reference}/payer", [
            'nom' => 'Mireille Adjovi', 'telephone' => '01 97 00 00 11', 'cgv' => '1', 'services' => $services,
        ])->assertRedirect();

        return $r->paiements()->latest('id')->firstOrFail();
    }

    private function simuler(Paiement $p, string $choix): TestResponse
    {
        $this->post("/paiements/{$p->id}/simulation", ['choix' => $choix, 'moyen' => 'MTN Mobile Money'])->assertRedirect("/paiements/{$p->id}/retour");

        return $this->get("/paiements/{$p->id}/retour");
    }

    /* ---------- Retenue du créneau ---------- */

    public function test_il_faut_etre_connecte_pour_reserver(): void
    {
        $this->post('/reserver/studio-lagune', self::DEMANDE)->assertRedirect('/connexion');
        $this->assertSame(0, Reservation::count());
    }

    public function test_continuer_retient_le_creneau_et_ouvre_le_tunnel(): void
    {
        $r = $this->bloquer();

        $this->assertSame(StatutReservation::EnAttentePaiement, $r->statut);
        $this->assertSame('2026-11-13 14:00', $r->debut->format('Y-m-d H:i'));
        $this->assertSame('2026-11-12 09:15', $r->bloque_jusqua->format('Y-m-d H:i'));   // maintenant + 15 minutes
        $this->assertSame(11025, $r->montant_total);                                       // 10 500 + 5 %
        $this->assertMatchesRegularExpression('/^\d{6}$/', $r->code_secours);

        $this->get("/reservations/{$r->reference}/paiement")->assertOk()
            ->assertSee('Ajoutez vos services')->assertSee('Petit-déjeuner')->assertSee('Créneau bloqué pour vous');
    }

    public function test_un_creneau_retenu_ne_peut_pas_etre_vendu_deux_fois(): void
    {
        $this->bloquer();
        $autre = tap(User::factory()->create(), fn (User $u) => $u->assignRole(Role::Client->value));

        $this->actingAs($autre)->post('/reserver/studio-lagune', self::DEMANDE)
            ->assertRedirect()->assertSessionHasErrors('reservation');
        $this->assertSame(1, Reservation::count());
    }

    public function test_le_client_qui_revient_en_arriere_remplace_sa_retenue(): void
    {
        $premiere = $this->bloquer();
        $seconde = $this->bloquer();

        $this->assertNotSame($premiere->id, $seconde->id);
        $this->assertSame(StatutReservation::Annulee, $premiere->fresh()->statut);
        $this->assertSame(StatutReservation::EnAttentePaiement, $seconde->statut);
    }

    /* ---------- Services et paiement ---------- */

    public function test_le_prix_avec_services_est_calcule_par_le_serveur(): void
    {
        $r = $this->bloquer();
        $cache = Service::create(['nom' => 'Chauffeur', 'prix' => 7000, 'unite' => 'le trajet', 'actif' => true]);   // pas proposé ici

        $this->getJson("/reservations/{$r->reference}/devis?services[{$this->petitDej->id}]=2&services[{$cache->id}]=1")
            ->assertOk()
            ->assertJsonPath('services', 5000)
            ->assertJsonPath('commission', 525)          // 5 % de la location (10 500), rien sur les services
            ->assertJsonPath('total', 16025);
    }

    public function test_paiement_reussi_confirme_la_reservation_et_previent_client_et_proprietaire(): void
    {
        $r = $this->bloquer();
        $p = $this->payer($r, [$this->petitDej->id => 2]);

        $this->assertSame('simulation', $p->passerelle);
        $this->assertSame(16025, $p->montant);
        $this->assertSame(16025, $r->fresh()->montant_total);
        $this->assertSame(1, $r->services()->count());
        $this->assertSame('0197000011', $this->client->fresh()->telephone);   // numéro enregistré sur le compte

        $this->simuler($p, 'reussi')->assertRedirect("/mes-reservations/{$r->reference}")->assertSessionHas('confirmee');

        $r->refresh();
        $this->assertSame(StatutReservation::Confirmee, $r->statut);
        $this->assertNull($r->bloque_jusqua);
        $this->assertSame(StatutPaiement::Reussi, $p->fresh()->statut);
        $this->assertSame('MTN Mobile Money', $p->fresh()->moyen);
        Notification::assertSentTo($this->client, ReservationConfirmee::class);
        Notification::assertSentTo($this->proprio, NouvelleReservation::class);

        $this->withSession(['confirmee' => true])->get("/mes-reservations/{$r->reference}")->assertOk()
            ->assertSee('Réservation confirmée')
            ->assertSee('<svg', false)                    // QR Code
            ->assertSee('Rue 12.345')                      // adresse exacte, après paiement
            ->assertSee('Code de secours '.$r->code_secours);
        $this->get("/mes-reservations/{$r->reference}/recu")->assertOk()->assertSee('Reçu de paiement')->assertSee("16\u{00A0}025\u{00A0}FCFA");
    }

    public function test_paiement_refuse_laisse_le_client_reessayer(): void
    {
        $r = $this->bloquer();
        $p = $this->payer($r);

        $this->simuler($p, 'refuse')->assertRedirect("/reservations/{$r->reference}/paiement")->assertSessionHasErrors('paiement');
        $this->assertSame(StatutPaiement::Echoue, $p->fresh()->statut);
        $this->assertSame(StatutReservation::EnAttentePaiement, $r->fresh()->statut);

        $nouveau = $this->payer($r);
        $this->assertNotSame($p->id, $nouveau->id);
        $this->simuler($nouveau, 'reussi');
        $this->assertSame(StatutReservation::Confirmee, $r->fresh()->statut);
    }

    public function test_la_confirmation_n_a_lieu_qu_une_fois(): void
    {
        $r = $this->bloquer();
        $p = $this->payer($r);
        $etat = new EtatTransaction(EtatTransaction::PAYEE, 'Moov Money');

        app(Reservations::class)->confirmer($p, $etat);
        app(Reservations::class)->confirmer($p, $etat);   // retour du client puis webhook

        Notification::assertSentToTimes($this->client, ReservationConfirmee::class, 1);
    }

    public function test_paiement_arrive_apres_expiration_et_creneau_repris_rembourse(): void
    {
        $r = $this->bloquer();
        $p = $this->payer($r);
        $this->travel(20)->minutes();                         // retenue expirée
        $autre = tap(User::factory()->create(), fn (User $u) => $u->assignRole(Role::Client->value));
        $this->bloquer($autre);                               // le créneau est repris

        app(Reservations::class)->confirmer($p, new EtatTransaction(EtatTransaction::PAYEE));

        $r->refresh();
        $this->assertSame(StatutReservation::Annulee, $r->statut);
        $this->assertSame($r->montant_total, $r->montant_rembourse);
        Notification::assertNotSentTo($this->client, ReservationConfirmee::class);
    }

    public function test_les_retenues_abandonnees_sont_liberees(): void
    {
        $r = $this->bloquer();
        $this->travel(2)->hours();

        $this->artisan('vivi:liberer-creneaux')->assertSuccessful();

        $this->assertSame(StatutReservation::Annulee, $r->fresh()->statut);
    }

    /* ---------- Webhook FedaPay ---------- */

    public function test_le_webhook_verifie_la_signature_puis_relit_la_transaction(): void
    {
        config(['vivi.paiement.fedapay.cle_webhook' => 'wh_sandbox_test']);
        $r = $this->bloquer();
        $p = $r->paiements()->create(['payeur_id' => $this->client->id, 'passerelle' => 'fedapay', 'reference_passerelle' => '4242',
            'montant' => $r->montant_total, 'statut' => StatutPaiement::EnAttente]);
        $this->app->instance(Passerelle::class, new class implements Passerelle
        {
            public function nom(): string
            {
                return 'fedapay';
            }

            public function demarrer(Paiement $paiement, string $urlRetour): string
            {
                return '';
            }

            public function consulter(Paiement $paiement): EtatTransaction
            {
                return new EtatTransaction(EtatTransaction::PAYEE, 'Celtiis Cash');
            }
        });

        $corps = json_encode(['name' => 'transaction.approved', 'entity' => ['id' => 4242, 'status' => 'approved']]);
        $t = time();

        $this->call('POST', '/webhooks/fedapay', [], [], [], ['HTTP_X-FEDAPAY-SIGNATURE' => "t={$t},s=faux", 'CONTENT_TYPE' => 'application/json'], $corps)
            ->assertStatus(400);
        $this->assertSame(StatutReservation::EnAttentePaiement, $r->fresh()->statut);

        $signature = hash_hmac('sha256', "{$t}.{$corps}", 'wh_sandbox_test');
        $this->call('POST', '/webhooks/fedapay', [], [], [], ['HTTP_X-FEDAPAY-SIGNATURE' => "t={$t},s={$signature}", 'CONTENT_TYPE' => 'application/json'], $corps)
            ->assertOk();
        $this->assertSame(StatutReservation::Confirmee, $r->fresh()->statut);
        $this->assertSame('Celtiis Cash', $p->fresh()->moyen);
    }

    /* ---------- QR Code et confidentialité ---------- */

    public function test_le_qr_code_est_signe_et_infalsifiable(): void
    {
        $r = $this->bloquer();
        $jeton = $r->jetonQr();

        $this->assertSame($r->reference, Reservation::referenceDuJeton($jeton));
        $this->assertNull(Reservation::referenceDuJeton(substr($jeton, 0, -1).(str_ends_with($jeton, 'a') ? 'b' : 'a')));
        $this->assertNull(Reservation::referenceDuJeton('VIVI1.'.$r->reference.'.00000000000000000000'));
    }

    public function test_un_client_ne_voit_pas_la_reservation_d_un_autre(): void
    {
        $r = $this->bloquer();
        $autre = tap(User::factory()->create(), fn (User $u) => $u->assignRole(Role::Client->value));

        $this->actingAs($autre)->get("/mes-reservations/{$r->reference}")->assertNotFound();
        $this->get("/reservations/{$r->reference}/paiement")->assertNotFound();
    }
}
