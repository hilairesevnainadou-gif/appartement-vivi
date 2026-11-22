<?php

namespace Tests\Feature;

use App\Enums\Formule;
use App\Enums\Role;
use App\Enums\StatutLogement;
use App\Enums\StatutReservation;
use App\Enums\TypeLogement;
use App\Enums\TypeMarketing;
use App\Enums\TypeMouvement;
use App\Models\Logement;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\AgentDesigne;
use App\Services\AgentsMarketing;
use App\Services\Arrivees;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AvecAbonnement;
use Tests\TestCase;

/** Agents marketing : lien d'affiliation, part prise sur la location, classement, espace agent. */
class AgentsMarketingTest extends TestCase
{
    use AvecAbonnement, RefreshDatabase;

    private User $proprio;

    private User $client;

    private Logement $logement;

    /** 3 h à 3 500 FCFA = 10 500 FCFA de location ; 10 % = 1 050 FCFA pour l'agent. */
    private const DEMANDE = ['date' => '2026-11-13', 'formule' => 'heure', 'heure' => '14:00', 'duree' => 3, 'voyageurs' => 2];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00'));
        Notification::fake();

        $this->proprio = tap(User::factory()->create(['name' => 'Rodrigue Houngbo', 'actif' => true]), fn (User $u) => $u->assignRole(Role::Proprietaire->value));
        $this->abonner($this->proprio);
        $this->client = tap(User::factory()->create(['name' => 'Mireille Adjovi', 'actif' => true]), fn (User $u) => $u->assignRole(Role::Client->value));
        $this->logement = $this->logement('Studio Lagune', TypeMarketing::Pourcentage, 10);
    }

    private function logement(string $titre, ?TypeMarketing $type = null, float $valeur = 0, ?User $proprio = null): Logement
    {
        $l = Logement::create([
            'proprietaire_id' => ($proprio ?? $this->proprio)->id, 'titre' => $titre, 'slug' => \Illuminate\Support\Str::slug($titre), 'type' => TypeLogement::Studio,
            'ville' => 'Cotonou', 'quartier' => 'Fidjrossè', 'capacite' => 2, 'statut' => StatutLogement::Publie, 'temps_preparation_minutes' => 60,
            'marketing_type' => $type, 'marketing_valeur' => $valeur,
        ]);
        $l->tarifs()->create(['formule' => Formule::Heure, 'prix' => 3500, 'heure_ouverture' => '08:00', 'heure_fermeture' => '22:00', 'duree_minimum_heures' => 2]);

        return $l;
    }

    private function agent(string $nom = 'Awa Dossou', ?Logement $logement = null): User
    {
        return app(AgentsMarketing::class)->designer($this->proprio, $nom, mb_strtolower(str_replace(' ', '.', $nom)).'@exemple.com', [($logement ?? $this->logement)->id]);
    }

    /** Réservation retenue par l'API avec le code de l'agent, puis payée et scannée à l'arrivée. */
    private function sejourScanne(?string $code, ?User $client = null): Reservation
    {
        Sanctum::actingAs($client ?? $this->client);
        $reference = $this->postJson("/api/v1/logements/{$this->logement->slug}/reserver", self::DEMANDE + ['agent' => $code])->assertCreated()->json('data.reference');
        $r = Reservation::where('reference', $reference)->firstOrFail();
        $r->update(['statut' => StatutReservation::Confirmee, 'debut' => now()->addMinutes(30), 'fin' => now()->addHours(3)]);
        app(Arrivees::class)->demarrer($this->proprio, $r);

        return $r->fresh();
    }

    public function test_le_proprietaire_designe_un_agent_qui_recoit_ses_liens(): void
    {
        $this->actingAs($this->proprio)->post('/pro/agents', ['nom' => 'Awa Dossou', 'identifiant' => 'awa@exemple.com', 'logements' => [$this->logement->id]])
            ->assertRedirect()->assertSessionHasNoErrors();

        $agent = User::where('email', 'awa@exemple.com')->firstOrFail();
        $this->assertTrue($agent->hasRole(Role::AgentMarketing->value));
        $this->assertTrue($agent->hasRole(Role::Client->value));   // il peut aussi réserver
        $this->assertMatchesRegularExpression('/^AG[A-Z0-9]{6}$/', $agent->code_agent);
        Notification::assertSentTo($agent, AgentDesigne::class);

        $this->actingAs($this->proprio)->get('/pro/agents')->assertOk()->assertSee('Awa Dossou')->assertSee('10 % de la location');
        $this->actingAs($agent)->get('/agent')->assertOk()
            ->assertSee('Studio Lagune')->assertSee('/logements/studio-lagune?agent='.$agent->code_agent, false);
        // L'agent n'accueille pas les clients : pas d'espace pro ni de scanner
        $this->actingAs($agent)->get('/pro/scanner')->assertForbidden();
    }

    public function test_un_client_venu_par_le_lien_de_l_agent_lui_rapporte_sa_commission(): void
    {
        $agent = $this->agent();

        // Le lien ouvert est retenu (même avant la connexion), puis utilisé à la réservation sur le site
        $this->get('/logements/studio-lagune?agent='.$agent->code_agent)->assertOk()->assertCookie(AgentsMarketing::COOKIE);
        $this->actingAs($this->client)
            ->withCookie(AgentsMarketing::COOKIE, json_encode([(string) $this->logement->id => $agent->code_agent]))
            ->post('/reserver/studio-lagune', self::DEMANDE)->assertRedirect();
        $web = Reservation::where('client_id', $this->client->id)->latest('id')->firstOrFail();
        $this->assertSame($agent->id, $web->agent_id);
        $this->assertSame(1050, $web->montant_marketing);

        // Séjour démarré : la part de l'agent est retirée de la location du propriétaire
        $r = $this->sejourScanne($agent->code_agent);
        $this->assertSame($agent->id, $r->agent_id);
        $this->assertSame(10500 - 1050, (int) $this->proprio->mouvementsSolde()->where('reservation_id', $r->id)->value('montant'));
        $this->assertSame(1050, $agent->solde());
        $this->assertSame(TypeMouvement::CreditMarketing, $agent->mouvementsSolde()->first()->type);

        // Un second crédit du même séjour ne verse rien de plus
        app(\App\Services\Caisse::class)->crediterSejour($r);
        $this->assertSame(1050, $agent->fresh()->solde());
    }

    public function test_sans_agent_la_part_marketing_revient_a_vivi(): void
    {
        $r = $this->sejourScanne(null);

        $this->assertNull($r->agent_id);
        $this->assertSame(1050, $r->montant_marketing);
        $this->assertSame(9450, $this->proprio->solde());   // la part n'est versée à personne : elle reste à VIVI
        $this->assertSame(0, \App\Models\MouvementSolde::where('type', TypeMouvement::CreditMarketing)->count());
    }

    public function test_un_code_invalide_ou_d_un_autre_logement_ne_compte_pas(): void
    {
        $autreLogement = $this->logement('Villa Akpakpa', TypeMarketing::Pourcentage, 10);
        $agentAilleurs = $this->agent('Koffi Agbo', $autreLogement);

        $this->assertNull($this->sejourScanne('AGZZZZZZ')->agent_id);
        $this->assertNull($this->sejourScanne($agentAilleurs->code_agent)->agent_id);
        $this->assertSame(0, $agentAilleurs->solde());
    }

    public function test_l_agent_qui_reserve_un_logement_qu_il_promeut_paie_le_prix_moins_sa_part(): void
    {
        $agent = $this->agent();
        Sanctum::actingAs($agent);

        // 10 500 de location + 5 % de commission (525) − 1 050 de part marketing
        $tunnel = $this->postJson('/api/v1/logements/studio-lagune/reserver', self::DEMANDE + ['agent' => $agent->code_agent])->assertCreated();
        $tunnel->assertJsonPath('data.devis.remise_agent', 1050)->assertJsonPath('data.devis.location', 10500);
        $reference = $tunnel->json('data.reference');
        $r = Reservation::where('reference', $reference)->firstOrFail();
        $this->assertNull($r->agent_id);
        $this->assertSame(1050, $r->remise_agent);
        $total = (int) $r->montant_commission + 10500 - 1050;
        $this->assertSame($total, (int) $r->montant_total);

        // Le paiement demande seulement la différence
        $paiement = $this->postJson("/api/v1/reservations/{$reference}/payer", ['nom' => 'Awa Dossou', 'telephone' => '01 97 00 00 11', 'cgv' => true, 'services' => []])
            ->assertOk()->json('data.paiement.id');
        $this->assertSame($total, (int) \App\Models\Paiement::find($paiement)->montant);

        // Au séjour : l'agent ne touche rien, le propriétaire reçoit la location moins la part, VIVI rien de plus
        $r->refresh()->update(['statut' => StatutReservation::Confirmee, 'debut' => now()->addMinutes(30), 'fin' => now()->addHours(3)]);
        app(Arrivees::class)->demarrer($this->proprio, $r);
        $this->assertSame(0, $agent->fresh()->solde());
        $this->assertSame(9450, $this->proprio->solde());
        $this->assertSame(0, $r->fresh()->partMarketingVivi());

        // Sur un logement qu'il ne promeut pas, il paie le prix normal
        $autre = $this->logement('Villa Akpakpa', TypeMarketing::Pourcentage, 10);
        $this->postJson('/api/v1/logements/villa-akpakpa/reserver', self::DEMANDE)->assertCreated()->assertJsonPath('data.devis.remise_agent', 0);
        $this->assertSame(0, Reservation::where('logement_id', $autre->id)->value('remise_agent'));
    }

    public function test_les_revenus_vivi_comptent_la_part_marketing_sans_agent(): void
    {
        $agent = $this->agent();
        $this->sejourScanne(null);                 // 1 050 pour VIVI
        $this->sejourScanne($agent->code_agent);   // 1 050 pour l'agent

        $chiffres = \App\Filament\Widgets\ChiffresDuJour::revenusVivi(now()->startOfMonth(), now()->endOfDay());
        $this->assertSame(1050, $chiffres['marketing']);
        $this->assertSame(1050, $chiffres['agents']);
        $this->assertSame($chiffres['commission'] + 1050, $chiffres['total']);

        $admin = tap(User::factory()->create(['actif' => true]), fn (User $u) => $u->assignRole(Role::Administrateur->value));
        $this->actingAs($admin, 'web');
        $this->followingRedirects()->get('/admin')->assertOk()->assertSee('Revenus VIVI du mois')->assertSee('Part marketing du mois');
        $csv = $this->get('/admin-exports/agents?du=2026-11-01&au=2026-11-30')->assertOk()->streamedContent();
        $this->assertStringContainsString('Awa Dossou', $csv);
    }

    public function test_la_documentation_technique_est_lisible_par_l_admin_et_le_technicien(): void
    {
        $admin = tap(User::factory()->create(['actif' => true]), fn (User $u) => $u->assignRole(Role::Administrateur->value));
        $this->actingAs($admin)->get('/admin/documentation')->assertOk()->assertSee('API de l', false)->assertSee('/agent/retraits');
        $this->get('/admin/documentation?doc=plateforme')->assertOk()->assertSee('Agents marketing');

        $technicien = tap(User::factory()->create(['actif' => true]), fn (User $u) => $u->assignRole(Role::Technicien->value));
        $this->actingAs($technicien)->get('/admin/documentation')->assertOk();
        $this->get('/admin/retraits')->assertForbidden();   // pas les finances

        $caissiere = tap(User::factory()->create(['actif' => true]), fn (User $u) => $u->assignRole(Role::Caissiere->value));
        $this->actingAs($caissiere)->get('/admin/documentation')->assertForbidden();
    }

    public function test_montant_fixe_jamais_plus_que_la_location(): void
    {
        $this->logement->update(['marketing_type' => TypeMarketing::Fixe, 'marketing_valeur' => 2000]);
        $this->assertSame(2000, $this->logement->fresh()->partMarketing(10500));
        $this->assertSame(1500, $this->logement->fresh()->partMarketing(1500));
        $this->assertSame('2 000 FCFA par réservation', str_replace("\u{202F}", ' ', str_replace("\u{00A0}", ' ', $this->logement->fresh()->libelleMarketing())));

        $agent = $this->agent();
        $this->assertSame(2000, $this->sejourScanne($agent->code_agent)->montant_marketing);
        $this->assertSame(2000, $agent->solde());
    }

    public function test_le_proprietaire_regle_la_part_et_voit_le_classement_dans_l_application(): void
    {
        $awa = $this->agent('Awa Dossou');
        $koffi = $this->agent('Koffi Agbo');
        $this->sejourScanne($koffi->code_agent);

        Sanctum::actingAs($this->proprio);
        $this->getJson('/api/v1/pro/agents?periode=tout')->assertOk()
            ->assertJsonPath('data.classement.0.agent.nom', 'Koffi Agbo')
            ->assertJsonPath('data.classement.0.rang', 1)
            ->assertJsonPath('data.classement.0.sejours', 1)
            ->assertJsonPath('data.classement.0.gains', 1050)
            ->assertJsonPath('data.classement.1.agent.nom', 'Awa Dossou')
            ->assertJsonPath('data.logements.0.part.libelle', '10 % de la location');

        $this->postJson('/api/v1/pro/agents', ['nom' => 'Paul Zinsou', 'identifiant' => '01 97 00 00 55', 'logements' => [$this->logement->id]])
            ->assertCreated()->assertJsonPath('data.nom', 'Paul Zinsou');
        $this->postJson('/api/v1/pro/agents', ['nom' => 'Moi', 'identifiant' => $this->proprio->email, 'logements' => [$this->logement->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('identifiant');

        $this->putJson("/api/v1/pro/agents/{$awa->id}", ['logements' => []])->assertOk();
        $this->assertFalse($awa->fresh()->hasRole(Role::AgentMarketing->value));

        // Fiche du logement : part marketing renvoyée et réglable
        $this->getJson("/api/v1/pro/logements/{$this->logement->id}")->assertOk()
            ->assertJsonPath('data.marketing_type', 'pourcentage')->assertJsonPath('data.marketing_valeur', 10);
    }

    public function test_la_part_en_pourcentage_est_plafonnee_a_la_validation(): void
    {
        $regles = (new \App\Http\Requests\LogementRequest)->merge(['marketing_type' => 'pourcentage'])->rules();
        $v = validator(['marketing_type' => 'pourcentage', 'marketing_valeur' => 60], ['marketing_type' => $regles['marketing_type'], 'marketing_valeur' => $regles['marketing_valeur']]);
        $this->assertTrue($v->fails());
        $v = validator(['marketing_type' => null, 'marketing_valeur' => null], ['marketing_type' => $regles['marketing_type'], 'marketing_valeur' => $regles['marketing_valeur']]);
        $this->assertFalse($v->fails());
    }

    public function test_l_agent_voit_ses_liens_et_retire_ses_commissions_dans_l_application(): void
    {
        $agent = $this->agent();
        $this->sejourScanne($agent->code_agent);

        Sanctum::actingAs($agent);
        $this->getJson('/api/v1/moi')->assertOk()->assertJsonPath('data.espace', 'agent')->assertJsonPath('data.est_agent', true);
        $lien = $this->getJson('/api/v1/agent')->assertOk()
            ->assertJsonPath('data.solde', 1050)
            ->assertJsonPath('data.sejours', 1)
            ->assertJsonPath('data.logements.0.titre', 'Studio Lagune')
            ->assertJsonPath('data.logements.0.part.libelle', '10 % de la location')
            ->assertJsonPath('data.logements.0.gains', 1050)
            ->json('data.logements.0.lien');
        $this->assertStringEndsWith('/logements/studio-lagune?agent='.$agent->code_agent, $lien);

        // Sans compte de retrait, la demande est refusée ; une fois le numéro indiqué, il est recopié dans la demande
        $this->postJson('/api/v1/agent/retraits', ['montant' => 1000])->assertUnprocessable()->assertJsonValidationErrors('compte');
        $this->putJson('/api/v1/compte-retrait', ['moyen' => 'Moov Money', 'numero' => '01 96 00 00 11', 'titulaire' => 'Agent Test'])
            ->assertOk()->assertJsonPath('data.configure', true)->assertJsonPath('data.numero', '0196000011');
        $this->postJson('/api/v1/agent/retraits', ['montant' => 1000])->assertCreated()->assertJsonPath('data.montant', 1000)
            ->assertJsonPath('data.compte.moyen', 'Moov Money')->assertJsonPath('data.compte.numero', '0196000011');
        $this->assertSame(50, $agent->fresh()->solde());
        $this->getJson('/api/v1/pro/tableau-de-bord')->assertForbidden();
    }
}
