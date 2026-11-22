<?php

namespace Tests\Feature;

use App\Enums\Formule;
use App\Enums\Role;
use App\Enums\StatutLogement;
use App\Enums\StatutReservation;
use App\Enums\TypeLogement;
use App\Models\Logement;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use App\Services\Disponibilites;
use App\Services\Tarification;
use App\Support\Parametres;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Concerns\AvecAbonnement;
use Tests\TestCase;

/** Moteur de disponibilités et calcul du prix (lot 3). Aujourd'hui = jeudi 12 novembre 2026, 09:00. */
class DisponibilitesTest extends TestCase
{
    use AvecAbonnement, RefreshDatabase;

    private Logement $logement;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00'));

        $proprio = tap(User::factory()->create(), fn (User $u) => $u->assignRole(Role::Proprietaire->value));
        $this->abonner($proprio);
        $this->client = tap(User::factory()->create(), fn (User $u) => $u->assignRole(Role::Client->value));

        $this->logement = Logement::create([
            'proprietaire_id' => $proprio->id, 'titre' => 'Studio Test', 'slug' => 'studio-test', 'type' => TypeLogement::Studio,
            'ville' => 'Cotonou', 'quartier' => 'Fidjrossè', 'capacite' => 2, 'statut' => StatutLogement::Publie,
            'temps_preparation_minutes' => 60,
        ]);
        $this->logement->tarifs()->createMany([
            ['formule' => Formule::Heure, 'prix' => 3500, 'heure_ouverture' => '08:00', 'heure_fermeture' => '22:00', 'duree_minimum_heures' => 2],
            ['formule' => Formule::DemiJournee, 'prix' => 10000],
            ['formule' => Formule::Journee, 'prix' => 18000, 'heure_arrivee' => '12:00', 'heure_depart' => '11:00'],
        ]);
        $this->logement->creneaux()->createMany([
            ['nom' => 'Matin', 'heure_debut' => '08:00', 'heure_fin' => '13:00', 'ordre' => 1],
            ['nom' => 'Après-midi', 'heure_debut' => '14:00', 'heure_fin' => '19:00', 'ordre' => 2],
            ['nom' => 'Soirée', 'heure_debut' => '20:00', 'heure_fin' => '01:00', 'ordre' => 3],
        ]);
    }

    private function logement(): Logement
    {
        return Logement::with(['tarifs', 'creneaux', 'services'])->find($this->logement->id);
    }

    private function moteur(): Disponibilites
    {
        return new Disponibilites;
    }

    private function reserver(string $debut, string $fin, StatutReservation $statut = StatutReservation::Confirmee, array $autres = []): Reservation
    {
        return Reservation::create([
            'reference' => 'VIVI-'.Str::upper(Str::random(6)), 'client_id' => $this->client->id, 'logement_id' => $this->logement->id,
            'formule' => Formule::Heure, 'debut' => $debut, 'fin' => $fin, 'voyageurs' => 2, 'statut' => $statut,
            'montant_location' => 7000, 'montant_services' => 0, 'taux_commission' => 5, 'montant_commission' => 350,
            'montant_total' => 7350, 'services_beneficiaire' => 'proprietaire', 'code_secours' => '123456',
        ] + $autres);
    }

    /* ---------- Règle de base ---------- */

    public function test_un_sejour_dans_le_passe_ou_trop_proche_n_est_pas_libre(): void
    {
        $m = $this->moteur();
        $this->assertFalse($m->estLibre($this->logement, now()->subHour(), now()->addHour()));
        $this->assertFalse($m->estLibre($this->logement, now()->addMinutes(10), now()->addHours(3)));
        $this->assertTrue($m->estLibre($this->logement, now()->addHour(), now()->addHours(3)));
    }

    public function test_le_temps_de_preparation_separe_deux_sejours(): void
    {
        $this->reserver('2026-11-12 14:00', '2026-11-12 17:00');
        $m = $this->moteur();

        $this->assertFalse($m->estLibre($this->logement, CarbonImmutable::parse('2026-11-12 17:00'), CarbonImmutable::parse('2026-11-12 19:00')));
        $this->assertTrue($m->estLibre($this->logement, CarbonImmutable::parse('2026-11-12 18:00'), CarbonImmutable::parse('2026-11-12 20:00')));
        // Avant : il faut aussi une heure avant l'arrivée du client suivant
        $this->assertFalse($m->estLibre($this->logement, CarbonImmutable::parse('2026-11-12 11:00'), CarbonImmutable::parse('2026-11-12 13:30')));
        $this->assertTrue($m->estLibre($this->logement, CarbonImmutable::parse('2026-11-12 11:00'), CarbonImmutable::parse('2026-11-12 13:00')));
    }

    public function test_un_jour_bloque_rend_le_logement_indisponible(): void
    {
        $this->logement->blocages()->create(['date_debut' => '2026-11-14', 'date_fin' => '2026-11-15', 'motif' => 'Travaux']);
        $m = $this->moteur();

        $this->assertFalse($m->estLibre($this->logement, CarbonImmutable::parse('2026-11-14 10:00'), CarbonImmutable::parse('2026-11-14 12:00')));
        $this->assertFalse($m->estLibre($this->logement, CarbonImmutable::parse('2026-11-13 12:00'), CarbonImmutable::parse('2026-11-14 11:00')));
        $this->assertTrue($m->estLibre($this->logement, CarbonImmutable::parse('2026-11-16 10:00'), CarbonImmutable::parse('2026-11-16 12:00')));
    }

    public function test_une_reservation_annulee_ou_un_paiement_abandonne_libere_le_creneau(): void
    {
        $this->reserver('2026-11-12 14:00', '2026-11-12 17:00', StatutReservation::Annulee);
        $this->reserver('2026-11-13 14:00', '2026-11-13 17:00', StatutReservation::EnAttentePaiement, ['bloque_jusqua' => now()->subMinute()]);
        $this->reserver('2026-11-14 14:00', '2026-11-14 17:00', StatutReservation::EnAttentePaiement, ['bloque_jusqua' => now()->addMinutes(10)]);
        $m = $this->moteur();

        $this->assertTrue($m->estLibre($this->logement, CarbonImmutable::parse('2026-11-12 14:00'), CarbonImmutable::parse('2026-11-12 16:00')));
        $this->assertTrue($m->estLibre($this->logement, CarbonImmutable::parse('2026-11-13 14:00'), CarbonImmutable::parse('2026-11-13 16:00')));
        // Paiement en cours : le créneau reste retenu
        $this->assertFalse($m->estLibre($this->logement, CarbonImmutable::parse('2026-11-14 14:00'), CarbonImmutable::parse('2026-11-14 16:00')));
    }

    /* ---------- Formules ---------- */

    public function test_heures_libres_et_duree_maximale(): void
    {
        $this->reserver('2026-11-13 14:00', '2026-11-13 17:00');
        $heures = collect($this->moteur()->heures($this->logement(), CarbonImmutable::parse('2026-11-13')))->keyBy('heure');

        $this->assertSame(5, $heures['08:00']['duree_max']);       // 08:00 → 13:00, puis une heure de préparation
        $this->assertFalse($heures['12:00']['libre']);              // 12:00 + 2 h minimum touche la préparation
        $this->assertFalse($heures['16:00']['libre']);
        $this->assertSame(4, $heures['18:00']['duree_max']);       // jusqu'à la fermeture à 22:00
        $this->assertArrayNotHasKey('21:00', $heures->all());       // 21:00 + 2 h dépasse la fermeture
    }

    public function test_un_lieu_ouvert_24h_se_reserve_de_nuit(): void
    {
        $this->logement->tarifs()->where('formule', Formule::Heure)->update(['heure_ouverture' => '00:00', 'heure_fermeture' => '00:00']);
        $date = CarbonImmutable::parse('2026-11-13');
        $heures = collect($this->moteur()->heures($this->logement(), $date))->keyBy('heure');
        $this->assertCount(24, $heures);
        $this->assertSame(24, $heures['23:00']['duree_max']);

        // Une réservation le lendemain à 04:00 : avec une heure de préparation, on part à 03:00 au plus tard
        $this->reserver('2026-11-14 04:00', '2026-11-14 06:00');
        $this->assertSame(4, collect($this->moteur()->heures($this->logement(), $date))->firstWhere('heure', '23:00')['duree_max']);

        $devis = app(Tarification::class)->devis($this->logement(), Formule::Heure, $date, ['heure' => '23:00', 'duree' => 3]);
        $this->assertSame('2026-11-14 02:00', $devis->fin->format('Y-m-d H:i'));
        $this->assertSame(10500, $devis->location);

        $etat = app(\App\Services\SimulationSejour::class)->etat($this->logement(), ['date' => '2026-11-13', 'formule' => 'heure', 'heure' => '23:00', 'duree' => 3]);
        $this->assertStringContainsString('Départ à 02:00 le lendemain', $etat['aide']);
        $this->assertTrue($this->moteur()->resume($this->logement(), $date)['heure']);
        $this->assertStringContainsString('De 23:00 à 02:00 le lendemain', $this->reserver('2026-11-20 23:00', '2026-11-21 02:00')->creneauEnClair());

        $this->get('/logements/studio-test')->assertOk()->assertSee('Ouvert 24 h/24');
        $this->getJson('/api/v1/logements/studio-test')->assertOk()->assertJsonPath('data.ouvert_24h', true);

        $this->expectException(\InvalidArgumentException::class);
        app(Tarification::class)->devis($this->logement(), Formule::Heure, $date, ['heure' => '23:00', 'duree' => 25]);
    }

    public function test_avec_des_horaires_la_nuit_reste_fermee(): void
    {
        $this->assertFalse($this->logement()->ouvert24h());
        try {
            app(Tarification::class)->devis($this->logement(), Formule::Heure, CarbonImmutable::parse('2026-11-13'), ['heure' => '21:00', 'duree' => 3]);
            $this->fail('La nuit doit être refusée.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('entre 08:00 et 22:00', $e->getMessage());
        }

        // Fermeture après minuit : on n'arrive que le jour choisi, jusqu'à la fermeture du lendemain
        $this->logement->tarifs()->where('formule', Formule::Heure)->update(['heure_fermeture' => '02:00', 'duree_minimum_heures' => 1]);
        $heures = collect($this->moteur()->heures($this->logement(), CarbonImmutable::parse('2026-11-13')));
        $this->assertSame(['08:00', '23:00', 3], [$heures->first()['heure'], $heures->last()['heure'], $heures->last()['duree_max']]);
    }

    public function test_creneaux_de_demi_journee(): void
    {
        $this->reserver('2026-11-13 14:00', '2026-11-13 19:00');
        $creneaux = collect($this->moteur()->creneaux($this->logement(), CarbonImmutable::parse('2026-11-13')))->keyBy(fn ($c) => $c['creneau']->nom);

        $this->assertTrue($creneaux['Matin']['libre']);
        $this->assertFalse($creneaux['Après-midi']['libre']);
        $this->assertTrue($creneaux['Soirée']['libre']);                                       // 20:00, une heure après le départ de 19:00
        $this->assertSame('2026-11-14 01:00', $creneaux['Soirée']['fin']->format('Y-m-d H:i')); // finit le lendemain
    }

    public function test_journees_consecutives_jusqu_a_la_prochaine_reservation(): void
    {
        $this->reserver('2026-11-16 14:00', '2026-11-16 17:00');
        $m = $this->moteur();

        // Arrivée le 13 à 12:00 : départ le 16 à 11:00 possible, le 17 non
        $this->assertSame(3, $m->joursMax($this->logement(), CarbonImmutable::parse('2026-11-13')));
    }

    /* ---------- Prix ---------- */

    public function test_prix_a_l_heure_avec_commission(): void
    {
        $devis = app(Tarification::class)->devis($this->logement(), Formule::Heure, CarbonImmutable::parse('2026-11-13'), ['heure' => '14:00', 'duree' => 3]);

        $this->assertSame(10500, $devis->location);
        $this->assertSame(525, $devis->commission);   // 5 % par défaut
        $this->assertSame(11025, $devis->total);
        $this->assertSame('2026-11-13 17:00', $devis->fin->format('Y-m-d H:i'));
    }

    public function test_le_taux_de_commission_des_parametres_s_applique_aux_services(): void
    {
        Parametres::enregistrer(['commission_pourcentage' => 10]);
        $petitDej = Service::create(['nom' => 'Petit-déjeuner', 'prix' => 2500, 'unite' => 'par personne', 'actif' => true]);
        $autre = Service::create(['nom' => 'Chauffeur', 'prix' => 15000, 'unite' => 'la course', 'actif' => true]);
        $this->logement->services()->attach($petitDej);   // le chauffeur n'est pas proposé dans ce logement

        $devis = app(Tarification::class)->devis($this->logement(), Formule::Journee, CarbonImmutable::parse('2026-11-13'), ['jours' => 2], [$petitDej->id => 2, $autre->id => 1]);

        $this->assertSame(36000, $devis->location);
        $this->assertSame(5000, $devis->services);        // 2 petits-déjeuners, chauffeur ignoré
        $this->assertSame(4100, $devis->commission);      // 10 % de 41 000
        $this->assertSame(45100, $devis->total);
    }

    public function test_un_choix_incoherent_est_refuse(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(Tarification::class)->devis($this->logement(), Formule::Heure, CarbonImmutable::parse('2026-11-13'), ['heure' => '21:00', 'duree' => 3]);
    }

    /* ---------- Bloc « Réserver » ---------- */

    public function test_le_bloc_reserver_corrige_les_choix_impossibles(): void
    {
        $this->reserver('2026-11-13 08:00', '2026-11-13 13:00');

        $this->getJson('/logements/studio-test/disponibilites?date=2026-11-10&formule=demi_journee')
            ->assertOk()
            ->assertJsonPath('date', '2026-11-12');                    // date passée ramenée à aujourd'hui

        $this->getJson('/logements/studio-test/disponibilites?date=2026-11-13&formule=demi_journee&creneau_id=999')
            ->assertOk()
            ->assertJsonPath('creneaux.0.libre', false)                // Matin pris
            ->assertJsonPath('creneau_id', $this->logement->creneaux()->where('nom', 'Après-midi')->value('id'))
            ->assertJsonPath('devis.location', 10000)
            ->assertJsonPath('devis.total', 10500);

        $this->getJson('/logements/studio-test/disponibilites?date=2026-11-13&formule=heure&heure=18:00&duree=9')
            ->assertJsonPath('duree', 4)                               // ramenée à la durée possible avant 22:00
            ->assertJsonPath('devis.location', 14000);
    }
}
