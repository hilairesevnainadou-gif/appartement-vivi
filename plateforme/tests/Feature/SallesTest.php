<?php

namespace Tests\Feature;

use App\Enums\Formule;
use App\Enums\Role;
use App\Enums\StatutLogement;
use App\Enums\TypeLogement;
use App\Models\Logement;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AvecAbonnement;
use Tests\TestCase;

/** Salles de fêtes et de conférence : publiées par les propriétaires, cherchées et réservées comme les logements. */
class SallesTest extends TestCase
{
    use AvecAbonnement, RefreshDatabase;

    private User $proprio;

    private Logement $studio;

    private Logement $salle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00'));
        Notification::fake();
        $this->proprio = tap(User::factory()->create(['name' => 'Rodrigue Houngbo', 'actif' => true]), fn (User $u) => $u->assignRole(Role::Proprietaire->value));
        $this->abonner($this->proprio);
        $this->studio = $this->espace('Studio Lagune', TypeLogement::Studio, 2);
        $this->salle = $this->espace('Salle Les Palmiers', TypeLogement::SalleFetes, 300);
    }

    private function espace(string $titre, TypeLogement $type, int $capacite): Logement
    {
        $l = Logement::create([
            'proprietaire_id' => $this->proprio->id, 'titre' => $titre, 'slug' => Str::slug($titre), 'type' => $type,
            'ville' => 'Cotonou', 'quartier' => 'Akpakpa', 'capacite' => $capacite, 'chambres' => $type->estSalle() ? 0 : 1, 'lits' => $type->estSalle() ? 0 : 1,
            'statut' => StatutLogement::Publie, 'temps_preparation_minutes' => 60, 'latitude' => 6.36, 'longitude' => 2.45,
        ]);
        $l->tarifs()->create(['formule' => Formule::Heure, 'prix' => $type->estSalle() ? 25000 : 3500, 'heure_ouverture' => '08:00', 'heure_fermeture' => '22:00', 'duree_minimum_heures' => 2]);

        return $l;
    }

    private function fiche(array $autres = []): array
    {
        return array_replace_recursive([
            'titre' => 'Salle Prestige', 'type' => 'salle_conference', 'ville' => 'Cotonou', 'quartier' => 'Cadjèhoun', 'adresse' => 'Rue 1',
            'capacite' => 250, 'chambres' => 3, 'lits' => 4, 'salles_de_bain' => 2, 'description' => 'Climatisée.', 'temps_preparation_minutes' => 120,
            'tarifs' => ['heure' => ['actif' => '1', 'prix' => 30000, 'heure_ouverture' => '08:00', 'heure_fermeture' => '23:00', 'duree_minimum_heures' => 3]],
        ], $autres);
    }

    public function test_le_proprietaire_publie_une_salle_de_conference(): void
    {
        $this->actingAs($this->proprio)->get('/pro/logements')->assertOk()->assertSee('Ajouter une salle');
        $this->get('/pro/logements/nouveau?type=salle')->assertOk()->assertSee('Capacité (personnes)')->assertSee('Salle de fêtes');

        $this->post('/pro/logements', $this->fiche())->assertRedirect()->assertSessionHasNoErrors();
        $l = Logement::where('titre', 'Salle Prestige')->firstOrFail();
        $this->assertSame(TypeLogement::SalleConference, $l->type);
        $this->assertSame(250, (int) $l->capacite);
        // Une salle n'a ni chambres ni lits, et se loue d'un seul tenant
        $this->assertSame([0, 0, 1], [(int) $l->chambres, (int) $l->lits, (int) $l->unites]);

        // 250 personnes : trop pour un studio, la limite d'un logement reste 20
        $this->post('/pro/logements', $this->fiche(['titre' => 'Grand studio', 'type' => 'studio']))->assertSessionHasErrors('capacite');
        $this->post('/pro/logements', $this->fiche(['titre' => 'Salle géante', 'capacite' => 5000]))->assertSessionHasErrors('capacite');
    }

    public function test_la_recherche_separe_logements_et_salles(): void
    {
        $this->get('/logements?categorie=salle')->assertOk()
            ->assertSee('Salle Les Palmiers')->assertDontSee('Studio Lagune')
            ->assertSee('Salle de fêtes · 300 places', false)->assertSee('Capacité')->assertDontSee('Invités');
        $this->get('/logements?categorie=logement')->assertOk()->assertSee('Studio Lagune')->assertDontSee('Salle Les Palmiers');
        $this->get('/logements')->assertOk()->assertSee('Studio Lagune')->assertSee('Salle Les Palmiers');
        // 200 invités : seule la salle convient
        $this->get('/logements?voyageurs=200')->assertOk()->assertSee('Salle Les Palmiers')->assertDontSee('Studio Lagune');

        $this->get('/')->assertOk()->assertSee('Salles de fêtes et de conférence')->assertSee('Une salle de fête ou de conférence');
        $this->get('/logements/salle-les-palmiers')->assertOk()
            ->assertSee('Capacité : 300 places')->assertSee('Règles de la salle')->assertDontSee('salle de bain')
            ->assertSee('Heure de début')->assertDontSee('id="c-voyageurs"', false)->assertDontSee('300 personnes');
        // Un logement garde son nombre de voyageurs
        $this->get('/logements/studio-lagune')->assertOk()->assertSee('id="c-voyageurs"', false)->assertSee('Heure d’arrivée', false);
    }

    public function test_quotas_separes_2_logements_et_1_salle(): void
    {
        // Formule de test : 2 logements et 1 salle ; le propriétaire a déjà 1 logement et 1 salle publiés
        \App\Models\FormuleAbonnement::query()->update(['logements_max' => 2, 'salles_max' => 1]);
        $this->actingAs($this->proprio);

        // La salle est déjà prise : une 2e salle reste en brouillon…
        $this->post('/pro/logements', $this->fiche(['titre' => 'Salle Bis']))->assertRedirect();
        $this->assertSame(StatutLogement::Brouillon, Logement::where('titre', 'Salle Bis')->first()->statut);
        // … mais un 2e logement part en vérification : les quotas sont séparés
        $this->post('/pro/logements', $this->fiche(['titre' => 'Studio Bis', 'type' => 'studio', 'capacite' => 2]))->assertRedirect();
        $this->assertSame(StatutLogement::EnValidation, Logement::where('titre', 'Studio Bis')->first()->statut);

        $this->get('/pro/logements')->assertOk()->assertSee('1 salle sur 1');
        $this->get('/pro/abonnement')->assertOk()->assertSee('salles publiées ou en vérification')->assertSee('de fête ou de conférence');
        $this->get('/proprietaires')->assertOk()->assertSee('1 salle');
    }

    public function test_le_proprietaire_confie_la_gestion_a_vivi_et_l_admin_designe_le_gerant(): void
    {
        $admin = tap(User::factory()->create(['actif' => true]), fn (User $u) => $u->assignRole(Role::Administrateur->value));
        $gerant = tap(User::factory()->create(['name' => 'Koffi Gérant VIVI', 'actif' => true]), fn (User $u) => $u->assignRole(Role::Gestionnaire->value));

        // Le propriétaire choisit « Je confie la gestion à VIVI » : l'équipe est prévenue
        $this->actingAs($this->proprio)->get('/pro/logements/'.$this->salle->id)->assertOk()->assertSee('Je confie la gestion à VIVI');
        $this->post('/pro/logements/'.$this->salle->id, $this->fiche(['titre' => 'Salle Les Palmiers', 'type' => 'salle_fetes', 'capacite' => 300, 'mode_gestion' => 'vivi']))->assertRedirect();
        $this->salle->refresh();
        $this->assertTrue($this->salle->estGereParVivi());
        $this->assertNotNull($this->salle->gestion_vivi_demandee_le);
        Notification::assertSentTo($admin, \App\Notifications\NotificationEquipe::class, fn ($n) => str_contains($n->titre, 'Gestion confiée à VIVI'));
        $this->assertSame([], app(\App\Services\Arrivees::class)->logementsDe($gerant));

        // L'administrateur désigne le gérant VIVI depuis la fiche
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        $this->actingAs($admin);
        \Livewire\Livewire::test(\App\Filament\Resources\Salles\Pages\EditSalle::class, ['record' => $this->salle->getKey()])
            ->callAction(\Filament\Actions\Testing\TestAction::make('gerantVivi'), ['gerant' => $gerant->id])
            ->assertHasNoErrors();

        $this->assertSame($gerant->id, $this->salle->fresh()->gerant_vivi_id);
        $this->assertSame([$this->salle->id], app(\App\Services\Arrivees::class)->logementsDe($gerant));   // il accueille et scanne
        $this->assertCount(0, app(\App\Services\Delegations::class)->gestionnairesDe($this->proprio));   // pas dans les gestionnaires du propriétaire
        Notification::assertSentTo($gerant, \App\Notifications\GerantViviDesigne::class, fn ($n) => $n->pourGerant);
        Notification::assertSentTo($this->proprio, \App\Notifications\GerantViviDesigne::class, fn ($n) => ! $n->pourGerant);

        // Tableau de bord et liste de l'administration
        $this->get('/admin')->assertOk()->assertSee('Salles publiées')->assertSee('Gérés par VIVI')->assertSee('Gérant VIVI à désigner');
        // Les salles ont leur propre liste, séparée des logements
        $this->get('/admin/salles')->assertOk()->assertSee('Salle Les Palmiers')->assertSee('Géré par VIVI')->assertSee('Koffi Gérant VIVI')->assertDontSee('Studio Lagune');
        $this->get('/admin/logements')->assertOk()->assertSee('Studio Lagune')->assertDontSee('Salle Les Palmiers');
        $this->get('/admin/logements/'.$this->salle->id.'/edit')->assertNotFound();
        $this->get('/admin/salles/'.$this->salle->id.'/edit')->assertOk();
        // Une seule carte, avec trois onglets ; l'ancienne adresse des salles y renvoie
        $this->get('/admin/carte-salles')->assertRedirect('/admin/carte?categorie=salle');
        $this->get('/admin/carte?categorie=salle')->assertOk()->assertSee('Salle Les Palmiers')->assertDontSee('Studio Lagune')->assertSee('Salles inscrites');
        $this->get('/admin/carte?categorie=logement')->assertOk()->assertSee('Studio Lagune')->assertDontSee('Salle Les Palmiers')->assertSee('Logements inscrits');
        $this->get('/admin/carte?categorie[]=x')->assertOk()->assertSee('Studio Lagune')->assertSee('Salle Les Palmiers')->assertSee('Bord orange : salle');
    }

    public function test_la_commission_des_salles_varie_par_tranches(): void
    {
        $tarification = app(\App\Services\Tarification::class);
        $jour = CarbonImmutable::parse('2026-11-14');
        $this->salle->tarifs()->create(['formule' => Formule::Journee, 'prix' => 300000, 'heure_arrivee' => '08:00', 'heure_depart' => '07:00']);
        $this->salle->load('tarifs', 'creneaux', 'services');

        // Tranches par défaut : jusqu'à 100 000 FCFA 10 %, jusqu'à 500 000 FCFA 8 %, au-delà 6 %
        $petit = $tarification->devis($this->salle, Formule::Heure, $jour, ['heure' => '10:00', 'duree' => 4]);          // 100 000
        $moyen = $tarification->devis($this->salle, Formule::Heure, $jour, ['heure' => '10:00', 'duree' => 6]);          // 150 000
        $grand = $tarification->devis($this->salle, Formule::Journee, $jour, ['jours' => 2]);                           // 600 000
        $this->assertSame([10.0, 10000], [$petit->tauxCommission, $petit->commission]);
        $this->assertSame([8.0, 12000], [$moyen->tauxCommission, $moyen->commission]);
        $this->assertSame([6.0, 36000], [$grand->tauxCommission, $grand->commission]);

        // Les logements gardent le taux unique
        $this->studio->load('tarifs', 'creneaux', 'services');
        $this->assertSame(5.0, $tarification->devis($this->studio, Formule::Heure, $jour, ['heure' => '10:00', 'duree' => 2])->tauxCommission);

        // L'administrateur change les tranches dans Paramètres
        \App\Support\Parametres::enregistrer(['commission_salles_tranches' => [
            ['jusqua' => '', 'pourcentage' => 4], ['jusqua' => 200000, 'pourcentage' => 12],
        ]]);
        $this->assertSame([['jusqua' => 200000, 'pourcentage' => 12.0], ['jusqua' => null, 'pourcentage' => 4.0]], \App\Support\Parametres::get('commission_salles_tranches'));
        $this->assertSame(12.0, $tarification->devis($this->salle, Formule::Heure, $jour, ['heure' => '10:00', 'duree' => 6])->tauxCommission);
        $this->assertSame(4.0, $tarification->devis($this->salle, Formule::Journee, $jour, ['jours' => 2])->tauxCommission);
        $this->getJson('/api/v1/configuration')->assertOk()->assertJsonPath('data.commission_salles_tranches.0.pourcentage', 12);
    }

    public function test_une_salle_se_reserve_entiere_sans_nombre_de_personnes(): void
    {
        Sanctum::actingAs($client = tap(User::factory()->create(['actif' => true]), fn (User $u) => $u->assignRole(Role::Client->value)));

        $this->getJson('/api/v1/configuration')->assertOk()
            ->assertJsonPath('data.categories.1.valeur', 'salle')
            ->assertJsonFragment(['valeur' => 'salle_fetes', 'libelle' => 'Salle de fêtes', 'categorie' => 'salle', 'capacite_max' => 1000]);
        $this->getJson('/api/v1/logements?categorie=salle')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.categorie', 'salle')->assertJsonPath('data.0.est_salle', true)
            ->assertJsonPath('data.0.capacite_libelle', '300 places')->assertJsonPath('criteres.categorie', 'salle');

        $reference = $this->postJson('/api/v1/logements/salle-les-palmiers/reserver', ['date' => '2026-11-14', 'formule' => 'heure', 'heure' => '15:00', 'duree' => 4])
            ->assertCreated()->json('data.reference');
        $r = Reservation::where('reference', $reference)->firstOrFail();
        $this->assertNull($r->load('logement')->personnes());
        $this->assertSame('', $r->suffixePersonnes());
        $this->getJson('/api/v1/reservations/'.$reference)->assertOk()->assertJsonPath('data.personnes', null)->assertJsonPath('data.logement.est_salle', true);

        // Tunnel du site : aucun nombre de personnes pour la salle
        $this->actingAs($client)->get('/reservations/'.$reference.'/paiement')->assertOk()->assertSee('Salle Les Palmiers')->assertDontSee('voyageur')->assertDontSee('1 personne');
        // Un logement exige toujours le nombre de voyageurs et l'affiche
        $this->post('/reserver/studio-lagune', ['date' => '2026-11-14', 'formule' => 'heure', 'heure' => '10:00', 'duree' => 2])->assertSessionHasErrors('voyageurs');
        $this->post('/reserver/studio-lagune', ['date' => '2026-11-14', 'formule' => 'heure', 'heure' => '10:00', 'duree' => 2, 'voyageurs' => 2])->assertRedirect();
        $studio = Reservation::where('logement_id', $this->studio->id)->firstOrFail();
        $this->get('/reservations/'.$studio->reference.'/paiement')->assertOk()->assertSee('2 voyageurs');

        // Réservation hors plateforme d'une salle par le propriétaire : pas de champ « Voyageurs »
        $this->actingAs($this->proprio)->get('/pro/calendrier?logement='.$this->salle->id)->assertOk()->assertDontSee('id="m-voy"', false);
        $this->get('/pro/calendrier?logement='.$this->studio->id)->assertOk()->assertSee('id="m-voy"', false);
        $this->post('/pro/calendrier/'.$this->salle->id.'/reservations', ['formule' => 'heure', 'date' => '2026-11-20', 'heure' => '10:00', 'duree' => 3, 'nom' => 'Association Espoir'])
            ->assertSessionHasNoErrors();
        $manuelle = Reservation::where('origine', Reservation::ORIGINE_MANUELLE)->firstOrFail()->load('logement');
        $this->assertNull($manuelle->personnes());
        $this->assertStringNotContainsString('voyageur', (new \App\Notifications\NouvelleReservation($manuelle))->toSms($this->proprio));
    }

    public function test_menus_et_pages_publiques_presentent_logements_et_salles(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('categorie=logement', false)->assertSee('categorie=salle', false)->assertSee('Salles et événements')
            ->assertSee('Gestion confiée à VIVI')->assertSee('Confier la gestion à VIVI')->assertSee('1 logement · 1 salle');
        $this->get('/logements?categorie=salle')->assertOk()->assertSee('data-page="salles"', false);
        $this->get('/logements?categorie=logement')->assertOk()->assertSee('data-page="recherche"', false);
        $this->get('/proprietaires')->assertOk()->assertSee('Je confie la gestion à VIVI')->assertSee('Publiez votre appartement ou votre salle');
    }
}
