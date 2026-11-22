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
use App\Notifications\CodeDeConnexion;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AvecAbonnement;
use Tests\TestCase;

/** API de l'application mobile (/api/v1). Aujourd'hui = jeudi 12 novembre 2026, 09:00. */
class ApiMobileTest extends TestCase
{
    use AvecAbonnement, RefreshDatabase;

    private User $proprio;

    private User $client;

    private Logement $logement;

    private Service $petitDej;

    private const DEMANDE = ['date' => '2026-11-13', 'formule' => 'heure', 'heure' => '14:00', 'duree' => 3, 'voyageurs' => 2];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00'));
        Notification::fake();

        $this->proprio = tap(User::factory()->create(['name' => 'Rodrigue Houngbo', 'actif' => true]), fn (User $u) => $u->assignRole(Role::Proprietaire->value));
        $this->abonner($this->proprio);
        $this->client = tap(User::factory()->create(['name' => 'Mireille Adjovi', 'telephone' => null, 'actif' => true]), fn (User $u) => $u->assignRole(Role::Client->value));

        $this->logement = Logement::create([
            'proprietaire_id' => $this->proprio->id, 'titre' => 'Studio Lagune', 'slug' => 'studio-lagune', 'type' => TypeLogement::Studio,
            'ville' => 'Cotonou', 'quartier' => 'Fidjrossè', 'adresse' => 'Rue 12.345', 'capacite' => 2, 'statut' => StatutLogement::Publie,
            'temps_preparation_minutes' => 60,
        ]);
        $this->logement->tarifs()->create(['formule' => Formule::Heure, 'prix' => 3500, 'heure_ouverture' => '08:00', 'heure_fermeture' => '22:00', 'duree_minimum_heures' => 2]);
        $this->petitDej = Service::create(['nom' => 'Petit-déjeuner', 'prix' => 2500, 'unite' => 'par personne', 'actif' => true]);
        $this->logement->services()->attach($this->petitDej);
    }

    private function codeEnvoye(): string
    {
        $code = null;
        Notification::assertSentOnDemand(CodeDeConnexion::class, function (CodeDeConnexion $n) use (&$code) {
            $code = $n->code;

            return true;
        });

        return $code;
    }

    /* ---------- Connexion ---------- */

    public function test_inscription_par_code_puis_jeton_d_acces(): void
    {
        $demande = $this->postJson('/api/v1/auth/code', [
            'mode' => 'inscription', 'identifiant' => 'Awa@Exemple.com', 'nom' => 'Awa Dossou', 'role' => 'proprietaire',
        ])->assertCreated()->assertJsonPath('canal', 'mail')->json('demande');

        $this->postJson('/api/v1/auth/verifier', ['demande' => $demande, 'code' => '000000'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');

        $reponse = $this->postJson('/api/v1/auth/verifier', ['demande' => $demande, 'code' => $this->codeEnvoye(), 'appareil' => 'Tecno Spark'])
            ->assertOk()
            ->assertJsonPath('nouveau_compte', true)
            ->assertJsonPath('utilisateur.email', 'awa@exemple.com')
            ->assertJsonPath('utilisateur.espace', 'pro')
            ->assertJsonPath('utilisateur.est_proprietaire', true);

        $jeton = $reponse->json('jeton');
        $this->withToken($jeton)->getJson('/api/v1/moi')->assertOk()->assertJsonPath('data.nom', 'Awa Dossou');
        $this->assertSame('Tecno Spark', User::where('email', 'awa@exemple.com')->first()->tokens()->first()->name);

        // La demande ne sert qu'une fois
        $this->postJson('/api/v1/auth/verifier', ['demande' => $demande, 'code' => '123456'])->assertJsonValidationErrors('demande');

        // Déconnexion : le jeton n'est plus accepté
        $this->withToken($jeton)->postJson('/api/v1/auth/deconnexion')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($jeton)->getJson('/api/v1/moi')->assertUnauthorized()->assertJsonPath('message', 'Connectez-vous pour continuer.');
    }

    public function test_connexion_refusee_pour_un_compte_inconnu(): void
    {
        $this->postJson('/api/v1/auth/code', ['mode' => 'connexion', 'identifiant' => 'inconnu@exemple.com'])
            ->assertUnprocessable()->assertJsonValidationErrors('identifiant');
    }

    /* ---------- Catalogue ---------- */

    public function test_recherche_fiche_et_disponibilites_sans_connexion(): void
    {
        $this->getJson('/api/v1/configuration')->assertOk()->assertJsonPath('data.villes.0', 'Cotonou');

        $this->getJson('/api/v1/logements?ville=Cotonou&date=2026-11-13&voyageurs=2')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'studio-lagune')
            ->assertJsonPath('data.0.a_partir_de.prix', 3500)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissingPath('data.0.adresse');

        $this->getJson('/api/v1/logements/studio-lagune')
            ->assertOk()->assertJsonPath('data.services.0.nom', 'Petit-déjeuner')->assertJsonPath('apercu', false);

        $this->getJson('/api/v1/logements/studio-lagune/disponibilites?'.http_build_query(self::DEMANDE))
            ->assertOk()->assertJsonPath('data.formule', 'heure')->assertJsonPath('data.heure', '14:00');

        $this->getJson('/api/v1/logements/inconnu')->assertNotFound()->assertJsonPath('message', 'Introuvable.');
    }

    /* ---------- Tunnel client ---------- */

    public function test_reservation_paiement_simule_et_billet(): void
    {
        Sanctum::actingAs($this->client);

        $tunnel = $this->postJson('/api/v1/logements/studio-lagune/reserver', self::DEMANDE)
            ->assertCreated()
            ->assertJsonPath('data.statut.valeur', 'en_attente_paiement')
            ->assertJsonPath('data.services_proposes.0.nom', 'Petit-déjeuner')
            ->assertJsonPath('data.billet', null);
        $reference = $tunnel->json('data.reference');

        $this->postJson("/api/v1/reservations/{$reference}/devis", ['services' => [$this->petitDej->id => 2]])
            ->assertOk()->assertJsonPath('data.location', 10500);

        $paiement = $this->postJson("/api/v1/reservations/{$reference}/payer", [
            'nom' => 'Mireille Adjovi', 'telephone' => '01 97 00 00 11', 'cgv' => true, 'services' => [$this->petitDej->id => 1],
        ])->assertOk()->assertJsonPath('data.paiement.termine', false)->json('data');
        $this->assertStringContainsString('/simulation', $paiement['url_paiement']);
        $id = $paiement['paiement']['id'];

        // Page de paiement simulée → retour vers l'application par le lien profond
        $this->post("/paiements/{$id}/simulation", ['choix' => 'reussi', 'moyen' => 'MTN Mobile Money'])->assertRedirect("/paiements/{$id}/retour-app");
        $this->get("/paiements/{$id}/retour-app")->assertRedirect("vivi://paiement/{$id}?statut=reussi");

        $this->getJson("/api/v1/paiements/{$id}")->assertOk()
            ->assertJsonPath('data.statut.valeur', 'reussi')->assertJsonPath('data.objet.reference', $reference);

        $billet = $this->getJson("/api/v1/reservations/{$reference}")->assertOk()
            ->assertJsonPath('data.statut.valeur', 'confirmee')
            ->assertJsonPath('data.logement.adresse', 'Rue 12.345, Fidjrossè, Cotonou')
            ->assertJsonPath('data.actions.annuler', true)
            ->json('data');
        $this->assertStringStartsWith('VIVI1.'.$reference.'.', $billet['billet']['qr_contenu']);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $billet['billet']['code_secours']);

        $this->getJson('/api/v1/reservations')->assertOk()->assertJsonPath('data.0.reference', $reference)->assertJsonPath('data.0.billet', null);
        $this->getJson("/api/v1/reservations/{$reference}/annulation")->assertOk()->assertJsonStructure(['data' => ['regle', 'rembourse']]);
    }

    public function test_un_client_ne_voit_pas_la_reservation_d_un_autre_ni_l_espace_pro(): void
    {
        $autre = tap(User::factory()->create(['actif' => true]), fn (User $u) => $u->assignRole(Role::Client->value));
        Sanctum::actingAs($autre);
        $reference = $this->postJson('/api/v1/logements/studio-lagune/reserver', self::DEMANDE)->json('data.reference');

        Sanctum::actingAs($this->client);
        $this->getJson("/api/v1/reservations/{$reference}")->assertNotFound();
        $this->getJson('/api/v1/pro/tableau-de-bord')->assertForbidden();
    }

    /* ---------- Espace pro ---------- */

    private function reservationConfirmee(string $debut = '2026-11-12 14:00'): Reservation
    {
        return Reservation::create([
            'reference' => Reservation::nouvelleReference(), 'client_id' => $this->client->id, 'logement_id' => $this->logement->id,
            'formule' => Formule::Heure, 'debut' => $debut, 'fin' => CarbonImmutable::parse($debut)->addHours(3), 'voyageurs' => 2,
            'statut' => StatutReservation::Confirmee, 'montant_location' => 10500, 'montant_services' => 0, 'taux_commission' => 5,
            'montant_commission' => 525, 'montant_total' => 11025, 'code_secours' => '482913',
        ]);
    }

    public function test_le_proprietaire_scanne_le_qr_code_et_son_solde_est_credite(): void
    {
        $r = $this->reservationConfirmee();
        Sanctum::actingAs($this->proprio);

        $this->getJson('/api/v1/pro/tableau-de-bord')->assertOk()
            ->assertJsonPath('data.est_proprietaire', true)->assertJsonPath('data.arrivees_du_jour.0.reference', $r->reference);
        $this->getJson('/api/v1/pro/arrivees')->assertOk()->assertJsonPath('data.0.client.nom', 'Mireille Adjovi');

        // Démarrer sans vérification préalable : refusé
        $this->postJson('/api/v1/pro/scanner/demarrer', ['reference' => $r->reference])->assertStatus(409);

        $this->postJson('/api/v1/pro/scanner/verifier', ['saisie' => 'VIVI1.'.$r->reference.'.0000000000aaaaaaaaaa'])
            ->assertUnprocessable()->assertJsonPath('valide', false);
        $this->postJson('/api/v1/pro/scanner/verifier', ['saisie' => $r->jetonQr()])
            ->assertOk()->assertJsonPath('valide', true)->assertJsonPath('data.reference', $r->reference);
        $this->postJson('/api/v1/pro/scanner/demarrer', ['reference' => $r->reference])
            ->assertOk()->assertJsonPath('data.credit', 10500);

        $this->assertSame(StatutReservation::EnCours, $r->fresh()->statut);
        $this->getJson('/api/v1/pro/revenus')->assertOk()->assertJsonPath('data.solde', 10500);

        $this->putJson('/api/v1/compte-retrait', ['moyen' => 'MTN Mobile Money', 'numero' => '12', 'titulaire' => 'Propriétaire'])->assertJsonValidationErrors('numero');
        $this->putJson('/api/v1/compte-retrait', ['moyen' => 'Espèces', 'numero' => '0197000001', 'titulaire' => 'Propriétaire'])->assertJsonValidationErrors('moyen');
        $this->putJson('/api/v1/compte-retrait', ['moyen' => 'MTN Mobile Money', 'numero' => '0197000001', 'titulaire' => 'Propriétaire'])->assertOk();
        $this->postJson('/api/v1/pro/retraits', ['montant' => 5000])->assertCreated()->assertJsonPath('data.montant', 5000);
        $this->getJson('/api/v1/pro/revenus')->assertJsonPath('data.solde', 5500);
    }

    public function test_le_gestionnaire_accueille_mais_ne_gere_ni_logements_ni_revenus(): void
    {
        $gestionnaire = tap(User::factory()->create(['actif' => true]), fn (User $u) => $u->assignRole(Role::Gestionnaire->value));
        $gestionnaire->logementsDelegues()->attach($this->logement);
        $r = $this->reservationConfirmee();
        Sanctum::actingAs($gestionnaire);

        $this->getJson('/api/v1/pro/tableau-de-bord')->assertOk()->assertJsonPath('data.est_proprietaire', false)->assertJsonPath('data.solde', null);
        $this->postJson('/api/v1/pro/scanner/verifier', ['saisie' => '482913'])->assertOk()->assertJsonPath('data.reference', $r->reference);
        $this->getJson("/api/v1/pro/logements/{$this->logement->id}/calendrier?mois=2026-11&jour=2026-11-12")
            ->assertOk()->assertJsonPath('data.jour.reservations.0.reference', $r->reference)->assertJsonPath('data.jours.11.etat', 'partiel');

        $this->getJson('/api/v1/pro/logements')->assertForbidden();
        $this->getJson('/api/v1/pro/revenus')->assertForbidden();
    }

    public function test_le_proprietaire_cree_un_logement_et_bloque_une_journee(): void
    {
        Sanctum::actingAs($this->proprio);

        $cree = $this->postJson('/api/v1/pro/logements', [
            'titre' => 'Appartement Haie Vive', 'type' => 'f3', 'ville' => 'Cotonou', 'quartier' => 'Haie Vive',
            'capacite' => 4, 'chambres' => 2, 'lits' => 2, 'salles_de_bain' => 1, 'temps_preparation_minutes' => 60,
            'tarifs' => ['journee' => ['actif' => true, 'prix' => 25000, 'heure_arrivee' => '14:00', 'heure_depart' => '12:00']],
        ])->assertCreated()->assertJsonPath('data.statut.valeur', 'en_validation')->assertJsonPath('data.tarifs.journee.prix', 25000);
        $id = $cree->json('data.id');

        $this->getJson('/api/v1/pro/logements')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/pro/logements/{$id}")->assertOk()->assertJsonPath('data.tarifs.heure.actif', false);

        // Un autre propriétaire n'y a pas accès
        $autre = tap(User::factory()->create(['actif' => true]), fn (User $u) => $u->assignRole(Role::Proprietaire->value));
        Sanctum::actingAs($autre);
        $this->getJson("/api/v1/pro/logements/{$id}")->assertNotFound();

        Sanctum::actingAs($this->proprio);
        $this->postJson("/api/v1/pro/logements/{$this->logement->id}/blocages", ['jour' => '2026-11-20', 'motif' => 'Travaux'])->assertCreated();
        $this->getJson('/api/v1/logements/studio-lagune/disponibilites?date=2026-11-20&formule=heure')
            ->assertOk()->assertJsonPath('data.heure', null);
    }

    public function test_reservation_hors_plateforme_et_encaissement(): void
    {
        Sanctum::actingAs($this->proprio);

        $ref = $this->postJson("/api/v1/pro/logements/{$this->logement->id}/reservations", [
            'formule' => 'heure', 'date' => '2026-11-13', 'heure' => '10:00', 'duree' => 2, 'voyageurs' => 1,
            'nom' => 'Client au comptoir', 'montant' => 7000, 'encaisse' => 2000,
        ])->assertOk()->assertJsonPath('data.origine', 'manuelle')->assertJsonPath('data.reste_a_payer', 5000)->json('data.reference');

        $this->postJson("/api/v1/pro/reservations/{$ref}/encaisser", ['montant' => 5000])->assertOk()->assertJsonPath('data.reste_a_payer', 0);
        $this->getJson('/api/v1/pro/reservations?onglet=avenir')->assertOk()->assertJsonPath('meta.compteurs.avenir', 1);
    }

    public function test_abonnement_et_paiement_par_l_application(): void
    {
        Sanctum::actingAs($this->proprio);
        $formule = \App\Models\FormuleAbonnement::first();

        $this->getJson('/api/v1/pro/abonnement')->assertOk()->assertJsonPath('data.actif.formule', $formule->nom);
        $id = $this->postJson("/api/v1/pro/abonnement/{$formule->id}")->assertOk()->json('data.paiement.id');
        $this->assertSame(StatutPaiement::EnAttente, Paiement::find($id)->statut);
        $this->assertSame(url("/paiements/{$id}/retour-app"), Paiement::find($id)->donnees['retour']);
    }
}
