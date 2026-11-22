<?php

namespace Tests\Feature;

use App\Enums\Formule;
use App\Enums\Role;
use App\Enums\StatutLogement;
use App\Enums\StatutReservation;
use App\Enums\TypeLogement;
use App\Models\Logement;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ApresVente;
use App\Services\Arrivees;
use App\Services\Disponibilites;
use App\Services\ReservationImpossible;
use App\Services\Reservations;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\AvecAbonnement;
use Tests\TestCase;

/** Résidences à plusieurs chambres et réservations saisies hors plateforme. Aujourd'hui = vendredi 13 novembre 2026, 09:00. */
class ResidencesEtReservationsManuellesTest extends TestCase
{
    use AvecAbonnement, RefreshDatabase;

    private User $proprio;

    private Logement $logement;

    private const DEMANDE = ['date' => '2026-11-14', 'formule' => 'heure', 'heure' => '14:00', 'duree' => 3, 'voyageurs' => 2];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-13 09:00'));
        Notification::fake();

        $this->proprio = $this->utilisateur(Role::Proprietaire, 'Rodrigue Houngbo');
        $this->abonner($this->proprio);
        $this->logement = Logement::create([
            'proprietaire_id' => $this->proprio->id, 'titre' => 'Résidence Les Cocotiers', 'slug' => 'residence-les-cocotiers', 'type' => TypeLogement::Studio,
            'ville' => 'Cotonou', 'quartier' => 'Cadjèhoun', 'capacite' => 2, 'statut' => StatutLogement::Publie, 'temps_preparation_minutes' => 60,
        ]);
        $this->logement->tarifs()->create(['formule' => Formule::Heure, 'prix' => 3500, 'heure_ouverture' => '08:00', 'heure_fermeture' => '22:00', 'duree_minimum_heures' => 2]);
        $this->logement->tarifs()->create(['formule' => Formule::Journee, 'prix' => 20000, 'heure_arrivee' => '13:00', 'heure_depart' => '11:00']);
    }

    private function utilisateur(Role $role, string $nom = 'Personne Test'): User
    {
        return tap(User::factory()->create(['name' => $nom, 'actif' => true]), fn (User $u) => $u->assignRole($role->value));
    }

    private function reserverEnLigne(): Reservation
    {
        $client = $this->utilisateur(Role::Client);
        $r = app(Reservations::class)->bloquer($client, $this->logement->fresh(), self::DEMANDE);
        app(Reservations::class)->confirmer(app(Reservations::class)->preparerPaiement($r, [], 'simulation'), new \App\Paiement\EtatTransaction(\App\Paiement\EtatTransaction::PAYEE));

        return $r->fresh();
    }

    /* ---------- Résidence : plusieurs chambres identiques ---------- */

    public function test_une_residence_reste_reservable_tant_qu_une_chambre_est_libre(): void
    {
        $this->logement->update(['unites' => 3]);
        $dispo = app(Disponibilites::class);
        $debut = CarbonImmutable::parse('2026-11-14 14:00');

        $this->reserverEnLigne();
        $this->reserverEnLigne();
        $dispo->oublier();
        $this->assertSame(1, $dispo->unitesLibres($this->logement->fresh(), $debut, $debut->addHours(3)));

        $this->reserverEnLigne();
        $dispo->oublier();
        $this->assertSame(0, $dispo->unitesLibres($this->logement->fresh(), $debut, $debut->addHours(3)));

        $this->expectException(ReservationImpossible::class);
        $this->reserverEnLigne();   // quatrième client : plus de chambre
    }

    public function test_deux_sejours_successifs_n_occupent_qu_une_chambre(): void
    {
        $this->logement->update(['unites' => 2]);
        $dispo = app(Disponibilites::class);
        // 10:00-12:00 puis 15:00-17:00 (préparation 1 h) : une chambre à chaque moment, la seconde reste libre de 10 h à 17 h
        foreach (['10:00', '15:00'] as $heure) {
            Reservation::create(['reference' => Reservation::nouvelleReference(), 'client_id' => $this->utilisateur(Role::Client)->id, 'logement_id' => $this->logement->id,
                'formule' => Formule::Heure, 'debut' => "2026-11-14 {$heure}", 'fin' => CarbonImmutable::parse("2026-11-14 {$heure}")->addHours(2), 'voyageurs' => 1,
                'statut' => StatutReservation::Confirmee, 'montant_location' => 7000, 'montant_services' => 0, 'taux_commission' => 5, 'montant_commission' => 350,
                'montant_total' => 7350, 'code_secours' => '111111']);
        }
        $this->assertSame(1, $dispo->unitesLibres($this->logement->fresh(), CarbonImmutable::parse('2026-11-14 10:00'), CarbonImmutable::parse('2026-11-14 17:00')));
    }

    public function test_un_logement_simple_garde_une_seule_unite(): void
    {
        $this->reserverEnLigne();
        app(Disponibilites::class)->oublier();
        $this->assertFalse(app(Disponibilites::class)->estLibre($this->logement->fresh(), CarbonImmutable::parse('2026-11-14 15:00'), CarbonImmutable::parse('2026-11-14 17:00')));
    }

    /* ---------- Réservation saisie hors plateforme ---------- */

    public function test_le_proprietaire_ajoute_une_reservation_hors_plateforme(): void
    {
        $this->actingAs($this->proprio)->post("/pro/calendrier/{$this->logement->id}/reservations", [
            'formule' => 'heure', 'date' => '2026-11-14', 'heure' => '14:00', 'duree' => 3, 'voyageurs' => 2,
            'nom' => 'Koffi Mensah', 'telephone' => '01 97 00 00 77', 'montant' => 9000, 'note' => 'Acompte reçu',
        ])->assertRedirect()->assertSessionHas('statut');

        $r = Reservation::where('origine', 'manuelle')->firstOrFail();
        $this->assertSame(StatutReservation::Confirmee, $r->statut);
        $this->assertSame(9000, (int) $r->montant_total);
        $this->assertSame(0, (int) $r->montant_commission);
        $this->assertSame('Koffi Mensah', $r->client->name);
        $this->assertSame('0197000077', $r->client->telephone);
        $this->assertSame($this->proprio->id, $r->cree_par);

        // Le créneau n'est plus proposé en ligne
        $this->expectException(ReservationImpossible::class);
        $this->reserverEnLigne();
    }

    public function test_sans_paiement_en_ligne_rien_n_est_credite_et_le_client_ne_voit_pas_de_billet(): void
    {
        $r = app(Reservations::class)->ajouterManuelle($this->proprio, $this->logement, [
            'formule' => 'heure', 'date' => '2026-11-14', 'heure' => '14:00', 'duree' => 2, 'voyageurs' => 1, 'nom' => 'Koffi Mensah', 'telephone' => '0197000077',
        ]);
        $this->assertSame(7000, (int) $r->montant_location);   // tarif par défaut

        $this->travelTo(CarbonImmutable::parse('2026-11-14 14:05'));
        app(Arrivees::class)->demarrer($this->proprio, $r);
        $this->assertSame(0, (int) $this->proprio->mouvementsSolde()->sum('montant'));

        $this->actingAs($r->client)->get('/mon-compte')->assertOk()->assertDontSee($r->reference);
        $this->get("/mes-reservations/{$r->reference}")->assertNotFound();
    }

    public function test_retirer_et_cloturer_une_reservation_hors_plateforme(): void
    {
        $r = app(Reservations::class)->ajouterManuelle($this->proprio, $this->logement, [
            'formule' => 'journee', 'date' => '2026-11-14', 'jours' => 2, 'voyageurs' => 2, 'nom' => 'Client sur place',
        ]);
        $this->assertFalse($r->client->actif);   // sans numéro : fiche sans accès
        $this->assertSame(40000, (int) $r->montant_location);

        $this->actingAs($this->proprio)->get('/pro/calendrier?logement='.$this->logement->id.'&jour=2026-11-14')->assertOk()
            ->assertSee('Hors plateforme')->assertSee('Retirer du calendrier');

        $this->post("/pro/reservations/{$r->reference}/retirer")->assertSessionHas('statut');
        $this->assertSame(StatutReservation::Annulee, $r->fresh()->statut);

        // Une autre, jamais scannée : terminée à la fin, sans crédit ni « client absent »
        $autre = app(Reservations::class)->ajouterManuelle($this->proprio, $this->logement, [
            'formule' => 'heure', 'date' => '2026-11-13', 'heure' => '10:00', 'duree' => 2, 'voyageurs' => 1, 'nom' => 'Client du matin',
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-11-13 12:30'));
        app(ApresVente::class)->cloturer();
        $this->assertSame(StatutReservation::Terminee, $autre->fresh()->statut);
        $this->assertSame(0, (int) $this->proprio->mouvementsSolde()->sum('montant'));
    }

    public function test_montant_encaisse_et_reste_a_payer(): void
    {
        $this->actingAs($this->proprio)->post("/pro/calendrier/{$this->logement->id}/reservations", [
            'formule' => 'heure', 'date' => '2026-11-14', 'heure' => '14:00', 'duree' => 3, 'voyageurs' => 2,
            'nom' => 'Koffi Mensah', 'montant' => 9000, 'encaisse' => 5000,
        ])->assertSessionHas('statut');
        $r = Reservation::where('origine', 'manuelle')->firstOrFail();
        $this->assertSame(4000, $r->resteAPayer());

        $this->get('/pro/calendrier?logement='.$this->logement->id.'&jour=2026-11-14')->assertOk()
            ->assertSeeInOrder(['À payer', "9\u{00A0}000", 'Encaissé', "5\u{00A0}000", 'Reste', "4\u{00A0}000"])->assertSee('Encaisser');

        $this->post("/pro/reservations/{$r->reference}/encaisser", ['montant' => 5000])->assertSessionHasErrors('calendrier');   // plus que le reste
        $this->post("/pro/reservations/{$r->reference}/encaisser", ['montant' => 4000])->assertSessionHas('statut');
        $this->assertSame(0, $r->fresh()->resteAPayer());

        // Encaissé supérieur au prix : refusé à la saisie
        $this->post("/pro/calendrier/{$this->logement->id}/reservations", [
            'formule' => 'heure', 'date' => '2026-11-15', 'heure' => '14:00', 'duree' => 2, 'voyageurs' => 1, 'nom' => 'Autre', 'montant' => 7000, 'encaisse' => 8000,
        ])->assertSessionHasErrors('manuelle');
    }

    public function test_un_autre_proprietaire_ne_peut_pas_ajouter(): void
    {
        $this->actingAs($this->utilisateur(Role::Proprietaire))->post("/pro/calendrier/{$this->logement->id}/reservations", [
            'formule' => 'heure', 'date' => '2026-11-14', 'heure' => '14:00', 'duree' => 2, 'voyageurs' => 1, 'nom' => 'X',
        ])->assertNotFound();
    }
}
