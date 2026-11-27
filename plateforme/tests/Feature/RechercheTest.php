<?php

namespace Tests\Feature;

use App\Enums\Formule;
use App\Enums\Role;
use App\Enums\StatutAvis;
use App\Enums\StatutLogement;
use App\Enums\StatutReservation;
use App\Enums\TypeLogement;
use App\Models\Avis;
use App\Models\Equipement;
use App\Models\Logement;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\AvecAbonnement;
use Tests\TestCase;

/** Accueil, page de résultats et fiche logement (lot 3). */
class RechercheTest extends TestCase
{
    use AvecAbonnement, RefreshDatabase;

    private User $proprio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00'));
        $this->proprio = tap(User::factory()->create(), fn (User $u) => $u->assignRole(Role::Proprietaire->value));
        $this->abonner($this->proprio);
    }

    private function logement(string $titre, array $attributs = [], array $tarifs = ['heure' => 3500]): Logement
    {
        $l = Logement::create($attributs + [
            'proprietaire_id' => $this->proprio->id, 'titre' => $titre, 'slug' => Str::slug($titre), 'type' => TypeLogement::Studio,
            'ville' => 'Cotonou', 'quartier' => 'Fidjrossè', 'capacite' => 2, 'statut' => StatutLogement::Publie,
            'description' => 'Logement meublé.',
        ]);
        foreach ($tarifs as $formule => $prix) {
            $l->tarifs()->create(['formule' => $formule, 'prix' => $prix, 'heure_ouverture' => '08:00', 'heure_fermeture' => '22:00',
                'duree_minimum_heures' => 2, 'heure_arrivee' => '12:00', 'heure_depart' => '11:00']);
        }

        return $l;
    }

    public function test_la_recherche_filtre_par_ville_voyageurs_formule_et_budget(): void
    {
        $this->logement('Studio Lagune');
        $this->logement('Villa Ouidah', ['ville' => 'Ouidah', 'capacite' => 6]);
        $this->logement('Loft Cher', ['capacite' => 4], ['heure' => 8000, 'journee' => 40000]);
        $this->logement('Brouillon caché', ['statut' => StatutLogement::Brouillon]);
        $this->logement('En pause', ['en_ligne' => false]);

        $this->get('/logements')->assertOk()
            ->assertSee('3 logements au Bénin')
            ->assertDontSee('Brouillon caché')->assertDontSee('En pause');

        $this->get('/logements?ville=Cotonou')->assertSee('2 logements à Cotonou')->assertDontSee('Villa Ouidah');
        $this->get('/logements?voyageurs=4')->assertSee('Villa Ouidah')->assertSee('Loft Cher')->assertDontSee('Studio Lagune');
        $this->get('/logements?formule=journee')->assertSee('Loft Cher')->assertDontSee('Studio Lagune');
        $this->get('/logements?formule=heure&budget=5000')->assertSee('Studio Lagune')->assertDontSee('Loft Cher');
    }

    public function test_la_recherche_par_mots_cherche_titre_quartier_et_ville(): void
    {
        $this->logement('Studio Lagune');
        $this->logement('Villa Plage', ['ville' => 'Ouidah', 'quartier' => 'Djègbadji']);
        $this->logement('Loft 100% neuf', ['quartier' => 'Akpakpa']);

        $noms = fn (string $q) => collect($this->getJson('/api/v1/logements?q='.urlencode($q))->assertOk()->json('data'))->pluck('titre')->sort()->values()->all();

        $this->assertSame(['Studio Lagune'], $noms('lagune'));
        $this->assertSame(['Villa Plage'], $noms('ouidah'));
        $this->assertSame(['Loft 100% neuf'], $noms('akpakpa  loft'));    // chaque mot doit se trouver
        $this->assertSame([], $noms('lagune ouidah'));
        $this->assertSame(['Loft 100% neuf'], $noms('100%'));          // % cherché tel quel
        $this->assertCount(3, $noms('   '));
        $this->getJson('/api/v1/logements?q=Lagune')->assertJsonPath('criteres.q', 'Lagune');
    }

    public function test_pres_de_moi_propose_les_logements_proches_du_plus_pres_au_plus_loin(): void
    {
        $this->logement('Studio Fidjrossè', ['latitude' => 6.3530, 'longitude' => 2.3790]);     // ~4,6 km
        $this->logement('Appartement Cadjèhoun', ['latitude' => 6.3700, 'longitude' => 2.3950]);   // ~2,6 km
        $this->logement('Villa Ouidah', ['ville' => 'Ouidah', 'latitude' => 6.3631, 'longitude' => 2.0851]);   // ~37 km
        $this->logement('Sans position');

        // Visiteur à Cotonou (Ganhi) : seulement ceux à moins de 25 km, le plus proche d'abord, avec la distance
        $this->get('/logements?lat=6.3654&lng=2.4183')->assertOk()
            ->assertSee('2 logements près de vous')
            ->assertSeeInOrder(['à 2,6 km', 'Appartement Cadjèhoun', 'à 4,6 km', 'Studio Fidjrossè'])   // distance affichée au-dessus du titre
            ->assertDontSee('Villa Ouidah')->assertDontSee('Sans position');

        // Personne à moins de 25 km : tous ceux qui ont une position, du plus proche au plus loin
        $this->get('/logements?lat=6.70&lng=1.80')->assertOk()
            ->assertSee('Aucun logement disponible à moins de 25 km', false)
            ->assertSeeInOrder(['Villa Ouidah', 'Studio Fidjrossè']);

        // Position invalide : recherche normale
        $this->get('/logements?lat=abc&lng=2')->assertOk()->assertSee('4 logements au Bénin');
    }

    public function test_le_budget_sans_formule_porte_sur_le_prix_a_l_heure(): void
    {
        $this->logement('Studio Abordable', [], ['heure' => 3000]);
        $this->logement('Loft Cher', [], ['heure' => 9000]);

        $this->get('/logements')->assertOk()->assertSee('id="f-budget"', false)->assertDontSee('id="f-budget" min="0" max="100" value="100" disabled', false);
        $this->get('/logements?budget=5000')->assertOk()->assertSee('Studio Abordable')->assertDontSee('Loft Cher')
            ->assertSee("Jusqu’à 5\u{00A0}000\u{00A0}FCFA par heure", false);
    }

    public function test_la_recherche_filtre_par_equipement_et_type(): void
    {
        $clim = Equipement::create(['nom' => 'Climatisation', 'icone' => 'bi-snow']);
        $this->logement('Studio Climatisé')->equipements()->attach($clim);
        $this->logement('F2 Sans clim', ['type' => TypeLogement::F2]);

        $this->get('/logements?equipement[]='.$clim->id)->assertSee('Studio Climatisé')->assertDontSee('F2 Sans clim');
        $this->get('/logements?type[]=f2')->assertSee('F2 Sans clim')->assertDontSee('Studio Climatisé');
    }

    public function test_un_logement_complet_a_la_date_choisie_n_apparait_pas(): void
    {
        $plein = $this->logement('Studio Plein');
        $client = tap(User::factory()->create(), fn (User $u) => $u->assignRole(Role::Client->value));
        Reservation::create([
            'reference' => 'VIVI-PLEIN1', 'client_id' => $client->id, 'logement_id' => $plein->id, 'formule' => Formule::Heure,
            'debut' => '2026-11-13 08:00', 'fin' => '2026-11-13 22:00', 'voyageurs' => 2, 'statut' => StatutReservation::Confirmee,
            'montant_location' => 49000, 'montant_services' => 0, 'taux_commission' => 5, 'montant_commission' => 2450,
            'montant_total' => 51450, 'services_beneficiaire' => 'proprietaire', 'code_secours' => '123456',
        ]);

        $this->get('/logements?date=2026-11-13')->assertDontSee('Studio Plein');
        $this->get('/logements?date=2026-11-14')->assertSee('Studio Plein');
    }

    public function test_la_fiche_affiche_tarifs_bloc_reserver_et_avis(): void
    {
        $l = $this->logement('Studio Lagune', [], ['heure' => 3500, 'journee' => 18000]);
        $client = tap(User::factory()->create(['name' => 'Arnaud Fagla']), fn (User $u) => $u->assignRole(Role::Client->value));
        $r = Reservation::create([
            'reference' => 'VIVI-AVIS01', 'client_id' => $client->id, 'logement_id' => $l->id, 'formule' => Formule::Heure,
            'debut' => '2026-11-10 10:00', 'fin' => '2026-11-10 13:00', 'voyageurs' => 2, 'statut' => StatutReservation::Terminee,
            'montant_location' => 10500, 'montant_services' => 0, 'taux_commission' => 5, 'montant_commission' => 525,
            'montant_total' => 11025, 'services_beneficiaire' => 'proprietaire', 'code_secours' => '123456',
        ]);
        Avis::create(['reservation_id' => $r->id, 'logement_id' => $l->id, 'client_id' => $client->id, 'note' => 5, 'commentaire' => 'Propre et calme.', 'statut' => StatutAvis::Publie]);

        $this->get('/logements/studio-lagune?formule=heure&date=2026-11-13')->assertOk()
            ->assertSee('Studio Lagune')
            ->assertSee("3\u{00A0}500\u{00A0}FCFA")
            ->assertSee('Non proposé')                 // demi-journée
            ->assertSee('« Propre et calme. »', false)
            ->assertSee('Arnaud F.')
            ->assertSee('etat-initial');
    }

    public function test_un_logement_non_publie_n_est_visible_que_par_son_proprietaire_et_l_equipe(): void
    {
        $this->logement('Chambre en attente', ['statut' => StatutLogement::EnValidation]);

        $this->get('/logements/chambre-en-attente')->assertNotFound();
        $this->actingAs($this->proprio)->get('/logements/chambre-en-attente')->assertOk()->assertSee('Aperçu');
    }

    public function test_l_accueil_montre_les_logements_a_la_une_et_les_villes(): void
    {
        $this->logement('Studio Lagune');
        $this->logement('Villa Ouidah', ['ville' => 'Ouidah']);

        $this->get('/')->assertOk()
            ->assertSee('Des logements prêts à vous accueillir')
            ->assertSee('Studio Lagune')
            ->assertSee('Nos villes')
            ->assertSee('Ouidah');
    }
}
