<?php

namespace Tests\Feature;

use App\Enums\Formule;
use App\Enums\Role;
use App\Enums\StatutLogement;
use App\Enums\StatutReservation;
use App\Enums\TypeLogement;
use App\Enums\TypeMouvement;
use App\Filament\Resources\Remboursements\Pages\ListRemboursements;
use App\Filament\Resources\Reservations\Pages\ViewReservation;
use App\Models\Logement;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\GestionConfiee;
use App\Notifications\RemboursementVerse;
use App\Notifications\ReservationDeplacee;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\AvecAbonnement;
use Tests\TestCase;

/** Caisse et rôles délégués (lot 7). Aujourd'hui = jeudi 12 novembre 2026, 09:00. */
class CaisseEtDelegationsTest extends TestCase
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
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->proprio = $this->utilisateur(Role::Proprietaire, 'Rodrigue Houngbo');
        $this->client = $this->utilisateur(Role::Client, 'Mireille Adjovi');
        $this->logement = Logement::create([
            'proprietaire_id' => $this->proprio->id, 'titre' => 'Studio Lagune', 'slug' => 'studio-lagune', 'type' => TypeLogement::Studio,
            'ville' => 'Cotonou', 'quartier' => 'Fidjrossè', 'capacite' => 2, 'statut' => StatutLogement::Publie, 'temps_preparation_minutes' => 60,
        ]);
        $this->logement->tarifs()->create(['formule' => Formule::Heure, 'prix' => 3500, 'heure_ouverture' => '08:00', 'heure_fermeture' => '22:00', 'duree_minimum_heures' => 2]);
    }

    private function utilisateur(Role $role, string $nom = 'Personne Test'): User
    {
        return tap(User::factory()->create(['name' => $nom]), fn (User $u) => $u->assignRole($role->value));
    }

    private function reservation(array $autres = []): Reservation
    {
        return Reservation::create($autres + [
            'reference' => Reservation::nouvelleReference(), 'client_id' => $this->client->id, 'logement_id' => $this->logement->id,
            'formule' => Formule::Heure, 'debut' => '2026-11-14 14:00', 'fin' => '2026-11-14 17:00', 'voyageurs' => 2,
            'statut' => StatutReservation::Confirmee, 'montant_location' => 10500, 'montant_services' => 5000, 'taux_commission' => 5,
            'montant_commission' => 775, 'montant_total' => 16275, 'services_beneficiaire' => 'proprietaire', 'code_secours' => '482913',
        ]);
    }

    /* ---------- Remboursements ---------- */

    public function test_la_caissiere_verse_un_remboursement_une_seule_fois(): void
    {
        $annulee = $this->reservation(['statut' => StatutReservation::Annulee, 'annulee_le' => now(), 'montant_rembourse' => 15500]);
        $this->actingAs($this->utilisateur(Role::Caissiere));

        Livewire::test(ListRemboursements::class)
            ->assertCanSeeTableRecords([$annulee])
            ->callAction(TestAction::make('verser')->table($annulee), ['mode' => 'MTN Mobile Money', 'reference' => 'MTN-7788'])
            ->assertHasNoFormErrors();

        $annulee->refresh();
        $this->assertNotNull($annulee->rembourse_le);
        $this->assertSame('MTN Mobile Money', $annulee->mode_remboursement);
        Notification::assertSentTo($this->client, RemboursementVerse::class);

        // Une fois versé, il quitte la file « À verser »
        Livewire::test(ListRemboursements::class)->assertCanNotSeeTableRecords([$annulee]);
    }

    /* ---------- Services à la carte : VIVI par défaut, sauf ceux que le propriétaire assure ---------- */

    public function test_le_prix_des_services_revient_a_vivi_sauf_exception_du_logement(): void
    {
        $this->abonner($this->proprio);
        $boissons = \App\Models\Service::create(['nom' => 'Pack Boissons', 'prix' => 5000, 'unite' => 'le pack', 'actif' => true]);
        $menage = \App\Models\Service::create(['nom' => 'Ménage', 'prix' => 3000, 'unite' => 'le passage', 'actif' => true]);
        $this->logement->services()->attach([$boissons->id, $menage->id]);
        $this->logement->services()->updateExistingPivot($menage->id, ['beneficiaire' => 'proprietaire']);   // le propriétaire assure le ménage

        $reservations = app(\App\Services\Reservations::class);
        $r = $reservations->bloquer($this->client, $this->logement, ['date' => '2026-11-14', 'formule' => 'heure', 'heure' => '14:00', 'duree' => 3, 'voyageurs' => 2]);
        $paiement = $reservations->preparerPaiement($r, [$boissons->id => 1, $menage->id => 1], 'simulation');
        $reservations->confirmer($paiement, new \App\Paiement\EtatTransaction(\App\Paiement\EtatTransaction::PAYEE));
        $r->refresh();

        $this->assertSame('mixte', $r->services_beneficiaire);
        $this->assertSame(3000, $r->montant_services_proprietaire);
        $this->assertSame(['Ménage' => 'proprietaire', 'Pack Boissons' => 'vivi'], $r->services->sortBy('nom')->pluck('beneficiaire', 'nom')->all());

        // Au démarrage du séjour : location + ménage pour le propriétaire, le pack boissons reste à VIVI
        $this->travelTo(CarbonImmutable::parse('2026-11-14 14:05'));
        app(\App\Services\Arrivees::class)->demarrer($this->proprio, $r);
        $this->assertSame(10500 + 3000, (int) $this->proprio->mouvementsSolde()->sum('montant'));
    }

    public function test_l_administrateur_note_les_services_assures_par_le_proprietaire(): void
    {
        $boissons = \App\Models\Service::create(['nom' => 'Pack Boissons', 'prix' => 5000, 'unite' => 'le pack', 'actif' => true]);
        $menage = \App\Models\Service::create(['nom' => 'Ménage', 'prix' => 3000, 'unite' => 'le passage', 'actif' => true]);
        $this->logement->services()->attach([$boissons->id, $menage->id]);
        $this->actingAs($this->utilisateur(Role::Administrateur));

        Livewire::test(\App\Filament\Resources\Logements\Pages\EditLogement::class, ['record' => $this->logement->getRouteKey()])
            ->assertFormFieldExists('services_proprietaire')
            ->fillForm(['services_proprietaire' => [(string) $menage->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $pivots = $this->logement->services()->get()->pluck('pivot.beneficiaire', 'nom')->all();
        $this->assertSame('proprietaire', $pivots['Ménage']);
        $this->assertNull($pivots['Pack Boissons']);   // valeur par défaut : VIVI
    }

    public function test_la_fiche_logement_de_l_administration_a_la_carte_de_position(): void
    {
        $this->actingAs($this->utilisateur(Role::Administrateur));
        $this->get('/admin/logements/'.$this->logement->id.'/edit')->assertOk()
            ->assertSee('Utiliser ma position')->assertSee('vivi-carte-champ', false);
    }

    /* ---------- Déplacement par l'administrateur ---------- */

    public function test_l_administrateur_deplace_une_reservation_consommee(): void
    {
        $r = $this->reservation(['statut' => StatutReservation::Consommee, 'debut' => '2026-11-11 10:00', 'fin' => '2026-11-11 13:00']);
        $this->actingAs($this->utilisateur(Role::Administrateur));

        Livewire::test(ViewReservation::class, ['record' => $r->getRouteKey()])
            ->callAction('deplacer', ['date' => '2026-11-16', 'heure' => '10:00'])
            ->assertHasNoFormErrors();

        $r->refresh();
        $this->assertSame('2026-11-16 10:00', $r->debut->format('Y-m-d H:i'));
        $this->assertSame(StatutReservation::Confirmee, $r->statut);
        Notification::assertSentTo($this->client, ReservationDeplacee::class);
    }

    public function test_la_caissiere_ne_deplace_pas(): void
    {
        $r = $this->reservation(['statut' => StatutReservation::Consommee, 'debut' => '2026-11-11 10:00', 'fin' => '2026-11-11 13:00']);
        $this->actingAs($this->utilisateur(Role::Caissiere));

        Livewire::test(ViewReservation::class, ['record' => $r->getRouteKey()])->assertActionHidden('deplacer');
    }

    /* ---------- Exports comptables ---------- */

    public function test_exports_csv_pour_l_equipe_seulement(): void
    {
        $r = $this->reservation();
        $this->actingAs($this->utilisateur(Role::Caissiere));

        $reponse = $this->get('/admin-exports/reservations?du=2026-11-01&au=2026-11-30')->assertOk();
        $csv = $reponse->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Référence;"Réservée le";Client', $csv);
        $this->assertStringContainsString($r->reference.';', $csv);
        // commission ; part marketing ; versée à ; remise agent ; part VIVI ; gestion VIVI ; total
        $this->assertStringContainsString(';775;0;VIVI;0;0;0;16275;', $csv);

        $this->get('/admin-exports/inconnu?du=2026-11-01&au=2026-11-30')->assertNotFound();
        $this->actingAs($this->proprio)->get('/admin-exports/reservations?du=2026-11-01&au=2026-11-30')->assertForbidden();
        $this->actingAs($this->utilisateur(Role::Administrateur))->get('/admin/exports')->assertOk()->assertSee('Exports comptables');
    }

    /* ---------- Espace propriétaire : réservations, revenus ---------- */

    public function test_reservations_et_revenus_du_proprietaire(): void
    {
        $this->reservation();
        $this->reservation(['statut' => StatutReservation::Annulee, 'annulee_le' => now()]);
        $this->proprio->mouvementsSolde()->create(['type' => TypeMouvement::CreditSejour, 'montant' => 30000, 'libelle' => 'Séjour test']);
        $this->actingAs($this->proprio);

        $this->get('/pro/reservations')->assertOk()->assertSee('Mireille Adjovi')->assertSeeInOrder(['À venir', '(1)', 'Passées', '(0)', 'Annulées', '(1)']);
        $this->get('/pro/revenus')->assertOk()->assertSee("30\u{00A0}000")->assertSee('Séjour test');

        $this->post('/pro/retraits', ['montant' => 50000])->assertSessionHasErrors('montant');
        $this->post('/pro/retraits', ['montant' => 12000])->assertSessionHas('statut');
        $this->assertSame(18000, $this->proprio->solde());
    }

    /* ---------- Gestionnaires délégués ---------- */

    public function test_confier_l_accueil_dans_la_limite_de_la_formule(): void
    {
        $this->abonner($this->proprio);   // formule de test : 1 gestionnaire
        $this->actingAs($this->proprio);

        $this->post('/pro/gestionnaires', ['nom' => 'Serge Agbo', 'identifiant' => '01 97 00 00 30', 'logements' => [$this->logement->id]])
            ->assertSessionHas('statut');
        $serge = User::where('telephone', '0197000030')->firstOrFail();
        $this->assertTrue($serge->hasRole(Role::Gestionnaire->value));
        $this->assertTrue($serge->logementsDelegues->contains($this->logement));
        Notification::assertSentTo($serge, GestionConfiee::class);

        $this->post('/pro/gestionnaires', ['nom' => 'Second', 'identifiant' => 'second@exemple.com', 'logements' => [$this->logement->id]])
            ->assertSessionHasErrors('identifiant');

        // Retirer l'accès : plus de logement confié, plus de rôle gestionnaire
        $this->post("/pro/gestionnaires/{$serge->id}", [])->assertSessionHas('statut');
        $serge->refresh();
        $this->assertFalse($serge->hasRole(Role::Gestionnaire->value));
        $this->assertSame(0, $serge->logementsDelegues()->count());
    }

    public function test_pas_de_gestionnaire_sans_formule_qui_l_inclut_ni_un_autre_proprietaire(): void
    {
        $this->actingAs($this->proprio);
        $this->post('/pro/gestionnaires', ['nom' => 'Serge Agbo', 'identifiant' => '0197000030', 'logements' => [$this->logement->id]])
            ->assertSessionHasErrors('identifiant');   // pas d'abonnement : aucun gestionnaire inclus

        $this->abonner($this->proprio);
        $autre = $this->utilisateur(Role::Proprietaire);
        $autre->update(['telephone' => '0197000031']);
        $this->post('/pro/gestionnaires', ['nom' => 'Autre', 'identifiant' => '0197000031', 'logements' => [$this->logement->id]])
            ->assertSessionHasErrors('identifiant');   // un propriétaire ne peut pas être gestionnaire
    }
}
