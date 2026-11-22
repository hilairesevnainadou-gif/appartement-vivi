<?php

namespace Tests\Feature;

use App\Enums\Formule;
use App\Enums\Role;
use App\Enums\StatutLogement;
use App\Enums\StatutReservation;
use App\Enums\TypeLogement;
use App\Filament\Resources\Salles\Pages\EditSalle;
use App\Filament\Widgets\ChiffresDuJour;
use App\Models\Logement;
use App\Models\User;
use App\Services\Caisse;
use App\Services\Reservations;
use App\Services\Tarification;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\AvecAbonnement;
use Tests\TestCase;

/** Journée d'une salle (elle finit le jour même), contrat de gestion confiée à VIVI et menus du site public. */
class SallesGestionTest extends TestCase
{
    use AvecAbonnement, RefreshDatabase;

    private User $proprio;

    private User $client;

    private Logement $studio;

    private Logement $salle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00'));
        Notification::fake();
        $this->proprio = $this->compte(Role::Proprietaire);
        $this->client = $this->compte(Role::Client);
        $this->abonner($this->proprio);
        $this->studio = $this->espace('Studio Lagune', TypeLogement::Studio, 2, 3500);
        $this->salle = $this->espace('Salle Les Palmiers', TypeLogement::SalleFetes, 300, 25000);
    }

    private function compte(Role $role): User
    {
        return tap(User::factory()->create(['actif' => true]), fn (User $u) => $u->assignRole($role->value));
    }

    private function espace(string $titre, TypeLogement $type, int $capacite, int $prix): Logement
    {
        $l = Logement::create([
            'proprietaire_id' => $this->proprio->id, 'titre' => $titre, 'slug' => Str::slug($titre), 'type' => $type,
            'ville' => 'Cotonou', 'quartier' => 'Akpakpa', 'capacite' => $capacite, 'chambres' => $type->estSalle() ? 0 : 1, 'lits' => $type->estSalle() ? 0 : 1,
            'statut' => StatutLogement::Publie, 'temps_preparation_minutes' => 60, 'latitude' => 6.36, 'longitude' => 2.45,
        ]);
        $l->tarifs()->create(['formule' => Formule::Heure, 'prix' => $prix, 'heure_ouverture' => '08:00', 'heure_fermeture' => '22:00', 'duree_minimum_heures' => 2]);

        return $l;
    }

    public function test_la_journee_d_une_salle_finit_le_jour_meme(): void
    {
        $this->salle->tarifs()->create(['formule' => Formule::Journee, 'prix' => 200000, 'heure_arrivee' => '08:00', 'heure_depart' => '22:00']);
        $this->studio->tarifs()->create(['formule' => Formule::Journee, 'prix' => 18000, 'heure_arrivee' => '12:00', 'heure_depart' => '11:00']);
        $this->salle->load('tarifs', 'creneaux', 'services');
        $this->studio->load('tarifs', 'creneaux', 'services');
        $fin = fn (Logement $l, int $n) => app(Tarification::class)
            ->devis($l, Formule::Journee, CarbonImmutable::parse('2026-11-14'), ['jours' => $n])->fin->format('Y-m-d H:i');

        $this->assertSame('2026-11-14 22:00', $fin($this->salle, 1));    // le soir même, pas le lendemain
        $this->assertSame('2026-11-15 22:00', $fin($this->salle, 2));
        $this->assertSame('2026-11-15 11:00', $fin($this->studio, 1));   // un logement garde son départ le lendemain

        // Fin avant l'heure de début : après minuit
        $this->salle->tarifs()->where('formule', Formule::Journee)->update(['heure_depart' => '02:00']);
        $this->salle->load('tarifs');
        $this->assertSame('2026-11-15 02:00', $fin($this->salle, 1));
        $this->salle->tarifs()->where('formule', Formule::Journee)->update(['heure_depart' => '22:00']);

        $this->get('/logements/salle-les-palmiers')->assertOk()->assertSee('De 08:00 à 22:00, le même jour')->assertDontSee('22:00 le lendemain');

        // Réservation : nombre de jours, horaires et prix exacts
        $service = app(Reservations::class);
        $deux = $service->bloquer($this->client, $this->salle->fresh(), ['date' => '2026-11-14', 'formule' => 'journee', 'jours' => 2]);
        $this->assertSame([2, 400000], [$deux->nombreJours(), (int) $deux->montant_location]);
        $this->assertStringContainsString('à 08:00 au', $deux->creneauEnClair());

        // La salle est libre dès le lendemain matin (préparation comprise)
        $un = $service->bloquer($this->compte(Role::Client), $this->salle->fresh(), ['date' => '2026-11-16', 'formule' => 'journee', 'jours' => 1]);
        $this->assertSame([1, 'Journée, de 08:00 à 22:00'], [$un->nombreJours(), $un->creneauEnClair()]);
        $this->assertSame(200000, $service->devis($un)->location);   // le tunnel retrouve le même prix
    }

    public function test_gestion_confiee_a_vivi_pourcentage_ou_prix_fixe_selon_le_contrat(): void
    {
        $service = app(Reservations::class);
        $demande = fn (string $date) => ['date' => $date, 'formule' => 'heure', 'heure' => '10:00', 'duree' => 4];   // 100 000 FCFA

        // L'administrateur fixe le contrat en désignant le gérant : 15 %
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->compte(Role::Administrateur));
        Livewire::test(EditSalle::class, ['record' => $this->salle->getKey()])
            ->callAction(TestAction::make('gerantVivi'), [
                'gerant' => $this->compte(Role::Gestionnaire)->id, 'gestion_type' => 'pourcentage', 'gestion_valeur' => 15, 'gestion_contrat' => 'CG-2026-014',
            ])->assertHasNoErrors();
        $this->salle->refresh();
        $this->assertSame('15 % de la location', $this->salle->libelleGestion());
        $this->get('/admin/salles')->assertOk()->assertSee('15 % de la location');
        $this->get('/admin/salles/'.$this->salle->id.'/edit')->assertOk()->assertSee('Rémunération de VIVI');

        $r = $service->bloquer($this->client, $this->salle, $demande('2026-11-14'));
        $this->assertSame([15000, 85000], [(int) $r->montant_gestion, $r->creditProprietaire()]);

        // Le contrat passe à un prix fixe : les réservations déjà faites gardent leur règle
        $this->salle->update(['gestion_type' => 'fixe', 'gestion_valeur' => 20000]);
        $this->assertSame(15000, (int) $r->fresh()->montant_gestion);
        $r2 = $service->bloquer($this->client, $this->salle->fresh(), $demande('2026-11-15'));
        $this->assertSame([20000, 80000], [(int) $r2->montant_gestion, $r2->creditProprietaire()]);

        // Séjour démarré : le propriétaire est crédité du reste, le libellé montre la retenue
        $r2->update(['statut' => StatutReservation::Confirmee]);
        app(Caisse::class)->crediterSejour($r2->load('logement.proprietaire'));
        $mouvement = $this->proprio->mouvementsSolde()->latest('id')->first();
        $this->assertSame(80000, $mouvement->montant);
        $this->assertStringContainsString('gestion VIVI', $mouvement->libelle);
        $this->assertSame(20000, ChiffresDuJour::revenusVivi(now()->subDay(), now()->addDay())['gestion']);

        // Jamais plus que ce qui revient au propriétaire (location moins part marketing)
        $this->salle->update(['gestion_valeur' => 900000, 'marketing_type' => 'pourcentage', 'marketing_valeur' => 10]);
        $r3 = $service->bloquer($this->client, $this->salle->fresh(), $demande('2026-11-16'));
        $this->assertSame([90000, 0], [(int) $r3->montant_gestion, $r3->creditProprietaire()]);

        // Le propriétaire voit les termes mais ne peut pas les changer
        $this->actingAs($this->proprio)->get('/pro/logements/'.$this->salle->id)->assertOk()->assertSee('CG-2026-014')->assertSee('par réservation');
        $this->post('/pro/logements/'.$this->salle->id, [
            'titre' => 'Salle Les Palmiers', 'type' => 'salle_fetes', 'ville' => 'Cotonou', 'quartier' => 'Akpakpa', 'adresse' => 'Rue 1', 'capacite' => 300,
            'salles_de_bain' => 2, 'description' => 'Climatisée.', 'temps_preparation_minutes' => 60, 'mode_gestion' => 'vivi',
            'tarifs' => ['heure' => ['actif' => '1', 'prix' => 25000, 'heure_ouverture' => '08:00', 'heure_fermeture' => '22:00', 'duree_minimum_heures' => 2]],
            'gestion_type' => 'pourcentage', 'gestion_valeur' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame(900000.0, (float) $this->salle->fresh()->gestion_valeur);

        // Gestion rendue au propriétaire : plus de retenue
        $this->salle->fresh()->update(['mode_gestion' => 'proprietaire', 'marketing_type' => null]);
        $this->assertSame(0, (int) $service->bloquer($this->client, $this->salle->fresh(), $demande('2026-11-17'))->montant_gestion);
    }

    public function test_gestion_confiee_a_vivi_prix_fixe_par_jour_ou_par_reservation(): void
    {
        $service = app(Reservations::class);
        $this->salle->tarifs()->create(['formule' => Formule::Journee, 'prix' => 200000, 'heure_arrivee' => '08:00', 'heure_depart' => '22:00']);
        $journees = fn (string $date, int $jours) => ['date' => $date, 'formule' => 'journee', 'jours' => $jours];

        // Par jour réservé : une période de 3 jours le compte 3 fois, une location à l'heure une fois
        $this->salle->update(['mode_gestion' => 'vivi', 'gestion_type' => 'fixe_jour', 'gestion_valeur' => 20000]);
        $this->assertStringContainsString('par jour réservé', $this->salle->fresh()->libelleGestion());
        $r = $service->bloquer($this->client, $this->salle->fresh(), $journees('2026-11-14', 3));
        $this->assertSame([60000, 60000], [(int) $r->montant_gestion, $r->fresh()->calculerPartGestion((int) $r->montant_location, 0)]);
        $heure = $service->bloquer($this->compte(Role::Client), $this->salle->fresh(), ['date' => '2026-11-18', 'formule' => 'heure', 'heure' => '10:00', 'duree' => 4]);
        $this->assertSame(20000, (int) $heure->montant_gestion);

        // Par réservation : le même prix pour un jour ou une période
        $this->salle->update(['gestion_type' => 'fixe']);
        $periode = $service->bloquer($this->compte(Role::Client), $this->salle->fresh(), $journees('2026-11-20', 3));
        $this->assertSame(20000, (int) $periode->montant_gestion);
    }

    public function test_pas_de_mes_reservations_sur_le_site_public(): void
    {
        foreach (['/', '/logements?categorie=salle', '/proprietaires', '/aide/annulation-et-report'] as $page) {
            $this->get($page)->assertOk()->assertDontSee('Mes réservations')->assertDontSee(route('espace.client'), false)
                ->assertSee('Nos villes')->assertSee('Confier la gestion à VIVI');
        }
        // Une fois connecté, le client les retrouve dans son menu
        $this->actingAs($this->client)->get('/')->assertOk()->assertSee('Mes réservations');
    }
}
