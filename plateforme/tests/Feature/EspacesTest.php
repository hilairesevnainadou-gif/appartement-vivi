<?php

namespace Tests\Feature;

use App\Enums\Formule;
use App\Enums\Role;
use App\Enums\StatutLogement;
use App\Enums\StatutReservation;
use App\Enums\StatutRetrait;
use App\Enums\TypeLogement;
use App\Enums\TypeMouvement;
use App\Models\Logement;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class EspacesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2026-11-12 09:00'));
    }

    private function utilisateur(Role $role, string $nom): User
    {
        return tap(User::factory()->create(['name' => $nom]), fn (User $u) => $u->assignRole($role->value));
    }

    private function logement(User $proprietaire, string $titre = 'Studio Lagune'): Logement
    {
        return Logement::create([
            'proprietaire_id' => $proprietaire->id, 'titre' => $titre, 'slug' => Str::slug($titre),
            'type' => TypeLogement::cases()[0], 'ville' => 'Cotonou', 'quartier' => 'Fidjrossè',
            'adresse' => 'Rue 12', 'capacite' => 2, 'statut' => StatutLogement::Publie, 'description' => 'Studio meublé.',
        ]);
    }

    private function reservation(Logement $logement, User $client, string $debut, string $fin, StatutReservation $statut, Formule $formule = Formule::Heure): Reservation
    {
        return Reservation::create([
            'reference' => 'VIVI-'.Str::upper(Str::random(6)), 'client_id' => $client->id, 'logement_id' => $logement->id,
            'formule' => $formule, 'debut' => $debut, 'fin' => $fin, 'voyageurs' => 2, 'statut' => $statut,
            'montant_location' => 10000, 'montant_services' => 0, 'taux_commission' => 5, 'montant_commission' => 500,
            'montant_total' => 10500, 'services_beneficiaire' => 'proprietaire', 'code_secours' => '123456',
        ]);
    }

    public function test_le_tableau_de_bord_montre_les_arrivees_les_revenus_et_le_solde(): void
    {
        $proprio = $this->utilisateur(Role::Proprietaire, 'Rodrigue Houngbo');
        $client = $this->utilisateur(Role::Client, 'Mireille Adjovi');
        $lagune = $this->logement($proprio);
        $this->reservation($lagune, $client, '2026-11-12 14:00', '2026-11-12 17:00', StatutReservation::Confirmee);
        $this->reservation($lagune, $client, '2026-11-12 08:00', '2026-11-12 09:30', StatutReservation::EnCours);
        $this->reservation($lagune, $client, '2026-11-14 10:00', '2026-11-14 12:00', StatutReservation::Confirmee);
        $proprio->mouvementsSolde()->create(['type' => TypeMouvement::Ajustement, 'montant' => 46500, 'libelle' => 'Test']);

        $this->actingAs($proprio)->get('/pro')->assertOk()
            ->assertSeeInOrder(['Bonjour Rodrigue', 'Arrivées aujourd’hui', '2', 'dont 1 déjà sur place'])
            ->assertSee('Mireille A. · 2 voyageurs');

        $this->get('/pro')->assertSee("10\u{00A0}000\u{00A0}FCFA")   // revenus du mois : le séjour en cours
            ->assertSee("46\u{00A0}500")                             // solde
            ->assertSee('Séjour en cours')
            ->assertSee('Aucun abonnement actif');
    }

    public function test_le_proprietaire_demande_un_retrait_de_son_solde(): void
    {
        $proprio = $this->utilisateur(Role::Proprietaire, 'Rodrigue Houngbo');
        $proprio->mouvementsSolde()->create(['type' => TypeMouvement::Ajustement, 'montant' => 46500, 'libelle' => 'Test']);

        $this->actingAs($proprio)->from('/pro')->post('/pro/retraits')->assertRedirect('/pro')->assertSessionHas('statut');

        $this->assertSame(0, $proprio->solde());
        $this->assertSame(StatutRetrait::EnAttente, $proprio->retraits()->first()->statut);
        $this->get('/pro')->assertSee('En attente de validation');

        // Solde vide : refus
        $this->from('/pro')->post('/pro/retraits')->assertSessionHasErrors('retrait');
    }

    public function test_un_gestionnaire_ne_demande_pas_de_retrait(): void
    {
        $this->actingAs($this->utilisateur(Role::Gestionnaire, 'Serge Agbo'))
            ->get('/pro')->assertOk()->assertDontSee('Solde et retraits');
        $this->post('/pro/retraits')->assertForbidden();
    }

    public function test_le_client_voit_ses_reservations_par_onglet(): void
    {
        $proprio = $this->utilisateur(Role::Proprietaire, 'Rodrigue Houngbo');
        $client = $this->utilisateur(Role::Client, 'Mireille Adjovi');
        $autre = $this->utilisateur(Role::Client, 'Géraud Kiki');
        $lagune = $this->logement($proprio);
        $cocotiers = $this->logement($proprio, 'Résidence Les Cocotiers');

        $loin = $this->reservation($lagune, $client, '2026-11-18 14:00', '2026-11-18 17:00', StatutReservation::Confirmee);
        $proche = $this->reservation($cocotiers, $client, '2026-11-12 11:00', '2026-11-12 13:00', StatutReservation::Confirmee);
        $this->reservation($lagune, $client, '2026-11-02 10:00', '2026-11-02 12:00', StatutReservation::Terminee);
        $this->reservation($lagune, $client, '2026-11-03 10:00', '2026-11-03 12:00', StatutReservation::Annulee);
        $pasLaSienne = $this->reservation($lagune, $autre, '2026-11-20 10:00', '2026-11-20 12:00', StatutReservation::Confirmee);

        $page = $this->actingAs($client)->get('/mon-compte')->assertOk();
        $page->assertSeeInOrder(['À venir', '(2)', 'Passées', '(1)', 'Annulées', '(1)'])
            ->assertSee($loin->reference)
            ->assertSee($proche->reference)
            ->assertDontSee($pasLaSienne->reference)
            ->assertSee('Report impossible à moins de 3 h', false)   // celle de 11:00, à 2 h de l'arrivée
            ->assertSee('De 14:00 à 17:00 (3 h)');
    }

    public function test_mes_reservations_vide(): void
    {
        $this->actingAs($this->utilisateur(Role::Client, 'Prisca Dossa'))
            ->get('/mon-compte?onglet=passees')->assertOk()
            ->assertSee('Aucune réservation à venir')
            ->assertSee('Aucune réservation passée');
    }
}
