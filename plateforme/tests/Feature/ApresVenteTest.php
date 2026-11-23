<?php

namespace Tests\Feature;

use App\Enums\Formule;
use App\Enums\Role;
use App\Enums\StatutAvis;
use App\Enums\StatutLogement;
use App\Enums\StatutReservation;
use App\Enums\TypeLogement;
use App\Enums\TypeMouvement;
use App\Models\Logement;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\DemandeAvis;
use App\Notifications\RappelArrivee;
use App\Notifications\ReservationAnnulee;
use App\Notifications\ReservationReportee;
use App\Services\Disponibilites;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\AvecAbonnement;
use Tests\TestCase;

/** Après-vente (lot 6). Aujourd'hui = jeudi 12 novembre 2026, 09:00. */
class ApresVenteTest extends TestCase
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
        Notification::fake();

        $this->proprio = tap(User::factory()->create(['name' => 'Rodrigue Houngbo']), fn (User $u) => $u->assignRole(Role::Proprietaire->value));
        $this->abonner($this->proprio);
        $this->client = tap(User::factory()->create(['name' => 'Mireille Adjovi']), fn (User $u) => $u->assignRole(Role::Client->value));
        $this->logement = Logement::create([
            'proprietaire_id' => $this->proprio->id, 'titre' => 'Studio Lagune', 'slug' => 'studio-lagune', 'type' => TypeLogement::Studio,
            'ville' => 'Cotonou', 'quartier' => 'Fidjrossè', 'capacite' => 2, 'statut' => StatutLogement::Publie, 'temps_preparation_minutes' => 60,
        ]);
        $this->logement->tarifs()->createMany([
            ['formule' => Formule::Heure, 'prix' => 3500, 'heure_ouverture' => '08:00', 'heure_fermeture' => '22:00', 'duree_minimum_heures' => 2],
            ['formule' => Formule::Journee, 'prix' => 18000, 'heure_arrivee' => '12:00', 'heure_depart' => '11:00'],
        ]);
    }

    /** Réservation à l'heure, 3 h : location 10 500, services 5 000, commission 775, total 16 275. */
    private function reservation(string $debut, string $fin, StatutReservation $statut = StatutReservation::Confirmee, array $autres = []): Reservation
    {
        return Reservation::create($autres + [
            'reference' => Reservation::nouvelleReference(), 'client_id' => $this->client->id, 'logement_id' => $this->logement->id,
            'formule' => Formule::Heure, 'debut' => $debut, 'fin' => $fin, 'voyageurs' => 2, 'statut' => $statut,
            'montant_location' => 10500, 'montant_services' => 5000, 'taux_commission' => 5, 'montant_commission' => 775,
            'montant_total' => 16275, 'services_beneficiaire' => 'proprietaire', 'code_secours' => '482913',
        ]);
    }

    /* ---------- Report ---------- */

    public function test_le_client_reporte_vers_un_creneau_libre_et_recoit_un_nouveau_qr_code(): void
    {
        $r = $this->reservation('2026-11-14 14:00', '2026-11-14 17:00');
        $this->reservation('2026-11-15 10:00', '2026-11-15 13:00');      // le 15 au matin est pris
        $ancienJeton = $r->jetonQr();
        $this->actingAs($this->client);

        $this->get("/mes-reservations/{$r->reference}/reporter?date=2026-11-15")->assertOk()
            ->assertSee('Reporter ma réservation')->assertSee('14:00 → 17:00')
            // 11:00 touche la réservation du matin : affichée barrée, impossible à choisir
            ->assertSee('slot--pris', false)->assertSee('value="11:00" disabled', false);

        $this->post("/mes-reservations/{$r->reference}/reporter", ['date' => '2026-11-15', 'option' => '11:00', 'accord' => '1'])
            ->assertSessionHasErrors('report');                            // 11:00 touche la réservation du matin
        $this->post("/mes-reservations/{$r->reference}/reporter", ['date' => '2026-11-15', 'option' => '15:00'])
            ->assertSessionHasErrors('accord');

        $this->post("/mes-reservations/{$r->reference}/reporter", ['date' => '2026-11-15', 'option' => '15:00', 'accord' => '1'])
            ->assertRedirect("/mes-reservations/{$r->reference}");

        $r->refresh();
        $this->assertSame('2026-11-15 15:00', $r->debut->format('Y-m-d H:i'));
        $this->assertSame('2026-11-15 18:00', $r->fin->format('Y-m-d H:i'));
        $this->assertSame('2026-11-14 14:00', $r->debut_initial->format('Y-m-d H:i'));
        $this->assertSame(1, $r->nombre_reports);
        $this->assertNull(Reservation::referenceDuJeton($ancienJeton));      // l'ancien QR Code n'est plus valable
        $this->assertSame($r->reference, Reservation::referenceDuJeton($r->jetonQr()));
        Notification::assertSentTo($this->client, ReservationReportee::class);
        Notification::assertSentTo($this->proprio, ReservationReportee::class);
        $this->assertTrue(app(Disponibilites::class)->estLibre($this->logement, CarbonImmutable::parse('2026-11-14 14:00'), CarbonImmutable::parse('2026-11-14 17:00')));
    }

    public function test_le_report_barre_les_heures_prises_et_cache_les_jours_bloques(): void
    {
        $r = $this->reservation('2026-11-14 14:00', '2026-11-14 17:00');
        $this->reservation('2026-11-15 10:00', '2026-11-15 13:00');
        $this->logement->blocages()->create(['date_debut' => '2026-11-16', 'date_fin' => '2026-11-17', 'motif' => 'Travaux']);
        $jours = collect(app(\App\Services\ApresVente::class)->joursDeReport($r))->keyBy(fn ($j) => $j['date']->toDateString());

        $this->assertArrayNotHasKey('2026-11-16', $jours->all());           // jours bloqués : pas proposés
        $this->assertArrayNotHasKey('2026-11-17', $jours->all());
        $creneaux = collect($jours['2026-11-15']['creneaux'])->keyBy('valeur');
        $this->assertFalse($creneaux['11:00']['libre']);                   // prise : barrée
        $this->assertTrue($creneaux['15:00']['libre']);
        $this->assertNotContains('11:00', array_column($jours['2026-11-15']['options'], 'valeur'));

        \Laravel\Sanctum\Sanctum::actingAs($this->client);
        $this->getJson("/api/v1/reservations/{$r->reference}/report")->assertOk()
            ->assertJsonMissing(['date' => '2026-11-16'])
            ->assertJsonFragment(['valeur' => '11:00', 'libelle' => '11:00 → 14:00', 'libre' => false]);
    }

    public function test_pas_de_report_a_moins_de_3_h_ni_au_dela_de_10_jours(): void
    {
        $proche = $this->reservation('2026-11-12 11:00', '2026-11-12 13:00');
        $this->actingAs($this->client)->get("/mes-reservations/{$proche->reference}/reporter")->assertOk()->assertSee('Le report n’est plus possible');
        $this->post("/mes-reservations/{$proche->reference}/reporter", ['date' => '2026-11-13', 'option' => '10:00', 'accord' => '1'])->assertSessionHasErrors('report');

        $r = $this->reservation('2026-11-14 14:00', '2026-11-14 17:00');
        $this->post("/mes-reservations/{$r->reference}/reporter", ['date' => '2026-11-25', 'option' => '14:00', 'accord' => '1'])->assertSessionHasErrors('report');
        $this->post("/mes-reservations/{$r->reference}/reporter", ['date' => '2026-11-24', 'option' => '14:00', 'accord' => '1'])->assertRedirect();
    }

    /* ---------- Annulation ---------- */

    public function test_annulation_plus_de_24_h_avant_rembourse_tout(): void
    {
        $r = $this->reservation('2026-11-14 14:00', '2026-11-14 17:00');
        $this->actingAs($this->client);

        $this->get("/mes-reservations/{$r->reference}/annuler")->assertOk()->assertSee('la location et les services vous sont remboursés')->assertSee('non remboursable');
        $this->post("/mes-reservations/{$r->reference}/annuler", ['motif' => 'Changement de programme'])->assertRedirect();

        $r->refresh();
        $this->assertSame(StatutReservation::Annulee, $r->statut);
        $this->assertSame(15500, $r->montant_rembourse);                  // tout sauf la commission (775)
        $this->assertSame('Changement de programme', $r->motif_annulation);
        $this->assertSame(0, $this->proprio->solde());
        Notification::assertSentTo($this->client, ReservationAnnulee::class);
        Notification::assertSentTo($this->proprio, ReservationAnnulee::class);
    }

    public function test_annulation_tardive_la_location_revient_au_proprietaire(): void
    {
        $r = $this->reservation('2026-11-12 20:00', '2026-11-12 23:00');   // dans 11 h
        $this->actingAs($this->client)->post("/mes-reservations/{$r->reference}/annuler")->assertRedirect();

        $r->refresh();
        $this->assertSame(5000, $r->montant_rembourse);                   // les services ; ni la location ni la commission
        $this->assertSame(10500, $this->proprio->solde());
        $this->assertSame(TypeMouvement::CreditAnnulation, $this->proprio->mouvementsSolde()->first()->type);
    }

    public function test_annulation_tardive_a_la_journee(): void
    {
        $r = $this->reservation('2026-11-14 12:00', '2026-11-17 11:00', autres: [
            'formule' => Formule::Journee, 'montant_location' => 54000, 'montant_services' => 0, 'montant_commission' => 2700, 'montant_total' => 56700,
        ]);
        $this->actingAs($this->client)->post("/mes-reservations/{$r->reference}/annuler")->assertRedirect();

        // 3 jours : la première journée (18 000) et la moitié des deux suivantes (18 000) restent dues
        $this->assertSame(56700 - 2700 - 36000, $r->fresh()->montant_rembourse);   // sans la commission (2 700)
        $this->assertSame(36000, $this->proprio->solde());
    }

    public function test_une_reservation_reportee_ne_peut_plus_etre_annulee(): void
    {
        $r = $this->reservation('2026-11-15 15:00', '2026-11-15 18:00', autres: ['nombre_reports' => 1, 'debut_initial' => '2026-11-14 14:00']);
        $this->actingAs($this->client);

        $this->get("/mes-reservations/{$r->reference}/annuler")->assertRedirect("/mes-reservations/{$r->reference}");
        $this->post("/mes-reservations/{$r->reference}/annuler")->assertRedirect();
        $this->assertSame(StatutReservation::Confirmee, $r->fresh()->statut);
        $this->get("/mes-reservations/{$r->reference}")->assertSee('n’est plus remboursable')->assertDontSee('Annuler ma réservation');
    }

    /* ---------- Avis ---------- */

    public function test_le_client_note_son_sejour_termine_une_seule_fois(): void
    {
        $avenir = $this->reservation('2026-11-14 14:00', '2026-11-14 17:00');
        $passe = $this->reservation('2026-11-10 10:00', '2026-11-10 13:00', StatutReservation::Terminee);
        $this->actingAs($this->client);

        $this->post("/mes-reservations/{$avenir->reference}/avis", ['note' => 5])->assertSessionHasErrors('note');
        $this->post("/mes-reservations/{$passe->reference}/avis", ['note' => 4, 'commentaire' => 'Calme et propre.'])->assertRedirect();
        $this->post("/mes-reservations/{$passe->reference}/avis", ['note' => 1])->assertSessionHasErrors('note');

        $avis = $passe->fresh()->avis;
        $this->assertSame(4, $avis->note);
        $this->assertSame(StatutAvis::EnAttente, $avis->statut);         // publié après relecture par VIVI
        $this->get("/mes-reservations/{$passe->reference}")->assertSee('Il sera publié après vérification');
    }

    /* ---------- Tâches automatiques ---------- */

    public function test_fin_de_creneau_sejour_termine_ou_client_absent(): void
    {
        $enCours = $this->reservation('2026-11-12 05:00', '2026-11-12 08:00', StatutReservation::EnCours, ['scanne_le' => '2026-11-12 05:05']);
        $absent = $this->reservation('2026-11-12 06:00', '2026-11-12 08:30');

        $this->artisan('vivi:cloturer-sejours')->assertSuccessful();

        $this->assertSame(StatutReservation::Terminee, $enCours->fresh()->statut);
        Notification::assertSentTo($this->client, DemandeAvis::class);
        $this->assertSame(StatutReservation::Consommee, $absent->fresh()->statut);
        $this->assertSame(15500, $this->proprio->solde());                // la location et les services restent acquis
    }

    public function test_le_rappel_de_la_veille_part_une_seule_fois(): void
    {
        $r = $this->reservation('2026-11-13 10:00', '2026-11-13 13:00');   // dans 25 h

        $this->artisan('vivi:rappels')->assertSuccessful();
        $this->artisan('vivi:rappels')->assertSuccessful();

        Notification::assertSentToTimes($this->client, RappelArrivee::class, 1);
        $this->assertNotNull($r->fresh()->rappel_envoye_le);
    }
}
