<?php

namespace Tests\Feature;

use App\Enums\Formule;
use App\Enums\Role;
use App\Enums\StatutAbonnement;
use App\Enums\StatutLogement;
use App\Enums\StatutReservation;
use App\Enums\TypeLogement;
use App\Models\Abonnement;
use App\Models\FormuleAbonnement;
use App\Models\Logement;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use App\Notifications\ReservationDeplacee;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\AvecAbonnement;
use Tests\TestCase;

/** Espace propriétaire (lot 5) : scanner, logements, calendrier, abonnement. Aujourd'hui = jeudi 12 novembre 2026, 09:00. */
class EspaceProprietaireTest extends TestCase
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
        $this->client = tap(User::factory()->create(['name' => 'Mireille Adjovi']), fn (User $u) => $u->assignRole(Role::Client->value));
        $this->logement = $this->creerLogement($this->proprio, 'Studio Lagune');
    }

    private function creerLogement(User $proprio, string $titre, StatutLogement $statut = StatutLogement::Publie): Logement
    {
        $l = Logement::create([
            'proprietaire_id' => $proprio->id, 'titre' => $titre, 'slug' => Str::slug($titre), 'type' => TypeLogement::Studio,
            'ville' => 'Cotonou', 'quartier' => 'Fidjrossè', 'capacite' => 2, 'statut' => $statut, 'temps_preparation_minutes' => 60,
        ]);
        $l->tarifs()->create(['formule' => Formule::Heure, 'prix' => 3500, 'heure_ouverture' => '08:00', 'heure_fermeture' => '22:00', 'duree_minimum_heures' => 2]);

        return $l;
    }

    private function reservation(string $debut, string $fin, StatutReservation $statut = StatutReservation::Confirmee, ?Logement $l = null, array $autres = []): Reservation
    {
        return Reservation::create([
            'reference' => Reservation::nouvelleReference(), 'client_id' => $this->client->id, 'logement_id' => ($l ?? $this->logement)->id,
            'formule' => Formule::Heure, 'debut' => $debut, 'fin' => $fin, 'voyageurs' => 2, 'statut' => $statut,
            'montant_location' => 10500, 'montant_services' => 5000, 'taux_commission' => 5, 'montant_commission' => 775,
            'montant_total' => 16275, 'services_beneficiaire' => 'proprietaire', 'code_secours' => '482913',
        ] + $autres);
    }

    /* ---------- Scanner ---------- */

    public function test_le_code_de_secours_demarre_le_sejour_et_credite_le_solde(): void
    {
        $r = $this->reservation('2026-11-12 14:00', '2026-11-12 17:00');
        $this->actingAs($this->proprio);

        $this->postJson('/pro/scanner/verifier', ['saisie' => '482913'])->assertOk()
            ->assertJsonPath('valide', true)->assertJsonPath('reservation.reference', $r->reference);
        $this->postJson('/pro/scanner/demarrer', ['reference' => $r->reference])->assertOk()
            ->assertJsonPath('credit', "15\u{00A0}500\u{00A0}FCFA");     // location + services (attribués au propriétaire)

        $r->refresh();
        $this->assertSame(StatutReservation::EnCours, $r->statut);
        $this->assertSame($this->proprio->id, $r->scanne_par);
        $this->assertSame(15500, $this->proprio->solde());

        // Un QR Code ne sert qu'une fois
        $this->postJson('/pro/scanner/verifier', ['saisie' => '482913'])->assertStatus(422)->assertJsonPath('titre', 'QR Code déjà utilisé');
        $this->assertSame(15500, $this->proprio->solde());
    }

    public function test_le_qr_code_signe_est_accepte_et_un_qr_code_modifie_refuse(): void
    {
        $r = $this->reservation('2026-11-12 14:00', '2026-11-12 17:00');
        $this->actingAs($this->proprio);

        $this->postJson('/pro/scanner/verifier', ['saisie' => $r->jetonQr()])->assertOk()->assertJsonPath('valide', true);
        $this->postJson('/pro/scanner/verifier', ['saisie' => 'VIVI1.'.$r->reference.'.00000000000000000000'])
            ->assertStatus(422)->assertJsonPath('titre', 'QR Code non reconnu');
    }

    public function test_le_scanner_refuse_un_autre_jour_un_autre_logement_et_un_demarrage_non_verifie(): void
    {
        $demain = $this->reservation('2026-11-13 14:00', '2026-11-13 17:00');
        $autreProprio = tap(User::factory()->create(), fn (User $u) => $u->assignRole(Role::Proprietaire->value));
        $this->actingAs($this->proprio);

        $this->postJson('/pro/scanner/verifier', ['saisie' => '482913'])->assertStatus(422)->assertJsonPath('titre', 'Réservation prévue un autre jour');
        $this->postJson('/pro/scanner/demarrer', ['reference' => $demain->reference])->assertStatus(409);

        $this->actingAs($autreProprio)->postJson('/pro/scanner/verifier', ['saisie' => '482913'])
            ->assertStatus(422)->assertJsonPath('titre', 'Code inconnu');
        $this->assertSame(StatutReservation::Confirmee, $demain->fresh()->statut);
    }

    public function test_le_gestionnaire_accueille_les_clients_des_logements_confies(): void
    {
        $gestionnaire = tap(User::factory()->create(), fn (User $u) => $u->assignRole(Role::Gestionnaire->value));
        $this->logement->gestionnaires()->attach($gestionnaire);
        $r = $this->reservation('2026-11-12 14:00', '2026-11-12 17:00');

        $this->actingAs($gestionnaire)->get('/pro/scanner')->assertOk()->assertSee('Mireille Adjovi')->assertDontSee('482913');
        $this->postJson('/pro/scanner/verifier', ['saisie' => '482913'])->assertOk();
        $this->postJson('/pro/scanner/demarrer', ['reference' => $r->reference])->assertOk();
        $this->assertSame(15500, $this->proprio->solde());           // crédité au propriétaire, pas au gestionnaire
        $this->get('/pro/logements')->assertForbidden();
    }

    /* ---------- Mes logements ---------- */

    private function fiche(array $autres = []): array
    {
        return array_replace_recursive([
            'titre' => 'Résidence Les Cocotiers', 'type' => 'f2', 'ville' => 'Cotonou', 'quartier' => 'Cadjèhoun', 'adresse' => 'Rue 1',
            'capacite' => 3, 'chambres' => 1, 'lits' => 2, 'salles_de_bain' => 1, 'description' => 'Calme.', 'temps_preparation_minutes' => 60,
            'tarifs' => [
                'heure' => ['actif' => '1', 'prix' => 5000, 'heure_ouverture' => '08:00', 'heure_fermeture' => '00:00', 'duree_minimum_heures' => 2],
                'demi_journee' => ['actif' => '1', 'prix' => 14000],
                'journee' => ['prix' => 25000, 'heure_arrivee' => '12:00', 'heure_depart' => '11:00'],   // désactivée
            ],
            'creneaux' => [['nom' => 'Journée', 'heure_debut' => '09:00', 'heure_fin' => '15:00'], ['nom' => 'Soirée', 'heure_debut' => '20:00', 'heure_fin' => '01:00']],
        ], $autres);
    }

    public function test_un_nouveau_logement_part_en_verification_avec_ses_tarifs_et_creneaux(): void
    {
        $this->abonner($this->proprio);
        $service = Service::create(['nom' => 'Pack Boissons', 'prix' => 5000, 'unite' => 'le pack', 'actif' => true]);

        $this->actingAs($this->proprio)->post('/pro/logements', $this->fiche(['services' => [$service->id]]))->assertRedirect();

        $l = Logement::where('titre', 'Résidence Les Cocotiers')->firstOrFail();
        $this->assertSame(StatutLogement::EnValidation, $l->statut);
        $this->assertSame('residence-les-cocotiers', $l->slug);
        $this->assertSame(5000, $l->tarif(Formule::Heure)->prix);
        $this->assertNull($l->tarif(Formule::Journee));
        $this->assertSame(['Journée', 'Soirée'], $l->creneaux()->orderBy('ordre')->pluck('nom')->all());
        $this->assertTrue($l->services->contains($service));
        $this->assertFalse($l->estVisible());     // pas avant la validation par VIVI
    }

    public function test_la_position_du_logement_est_enregistree(): void
    {
        $this->actingAs($this->proprio)->post('/pro/logements', $this->fiche(['latitude' => '6.3651234', 'longitude' => '2.4183456']))->assertRedirect();
        $l = Logement::where('titre', 'Résidence Les Cocotiers')->firstOrFail();
        $this->assertSame('6.3651234', $l->latitude);
        $this->assertSame('2.4183456', $l->longitude);

        // Zone publique : décalée du point exact, mais à moins de 400 m (rayon du cercle affiché)
        [$la, $ln] = $l->positionApprochee();
        $ecart = \App\Support\Geo::distanceKm((float) $l->latitude, (float) $l->longitude, $la, $ln);
        $this->assertGreaterThan(0.1, $ecart);
        $this->assertLessThan(0.4, $ecart);

        $this->post('/pro/logements', $this->fiche(['titre' => 'Autre', 'latitude' => '6.36']))->assertSessionHasErrors('longitude');
    }

    public function test_sans_abonnement_le_logement_reste_en_brouillon(): void
    {
        $this->actingAs($this->proprio)->post('/pro/logements', $this->fiche())->assertRedirect()->assertSessionHas('statut');

        $this->assertSame(StatutLogement::Brouillon, Logement::where('titre', 'Résidence Les Cocotiers')->firstOrFail()->statut);
        $this->get('/pro/logements')->assertSee('Brouillon')->assertSee('Aucun abonnement actif');
    }

    public function test_la_fiche_logement_est_validee(): void
    {
        $this->actingAs($this->proprio)->post('/pro/logements', $this->fiche([
            'titre' => '', 'tarifs' => ['heure' => ['actif' => '0'], 'demi_journee' => ['actif' => '0']],
        ]))->assertSessionHasErrors(['titre', 'tarifs']);

        $this->post('/pro/logements', $this->fiche(['tarifs' => ['heure' => ['heure_ouverture' => '14:00', 'heure_fermeture' => '15:00']]]))
            ->assertSessionHasErrors('tarifs.heure.heure_fermeture');      // moins que la durée minimale
    }

    public function test_le_proprietaire_ouvre_son_logement_24h_sur_24(): void
    {
        $this->actingAs($this->proprio)->post('/pro/logements', $this->fiche(['tarifs' => ['heure' => ['ouvert_24h' => '1']]]))->assertSessionHasNoErrors();
        $l = Logement::where('titre', 'Résidence Les Cocotiers')->firstOrFail();
        $this->assertTrue($l->ouvert24h());
        $this->get('/pro/logements/'.$l->getRouteKey())->assertOk()->assertSee('Ouvert 24 h/24');

        // Retour à des horaires, avec une fermeture après minuit
        $this->post('/pro/logements/'.$l->getRouteKey(), $this->fiche(['tarifs' => ['heure' => ['heure_fermeture' => '02:00']]]))->assertSessionHasNoErrors();
        $this->assertFalse($l->fresh()->ouvert24h());
        $this->assertSame('De 08:00 à 02:00 (après minuit)', $l->fresh()->tarif(Formule::Heure)->horairesEnClair());
    }

    public function test_un_client_en_avance_avant_minuit_est_accueilli(): void
    {
        $r = $this->reservation('2026-11-13 00:00', '2026-11-13 03:00');
        $this->actingAs($this->proprio);
        $this->travelTo('2026-11-12 22:30');
        $this->postJson('/pro/scanner/verifier', ['saisie' => '482913'])->assertStatus(422);
        $this->travelTo('2026-11-12 23:30');
        $this->postJson('/pro/scanner/verifier', ['saisie' => '482913'])->assertOk();
        $this->postJson('/pro/scanner/demarrer', ['reference' => $r->reference])->assertOk();
    }

    public function test_modifier_le_logement_et_ses_creneaux(): void
    {
        $l = $this->logement;
        $c = $l->creneaux()->create(['nom' => 'Matin', 'heure_debut' => '08:00', 'heure_fin' => '13:00', 'ordre' => 1]);
        $donnees = $this->fiche(['titre' => 'Studio Lagune rénové']);
        $donnees['creneaux'] = [['id' => $c->id, 'nom' => 'Matinée', 'heure_debut' => '07:00', 'heure_fin' => '12:00']];   // le second créneau est retiré

        $this->actingAs($this->proprio)->post("/pro/logements/{$l->id}", $donnees)->assertRedirect();

        $l->refresh();
        $this->assertSame('Studio Lagune rénové', $l->titre);
        $this->assertSame('studio-lagune', $l->slug);                  // l'adresse de l'annonce ne change pas
        $this->assertSame(StatutLogement::Publie, $l->statut);         // un logement publié le reste
        $this->assertSame('Matinée', $c->fresh()->nom);
        $this->assertSame(1, $l->creneaux()->count());
    }

    public function test_un_proprietaire_ne_touche_pas_au_logement_d_un_autre(): void
    {
        $autre = tap(User::factory()->create(), fn (User $u) => $u->assignRole(Role::Proprietaire->value));

        $this->actingAs($autre)->get("/pro/logements/{$this->logement->id}")->assertNotFound();
        $this->post("/pro/logements/{$this->logement->id}", $this->fiche())->assertNotFound();
    }

    public function test_photos_ajout_principale_et_suppression(): void
    {
        Storage::fake('public');
        $this->actingAs($this->proprio);

        $this->post("/pro/logements/{$this->logement->id}/photos", [
            'photos' => [UploadedFile::fake()->image('salon.jpg', 1280, 960), UploadedFile::fake()->image('chambre.jpg', 1280, 960)],
        ])->assertRedirect();
        $photos = $this->logement->photos()->orderBy('ordre')->get();
        $this->assertCount(2, $photos);
        Storage::disk('public')->assertExists($photos[0]->chemin);

        $this->post("/pro/logements/{$this->logement->id}/photos/{$photos[1]->id}/principale")->assertRedirect();
        $this->assertSame($photos[1]->id, $this->logement->photos()->orderBy('ordre')->first()->id);

        $this->post("/pro/logements/{$this->logement->id}/photos/{$photos[0]->id}/supprimer")->assertRedirect();
        Storage::disk('public')->assertMissing($photos[0]->chemin);

        $this->post("/pro/logements/{$this->logement->id}/photos", ['photos' => [UploadedFile::fake()->image('petite.jpg', 300, 200)]])
            ->assertSessionHasErrors('photos.0');
    }

    /* ---------- Calendrier ---------- */

    public function test_bloquer_et_debloquer_une_journee(): void
    {
        $this->actingAs($this->proprio);

        $this->post("/pro/calendrier/{$this->logement->id}/blocages", ['jour' => '2026-11-20', 'motif' => 'Travaux'])->assertRedirect()->assertSessionHas('statut');
        $blocage = $this->logement->blocages()->firstOrFail();
        $this->get('/pro/calendrier?jour=2026-11-20')->assertOk()->assertSee('Travaux')->assertSee('Débloquer');

        $this->reservation('2026-11-21 10:00', '2026-11-21 12:00');
        $this->post("/pro/calendrier/{$this->logement->id}/blocages", ['jour' => '2026-11-21'])->assertSessionHasErrors('calendrier');

        $this->delete("/pro/calendrier/{$this->logement->id}/blocages/{$blocage->id}")->assertRedirect();
        $this->assertSame(0, $this->logement->blocages()->count());
    }

    public function test_deplacer_une_reservation_consommee(): void
    {
        $r = $this->reservation('2026-11-11 10:00', '2026-11-11 13:00', StatutReservation::Consommee);
        $this->reservation('2026-11-14 10:00', '2026-11-14 12:00');   // occupe le 14 au matin
        $this->actingAs($this->proprio);

        $this->post("/pro/reservations/{$r->reference}/deplacer", ['date' => '2026-11-14', 'heure' => '11:00'])
            ->assertSessionHasErrors('deplacement_'.$r->id);

        $this->post("/pro/reservations/{$r->reference}/deplacer", ['date' => '2026-11-14', 'heure' => '15:00'])->assertRedirect();
        $r->refresh();
        $this->assertSame(StatutReservation::Confirmee, $r->statut);
        $this->assertSame('2026-11-14 18:00', $r->fin->format('Y-m-d H:i'));   // même durée
        $this->assertSame('2026-11-11 10:00', $r->debut_initial->format('Y-m-d H:i'));
        $this->assertSame($this->proprio->id, $r->deplacee_par);
        Notification::assertSentTo($this->client, ReservationDeplacee::class);
    }

    public function test_deplacer_montre_les_jours_complets_et_les_heures_prises(): void
    {
        $r = $this->reservation('2026-11-11 10:00', '2026-11-11 13:00', StatutReservation::Consommee);
        $this->reservation('2026-11-14 10:00', '2026-11-14 12:00');
        $this->logement->blocages()->create(['date_debut' => '2026-11-16', 'date_fin' => '2026-11-16', 'motif' => 'Travaux']);
        $this->actingAs($this->proprio);

        $data = $this->getJson("/pro/reservations/{$r->reference}/deplacement?date=2026-11-14")->assertOk()->json('data');
        $heures = collect($data['heures'])->pluck('libre', 'heure');
        $this->assertSame('2026-11-14', $data['date']);
        $this->assertFalse($heures['10:00']);   // déjà prise : affichée barrée
        $this->assertTrue($heures['15:00']);
        $this->assertContains('2026-11-16', $data['jours_complets']);   // jour bloqué : grisé
        $this->assertNotContains('2026-11-14', $data['jours_complets']);

        // Le formulaire du calendrier grise le jour complet et barre l'heure prise
        $this->get('/pro/calendrier?jour=2026-11-11')->assertOk()->assertSee('· complet')->assertSee('slot--pris', false);
    }

    /* ---------- Abonnement ---------- */

    public function test_souscrire_un_abonnement_par_le_paiement(): void
    {
        $confort = FormuleAbonnement::create(['nom' => 'Confort', 'prix_mensuel' => 12000, 'logements_max' => 3, 'gestionnaires_max' => 1, 'actif' => true]);
        $this->actingAs($this->proprio);

        $this->get('/pro/abonnement')->assertOk()->assertSee('Aucun abonnement actif')->assertSee('Choisir Confort');
        $this->post("/pro/abonnement/{$confort->id}")->assertRedirect();
        $paiement = $this->proprio->abonnements()->firstOrFail()->paiements()->firstOrFail();
        $this->assertFalse($this->logement->estVisible());

        $this->post("/paiements/{$paiement->id}/simulation", ['choix' => 'reussi', 'moyen' => 'Moov Money']);
        $this->get("/paiements/{$paiement->id}/retour")->assertRedirect('/pro/abonnement');

        $actif = $this->proprio->abonnementActif()->first();
        $this->assertSame('Confort', $actif->formule->nom);
        $this->assertSame('2026-12-11', $actif->fin->toDateString());
        $this->assertTrue($this->logement->fresh()->estVisible());
        $this->get('/logements')->assertSee('Studio Lagune');
    }

    public function test_renouveler_prolonge_et_changer_de_formule_remplace(): void
    {
        $actuel = $this->abonner($this->proprio);          // jusqu'au 7 décembre
        $this->actingAs($this->proprio);
        $payer = function (FormuleAbonnement $f) {
            $this->post("/pro/abonnement/{$f->id}")->assertRedirect();
            $p = $this->proprio->abonnements()->latest('id')->first()->paiements()->firstOrFail();
            $this->post("/paiements/{$p->id}/simulation", ['choix' => 'reussi', 'moyen' => 'MTN Mobile Money']);
            $this->get("/paiements/{$p->id}/retour");
        };

        $payer(FormuleAbonnement::findOrFail($actuel->formule_abonnement_id));
        $renouvele = $this->proprio->abonnements()->latest('id')->first();
        $this->assertSame('2026-12-08', $renouvele->debut->toDateString());
        $this->assertSame(StatutAbonnement::Actif, $actuel->fresh()->statut);

        $premium = FormuleAbonnement::create(['nom' => 'Premium', 'prix_mensuel' => 25000, 'logements_max' => 20, 'gestionnaires_max' => 3, 'actif' => true]);
        $payer($premium);
        $this->assertSame(StatutAbonnement::Resilie, $actuel->fresh()->statut);
        $this->assertSame('Premium', $this->proprio->abonnementActif()->with('formule')->first()->formule->nom);
    }

    public function test_une_formule_trop_petite_est_refusee(): void
    {
        $this->abonner($this->proprio);
        $this->creerLogement($this->proprio, 'Second logement');
        $essentiel = FormuleAbonnement::create(['nom' => 'Essentiel', 'prix_mensuel' => 5000, 'logements_max' => 1, 'gestionnaires_max' => 0, 'actif' => true]);

        $this->actingAs($this->proprio)->post("/pro/abonnement/{$essentiel->id}")->assertSessionHasErrors('abonnement');
        $this->assertSame(0, Abonnement::where('statut', StatutAbonnement::EnAttente)->count());
    }

    public function test_sans_abonnement_les_logements_sortent_de_la_recherche(): void
    {
        $this->get('/logements')->assertDontSee('Studio Lagune');
        $this->get('/logements/studio-lagune')->assertNotFound();

        $this->abonner($this->proprio);
        $this->get('/logements')->assertSee('Studio Lagune');
    }
}
