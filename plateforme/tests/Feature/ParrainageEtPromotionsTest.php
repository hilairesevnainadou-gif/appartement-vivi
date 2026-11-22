<?php

namespace Tests\Feature;

use App\Enums\Formule;
use App\Enums\Role;
use App\Enums\StatutAbonnement;
use App\Enums\StatutLogement;
use App\Enums\StatutReservation;
use App\Enums\TypeLogement;
use App\Models\Bonus;
use App\Models\FormuleAbonnement;
use App\Models\Logement;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\BonusRecu;
use App\Notifications\CodeDeConnexion;
use App\Paiement\EtatTransaction;
use App\Services\Abonnements;
use App\Services\ApresVente;
use App\Services\Arrivees;
use App\Services\Parrainage;
use App\Services\Reservations;
use App\Support\Parametres;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\AvecAbonnement;
use Tests\TestCase;

/** Parrainage (code d'invitation, bonus déductible) et promotions sur les abonnements. Aujourd'hui = jeudi 12 novembre 2026, 09:00. */
class ParrainageEtPromotionsTest extends TestCase
{
    use AvecAbonnement, RefreshDatabase;

    private User $proprio;

    private User $parrain;

    private Logement $logement;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00'));
        Notification::fake();
        Parametres::enregistrer([
            'parrainage_actif' => true, 'parrainage_bonus_parrain' => 2000, 'parrainage_bonus_filleul' => 1000,
            'parrainage_validite_jours' => 90, 'bonus_plafond_pourcentage' => 50,
        ]);

