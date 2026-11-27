<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\StatutLogement;
use App\Enums\TypeLogement;
use App\Enums\TypeMouvement;
use App\Models\FormuleAbonnement;
use App\Models\Logement;
use App\Models\User;
use App\Services\Abonnements;
use App\Services\Caisse;
use App\Services\GestionLogements;
use App\Services\ModeleEconomique;
use App\Services\ReservationImpossible;
use App\Support\Parametres;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Modèle « commission sur retrait » : réglage général, personnalisation par propriétaire, décompte du retrait. */
class CommissionSurRetraitTest extends TestCase
{
    use RefreshDatabase;

    private User $proprio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
        $this->proprio = tap(User::factory()->create(), fn (User $u) => $u->assignRole(Role::Proprietaire->value));
        $this->proprio->mouvementsSolde()->create(['type' => TypeMouvement::CreditSejour, 'montant' => 50000, 'libelle' => 'Séjour']);
    }

    private function logement(): Logement
    {
        return Logement::create([
            'proprietaire_id' => $this->proprio->id, 'titre' => 'Studio Lagune', 'slug' => Str::slug('Studio Lagune '.uniqid()),
            'type' => TypeLogement::Studio, 'ville' => 'Cotonou', 'quartier' => 'Fidjrossè', 'capacite' => 2,
            'statut' => StatutLogement::Publie, 'temps_preparation_minutes' => 60,
        ])->refresh();   // valeurs par défaut de la base (en_ligne)
    }

    public function test_sur_abonnement_le_retrait_est_verse_en_entier(): void
    {
        $retrait = app(Caisse::class)->demanderRetrait($this->proprio, 30000);

        $this->assertSame(0, $retrait->montant_commission);
        $this->assertSame(30000, $retrait->aVerser());
        $this->assertSame(20000, $this->proprio->solde());
    }

    public function test_commission_generale_retenue_sur_le_retrait(): void
    {
        Parametres::enregistrer(['modele_defaut' => 'commission', 'commission_retrait_taux' => 10]);

        $retrait = app(Caisse::class)->demanderRetrait($this->proprio, 30000);

        $this->assertSame(10.0, $retrait->taux_commission);
        $this->assertSame(3000, $retrait->montant_commission);
        $this->assertSame(27000, $retrait->aVerser());
        $this->assertSame(20000, $this->proprio->solde(), 'Le montant demandé entier sort du solde');
    }

    public function test_taux_personnalise_et_retrait_refuse_recredite_en_entier(): void
    {
        $this->proprio->update(['modele_economique' => 'commission', 'commission_retrait_taux' => 7.5]);

        $retrait = app(Caisse::class)->demanderRetrait($this->proprio, 20000);
        $this->assertSame(1500, $retrait->montant_commission);
        $this->assertSame(18500, $retrait->montant_net);

        app(Caisse::class)->refuserRetrait($retrait, User::factory()->create(), 'Numéro invalide');
        $this->assertSame(50000, $this->proprio->solde());
    }

    public function test_un_proprietaire_peut_rester_sur_abonnement_quand_la_regle_est_la_commission(): void
    {
        Parametres::enregistrer(['modele_defaut' => 'commission']);
        $this->proprio->update(['modele_economique' => 'abonnement']);

        $this->assertSame(0, app(Caisse::class)->demanderRetrait($this->proprio, 10000)->montant_commission);
        $this->assertFalse(Logement::visibles()->exists(), 'Sans abonnement actif, son logement reste invisible');
    }

    public function test_un_agent_marketing_n_a_jamais_de_commission(): void
    {
        Parametres::enregistrer(['modele_defaut' => 'commission']);
        $agent = tap(User::factory()->create(), fn (User $u) => $u->assignRole(Role::AgentMarketing->value));

        $this->assertSame(0.0, app(ModeleEconomique::class)->taux($agent));
    }

    public function test_sur_commission_les_logements_sont_visibles_sans_abonnement_et_les_quotas_viennent_des_parametres(): void
    {
        $logement = $this->logement();
        $this->assertFalse($logement->estVisible());

        Parametres::enregistrer(['modele_defaut' => 'commission', 'commission_logements_max' => 3]);

        $this->assertTrue($logement->estVisible());
        $this->assertTrue(Logement::visibles()->whereKey($logement->id)->exists());
        $quota = app(GestionLogements::class)->quota($this->proprio);
        $this->assertSame(3, $quota['max']);
        $this->assertTrue($quota['commission']);
    }

    public function test_sur_commission_on_ne_peut_pas_souscrire_de_formule(): void
    {
        $this->proprio->update(['modele_economique' => 'commission']);
        $formule = FormuleAbonnement::create(['nom' => 'Solo', 'prix_mensuel' => 5000, 'logements_max' => 1, 'salles_max' => 0, 'gestionnaires_max' => 0, 'actif' => true]);

        $this->expectException(ReservationImpossible::class);
        app(Abonnements::class)->souscrire($this->proprio, $formule, 'fedapay');
    }

    public function test_les_pages_publiques_presentent_la_commission_quand_c_est_la_regle(): void
    {
        $this->get(route('proprietaires'))->assertOk()->assertSee('Choisissez selon le nombre de logements');

        Parametres::enregistrer(['modele_defaut' => 'commission', 'commission_retrait_taux' => 12]);

        $this->get(route('proprietaires'))->assertOk()->assertSee('Commission sur retrait')->assertDontSee('Puis-je changer de formule');
        $this->get(route('accueil'))->assertOk()->assertSee('Sans abonnement')->assertSee('Conditions propriétaires');
        $this->get(route('aide.conditions'))->assertOk()->assertSee('prélevée sur chaque retrait');
    }

    public function test_la_page_revenus_affiche_le_decompte(): void
    {
        $this->proprio->update(['modele_economique' => 'commission', 'commission_retrait_taux' => 10]);

        $this->actingAs($this->proprio)->get(route('pro.revenus'))
            ->assertOk()
            ->assertSee('Commission VIVI (10', false)
            ->assertSee('Vous recevez');
    }
}