        $this->proprio = $this->utilisateur(Role::Proprietaire, 'Rodrigue Houngbo');
        $this->abonner($this->proprio);
        $this->parrain = $this->utilisateur(Role::Client, 'Géraud Kiki');
        $this->logement = Logement::create([
            'proprietaire_id' => $this->proprio->id, 'titre' => 'Studio Lagune', 'slug' => 'studio-lagune', 'type' => TypeLogement::Studio,
            'ville' => 'Cotonou', 'quartier' => 'Fidjrossè', 'capacite' => 2, 'statut' => StatutLogement::Publie, 'temps_preparation_minutes' => 60,
        ]);
        $this->logement->tarifs()->create(['formule' => Formule::Heure, 'prix' => 3500, 'heure_ouverture' => '08:00', 'heure_fermeture' => '22:00', 'duree_minimum_heures' => 2]);
    }

    private function utilisateur(Role $role, string $nom = 'Personne Test'): User
    {
        return tap(User::factory()->create(['name' => $nom, 'actif' => true]), fn (User $u) => $u->assignRole($role->value));
    }

    /** Inscription complète par le formulaire, avec le code d'invitation du parrain. */
    private function inscrireAvecCode(string $code, string $telephone = '01 97 00 00 41'): User
    {
        $this->post('/connexion', ['mode' => 'inscription', 'nom' => 'Mireille Adjovi', 'role' => 'client', 'identifiant' => $telephone, 'invitation' => $code])
            ->assertRedirect('/connexion/code');
        $code = null;
        Notification::assertSentOnDemand(CodeDeConnexion::class, function (CodeDeConnexion $n) use (&$code) {
            $code = $n->code;

            return true;
        });
        $this->post('/connexion/code', ['code' => $code])->assertRedirect();

        return User::where('telephone', preg_replace('/\D/', '', $telephone))->firstOrFail();
    }

    /** Réservation de 3 h demain (10 500 + 5 % = 11 025 FCFA), payée avec le bonus si demandé. */
    private function reserverEtPayer(User $client, bool $bonus, string $heure = '14:00'): Reservation
    {
        $service = app(Reservations::class);
        $r = $service->bloquer($client, $this->logement, ['date' => '2026-11-13', 'formule' => 'heure', 'heure' => $heure, 'duree' => 3, 'voyageurs' => 2]);
        $paiement = $service->preparerPaiement($r, [], 'simulation', $bonus);
        $service->confirmer($paiement, new EtatTransaction(EtatTransaction::PAYEE, 'MTN Mobile Money'));

        return $r->fresh();
    }

    /* ---------- Parrainage ---------- */

    public function test_le_filleul_inscrit_avec_un_code_recoit_son_bonus_de_bienvenue(): void
    {
        $code = app(Parrainage::class)->codeDe($this->parrain);
        $this->assertMatchesRegularExpression('/^GERA-[A-Z2-9]{4}$/', $code);

        $this->get('/connexion?invitation='.$code)->assertOk()->assertSee('value="'.$code.'"', false);
        $filleul = $this->inscrireAvecCode(strtolower($code));

        $this->assertTrue($filleul->parrain->is($this->parrain));
        $this->assertSame(1000, app(Parrainage::class)->disponible($filleul));
    }

    public function test_un_code_inconnu_est_refuse(): void
    {
        $this->post('/connexion', ['mode' => 'inscription', 'nom' => 'Mireille Adjovi', 'role' => 'client', 'identifiant' => '0197000041', 'invitation' => 'NIMP-0000'])
            ->assertSessionHasErrors('invitation');
    }

    public function test_le_bonus_paie_une_partie_de_la_reservation_puis_le_parrain_est_recompense_au_premier_sejour(): void
    {
        $filleul = $this->utilisateur(Role::Client, 'Mireille Adjovi');
        app(Parrainage::class)->rattacher($filleul, app(Parrainage::class)->codeDe($this->parrain));

        $r = $this->reserverEtPayer($filleul, bonus: true);
        $this->assertSame(StatutReservation::Confirmee, $r->statut);
        $this->assertSame(11025, $r->montant_total);
        $this->assertSame(1000, $r->montant_bonus);
        $this->assertSame(10025, $r->paiements()->latest('id')->first()->montant);
        $this->assertSame(0, app(Parrainage::class)->disponible($filleul));

        // Paiement seul : pas encore de bonus pour le parrain
        $this->assertSame(0, app(Parrainage::class)->disponible($this->parrain));

        // Arrivée du filleul (QR Code scanné) : le parrain reçoit 2 000 FCFA, une seule fois
        $this->travelTo(CarbonImmutable::parse('2026-11-13 14:05'));
        app(Arrivees::class)->demarrer($this->proprio, $r);
        $this->assertSame(2000, app(Parrainage::class)->disponible($this->parrain));
        Notification::assertSentTo($this->parrain, BonusRecu::class);

        // Le propriétaire reçoit sa location entière : c'est VIVI qui finance le bonus
        $this->assertSame(10500, (int) $this->proprio->mouvementsSolde()->sum('montant'));

        $autre = $this->reserverEtPayer($filleul, bonus: false, heure: '19:00');
        $this->travelTo(CarbonImmutable::parse('2026-11-13 18:00'));
        $this->assertSame(1, Bonus::where('user_id', $this->parrain->id)->count());
        $this->assertSame(0, $autre->montant_bonus);
    }

    public function test_le_bonus_est_plafonne_et_rendu_si_le_paiement_n_aboutit_pas(): void
    {
        $client = $this->utilisateur(Role::Client);
        app(Parrainage::class)->crediter($client, 20000, Bonus::MOTIF_GESTE, 'Geste');

        $service = app(Reservations::class);
        $r = $service->bloquer($client, $this->logement, ['date' => '2026-11-13', 'formule' => 'heure', 'heure' => '14:00', 'duree' => 3, 'voyageurs' => 2]);
        $paiement = $service->preparerPaiement($r, [], 'simulation', true);
        $this->assertSame(5512, $r->fresh()->montant_bonus);              // 50 % de 11 025
        $this->assertSame(11025 - 5512, $paiement->montant);
        $this->assertSame(20000 - 5512, app(Parrainage::class)->disponible($client));

        // Le client renonce au bonus en revenant au paiement
        $service->preparerPaiement($r, [], 'simulation', false);
        $this->assertSame(0, $r->fresh()->montant_bonus);
        $this->assertSame(20000, app(Parrainage::class)->disponible($client));

        // Retenue expirée sans paiement : le bonus revient aussi
        $service->preparerPaiement($r, [], 'simulation', true);
        $this->travelTo(now()->addHours(2));
        $service->libererExpirees();
        $this->assertSame(20000, app(Parrainage::class)->disponible($client));
    }

    public function test_a_l_annulation_le_bonus_est_rendu_avant_l_argent(): void
    {
        $client = $this->utilisateur(Role::Client);
        app(Parrainage::class)->crediter($client, 3000, Bonus::MOTIF_GESTE, 'Geste');
        $r = $this->reserverEtPayer($client, bonus: true);           // 3 000 de bonus + 8 025 payés

        $this->travelTo(CarbonImmutable::parse('2026-11-12 10:00'));   // plus de 24 h avant : location remboursée
        $calcul = app(ApresVente::class)->remboursement($r);
        $this->assertSame(10500, $calcul['bonus_rendu'] + $calcul['rembourse']);   // tout sauf la commission (525)
        $this->assertSame(3000, $calcul['bonus_rendu']);
        $this->assertSame(7500, $calcul['rembourse']);

        app(ApresVente::class)->annuler($r, null);
        $this->assertSame(7500, $r->fresh()->montant_rembourse);
        $this->assertSame(3000, app(Parrainage::class)->disponible($client));
    }

    public function test_page_parrainage(): void
    {
        $this->actingAs($this->parrain)->get('/parrainage')->assertOk()
            ->assertSee('Votre code d’invitation', false)->assertSee(app(Parrainage::class)->codeDe($this->parrain))
            ->assertSee('wa.me/?text=', false);

        Parametres::enregistrer(['parrainage_actif' => false]);
        $this->get('/parrainage')->assertOk()->assertSee('pas ouvert pour le moment', false);
        $this->get('/connexion?mode=inscription')->assertDontSee('Code d’invitation', false);
    }

    /* ---------- Abonnements : essai et promotion ---------- */

    public function test_essai_gratuit_une_seule_fois(): void
    {
        Parametres::enregistrer(['essai_actif' => true, 'essai_jours' => 14, 'essai_formule_id' => 0]);
        $nouveau = $this->utilisateur(Role::Proprietaire);
        $formule = FormuleAbonnement::create(['nom' => 'Confort', 'prix_mensuel' => 12000, 'logements_max' => 3, 'gestionnaires_max' => 1, 'actif' => true]);

        $this->actingAs($nouveau)->get('/pro/abonnement')->assertSee('Essayer 14 jours gratuits');
        $this->post("/pro/abonnement/{$formule->id}/essai")->assertRedirect('/pro/abonnement')->assertSessionHas('statut');

        $essai = $nouveau->abonnementActif()->first();
        $this->assertTrue($essai->essai);
        $this->assertSame('2026-11-25', $essai->fin->format('Y-m-d'));
        $this->assertSame(0, $essai->prix_paye);

        $this->post("/pro/abonnement/{$formule->id}/essai")->assertSessionHasErrors('abonnement');
        $this->get('/pro/abonnement')->assertDontSee('Essayer 14 jours gratuits')->assertSee('Essai gratuit');
    }

    public function test_promotion_sur_la_periode_et_pour_les_nouveaux(): void
    {
        Parametres::enregistrer(['promo_actif' => true, 'promo_pourcentage' => 25, 'promo_libelle' => 'Offre de lancement',
            'promo_debut' => '2026-11-01', 'promo_fin' => '2026-11-30', 'promo_nouveaux_seulement' => true]);
        $formule = FormuleAbonnement::create(['nom' => 'Confort', 'prix_mensuel' => 12000, 'logements_max' => 3, 'gestionnaires_max' => 1, 'actif' => true]);
        $nouveau = $this->utilisateur(Role::Proprietaire);
        $abonnements = app(Abonnements::class);

        $paiement = $abonnements->souscrire($nouveau, $formule, 'simulation');
        $this->assertSame(9000, $paiement->montant);
        $this->assertSame(3000, $paiement->payable->montant_remise);
        $this->assertSame('Offre de lancement', $paiement->payable->libelle_promo);

        $this->get('/proprietaires')->assertSee('Offre de lancement')->assertSee("9\u{00A0}000");

        // Déjà abonné payant : pas de remise ; hors période : pas de remise
        $abonnements->activer($paiement, new EtatTransaction(EtatTransaction::PAYEE));
        $this->assertSame(12000, $abonnements->tarif($nouveau, $formule)['net']);
        $this->travelTo(CarbonImmutable::parse('2026-12-01 09:00'));
        $this->assertNull($abonnements->promotionEnCours());
        $this->assertSame(StatutAbonnement::Actif, $nouveau->abonnementActif()->first()->statut);
    }
}
