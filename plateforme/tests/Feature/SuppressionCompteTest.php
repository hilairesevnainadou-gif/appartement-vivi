<?php

namespace Tests\Feature;

use App\Enums\Formule;
use App\Enums\Role;
use App\Enums\StatutAbonnement;
use App\Enums\StatutLogement;
use App\Enums\StatutReservation;
use App\Enums\TypeLogement;
use App\Enums\TypeMouvement;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Bonus;
use App\Models\CodeConnexion;
use App\Models\Logement;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\CodeDeConnexion;
use App\Notifications\CompteSupprime;
use App\Services\SuppressionCompte;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\AvecAbonnement;
use Tests\TestCase;

/** Suppression du compte par son titulaire : obstacles, code, effacement des données, site et API. */
class SuppressionCompteTest extends TestCase
{
    use AvecAbonnement, RefreshDatabase;

    private User $client;

    private User $proprio;

    private Logement $studio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00'));
        Notification::fake();
        Storage::fake('public');
        $this->client = $this->compte(Role::Client, ['name' => 'Mireille Adjovi', 'email' => 'mireille@exemple.com', 'telephone' => '0197000011']);
        $this->proprio = $this->compte(Role::Proprietaire, ['name' => 'Rodrigue Houngbo']);
        $this->abonner($this->proprio);
        $this->studio = Logement::create([
            'proprietaire_id' => $this->proprio->id, 'titre' => 'Studio Lagune', 'slug' => 'studio-lagune', 'type' => TypeLogement::Studio,
            'ville' => 'Cotonou', 'quartier' => 'Fidjrossè', 'adresse' => 'Rue 12', 'capacite' => 2, 'statut' => StatutLogement::Publie,
            'temps_preparation_minutes' => 60, 'latitude' => 6.36, 'longitude' => 2.45,
        ]);
        $this->studio->tarifs()->create(['formule' => Formule::Heure, 'prix' => 3500, 'heure_ouverture' => '08:00', 'heure_fermeture' => '22:00', 'duree_minimum_heures' => 2]);
    }

    private function compte(Role $role, array $champs = []): User
    {
        return tap(User::factory()->create($champs + ['actif' => true]), fn (User $u) => $u->assignRole($role->value));
    }

    private function reservation(StatutReservation $statut, array $champs = []): Reservation
    {
        return Reservation::create($champs + [
            'reference' => Reservation::nouvelleReference(), 'client_id' => $this->client->id, 'logement_id' => $this->studio->id,
            'formule' => Formule::Heure, 'debut' => '2026-11-14 10:00', 'fin' => '2026-11-14 12:00', 'voyageurs' => 1, 'statut' => $statut,
            'montant_location' => 7000, 'montant_services' => 0, 'taux_commission' => 10, 'montant_commission' => 700, 'montant_total' => 7700,
            'code_secours' => '123456', 'whatsapp' => '+33612345678',
        ]);
    }

    private function dernierCode(): string
    {
        $code = null;
        Notification::assertSentOnDemand(CodeDeConnexion::class, function (CodeDeConnexion $n) use (&$code) {
            $code = $n->code;

            return $n->motif === 'suppression';
        });

        return $code;
    }

    private function codes(User $u): array
    {
        return array_column(app(SuppressionCompte::class)->obstacles($u->fresh()), 'code');
    }

    public function test_le_client_supprime_son_compte_sur_le_site(): void
    {
        $r = $this->reservation(StatutReservation::Terminee, ['debut' => '2026-11-01 10:00', 'fin' => '2026-11-01 12:00']);
        $r->avis()->create(['client_id' => $this->client->id, 'logement_id' => $this->studio->id, 'note' => 5, 'commentaire' => 'Très bien', 'statut' => 'publie']);
        Bonus::create(['user_id' => $this->client->id, 'motif' => 'geste', 'libelle' => 'Geste', 'montant' => 2000, 'restant' => 2000, 'expire_le' => '2027-01-01']);
        $this->client->update(['name' => 'Mireille Adjovi-Houénou']);   // ancienne valeur dans le journal
        $this->client->createToken('Téléphone');

        $this->actingAs($this->client)->get('/mon-profil')->assertOk()->assertSee(route('profil.suppression'), false);
        $this->get('/mon-profil/suppression')->assertOk()->assertSee('mireille@exemple.com')->assertSee('est perdu')->assertSee('Recevoir un code');

        // Sans code demandé, puis avec un code faux, puis sans la case cochée : rien n'est supprimé
        $this->post('/mon-profil/suppression', ['code' => '123456', 'accord' => '1'])->assertSessionHasErrors('code');
        $this->post('/mon-profil/suppression/code')->assertRedirect(route('profil.suppression'));
        $this->get('/mon-profil/suppression')->assertSee('Code envoyé');
        $this->post('/mon-profil/suppression', ['code' => '000000', 'accord' => '1'])->assertSessionHasErrors('code');
        $this->post('/mon-profil/suppression', ['code' => $this->dernierCode()])->assertSessionHasErrors('accord');
        $this->assertNull($this->client->fresh()->supprime_le);

        $this->post('/mon-profil/suppression', ['code' => $this->dernierCode(), 'accord' => '1'])->assertRedirect(route('accueil'));
        $this->assertGuest();
        Notification::assertSentOnDemand(CompteSupprime::class);

        $u = $this->client->fresh();
        $this->assertSame([SuppressionCompte::NOM, null, null, false], [$u->name, $u->email, $u->telephone, $u->actif]);
        $this->assertNotNull($u->supprime_le);
        $this->assertSame([0, 0, 0], [$u->tokens()->count(), (int) $u->bonus()->sum('restant'), $u->roles()->count()]);
        $this->assertNull($r->fresh()->whatsapp);
        $this->assertSame(7700, (int) $r->fresh()->montant_total);   // pièce comptable gardée
        $this->assertSame(0, CodeConnexion::where('identifiant', 'mireille@exemple.com')->count());
        // Plus rien dans le journal d'activité
        $journal = DB::table('activity_log')->where('subject_type', 'user')->where('subject_id', $u->id)->get();
        $this->assertStringNotContainsString('Mireille', $journal->toJson());
        $this->assertStringNotContainsString('33612345678', DB::table('activity_log')->get()->toJson());
        $this->assertTrue($journal->contains('event', 'suppression_compte'));

        // Avis gardé, sans le nom ; l'email peut resservir
        $this->get('/logements/studio-lagune')->assertOk()->assertSee('Ancien client')->assertDontSee('Mireille');
        $this->post('/connexion', ['mode' => 'inscription', 'identifiant' => 'mireille@exemple.com', 'nom' => 'Mireille Adjovi', 'role' => 'client'])->assertSessionDoesntHaveErrors();

        // Masqué dans l'administration
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->compte(Role::Administrateur));
        Livewire::test(ListUsers::class)->assertCanSeeTableRecords([$this->proprio])->assertCanNotSeeTableRecords([$u]);
    }

    public function test_les_obstacles(): void
    {
        $this->assertSame([], $this->codes($this->client));

        $r = $this->reservation(StatutReservation::Confirmee);
        $this->assertSame(['reservation'], $this->codes($this->client));
        $this->assertSame(['accueil'], $this->codes($this->proprio));   // le propriétaire attend un client
        $this->actingAs($this->client)->get('/mon-profil/suppression')->assertOk()->assertSee('Pas encore possible')->assertDontSee('Recevoir un code');
        $this->post('/mon-profil/suppression/code')->assertSessionHasErrors('compte');

        $r->update(['statut' => StatutReservation::Annulee, 'montant_rembourse' => 7000]);
        $this->assertSame(['remboursement'], $this->codes($this->client));
        $r->update(['rembourse_le' => now()]);
        $this->assertSame([], $this->codes($this->client));

        $r2 = $this->reservation(StatutReservation::EnAttentePaiement, ['bloque_jusqua' => now()->addMinutes(10)]);
        $this->assertSame(['paiement'], $this->codes($this->client));
        $r2->update(['bloque_jusqua' => now()->subMinute()]);

        $this->proprio->mouvementsSolde()->create(['type' => TypeMouvement::CreditSejour, 'montant' => 900, 'libelle' => 'Séjour']);
        $this->assertSame([], $this->codes($this->proprio));   // sous le minimum de retrait
        $this->proprio->mouvementsSolde()->create(['type' => TypeMouvement::CreditSejour, 'montant' => 5000, 'libelle' => 'Séjour']);
        $this->assertSame(['solde'], $this->codes($this->proprio));
        app(\App\Services\Caisse::class)->demanderRetrait($this->proprio, 5900);
        $this->assertSame(['retrait'], $this->codes($this->proprio));

        // Équipe VIVI et gérant VIVI
        $this->assertSame(['equipe'], $this->codes($this->compte(Role::Caissiere)));
        $gerant = $this->compte(Role::Gestionnaire);
        $this->studio->update(['gerant_vivi_id' => $gerant->id]);
        $this->assertSame(['equipe'], $this->codes($gerant));
    }

    public function test_le_proprietaire_retire_ses_fiches_et_ses_acces(): void
    {
        $gestionnaire = $this->compte(Role::Gestionnaire);
        $gestionnaire->logementsDelegues()->attach($this->studio->id);
        $agent = $this->compte(Role::AgentMarketing);
        $agent->logementsAgent()->attach($this->studio->id);
        $chemin = 'logements/'.$this->studio->id.'/a.jpg';
        Storage::disk('public')->put($chemin, 'x');
        $this->studio->photos()->create(['chemin' => $chemin, 'ordre' => 0]);
        $this->get('/logements/studio-lagune')->assertOk();

        $this->actingAs($this->proprio);
        $this->get('/mon-profil/suppression')->assertOk()->assertSee('retirés du site')->assertSee('abonnement');
        $this->post('/mon-profil/suppression/code');
        $this->post('/mon-profil/suppression', ['code' => $this->dernierCode(), 'accord' => '1'])->assertRedirect(route('accueil'));

        $l = $this->studio->fresh();
        $this->assertSame([StatutLogement::Suspendu, false, null, null], [$l->statut, (bool) $l->en_ligne, $l->adresse, $l->latitude]);
        $this->get('/logements/studio-lagune')->assertNotFound();
        Storage::disk('public')->assertMissing($chemin);
        $this->assertSame([0, 0], [$gestionnaire->logementsDelegues()->count(), $agent->logementsAgent()->count()]);
        $this->assertFalse($gestionnaire->fresh()->hasRole(Role::Gestionnaire->value));
        $this->assertSame(0, $this->proprio->abonnements()->where('statut', StatutAbonnement::Actif)->count());
        $this->assertStringNotContainsString('Rue 12', DB::table('activity_log')->get()->toJson());
    }

    public function test_l_agent_perd_les_commissions_a_venir(): void
    {
        $agent = $this->compte(Role::AgentMarketing);
        $r = $this->reservation(StatutReservation::Confirmee, ['agent_id' => $agent->id, 'montant_marketing' => 700]);
        $passee = $this->reservation(StatutReservation::Terminee, ['agent_id' => $agent->id, 'montant_marketing' => 700, 'debut' => '2026-11-01 10:00', 'fin' => '2026-11-01 12:00']);

        $this->actingAs($agent)->get('/mon-profil/suppression')->assertSee('commissions');
        $this->post('/mon-profil/suppression/code');
        $this->post('/mon-profil/suppression', ['code' => $this->dernierCode(), 'accord' => '1'])->assertRedirect(route('accueil'));
        $this->assertSame([null, $agent->id], [$r->fresh()->agent_id, $passee->fresh()->agent_id]);
    }

    public function test_l_api(): void
    {
        $this->getJson('/api/v1/compte/suppression')->assertUnauthorized();
        $this->getJson('/api/v1/configuration')->assertJsonPath('data.pages.suppression_compte', route('aide.suppression'));
        $jeton = $this->client->createToken('Téléphone')->plainTextToken;
        $entetes = ['Authorization' => 'Bearer '.$jeton];

        $r = $this->reservation(StatutReservation::Confirmee);
        $this->getJson('/api/v1/compte/suppression', $entetes)->assertOk()->assertJsonPath('data.possible', false)->assertJsonPath('data.obstacles.0.code', 'reservation');
        $this->postJson('/api/v1/compte/suppression/code', [], $entetes)->assertUnprocessable()->assertJsonValidationErrors('compte');

        $r->update(['statut' => StatutReservation::Terminee]);
        $this->getJson('/api/v1/compte/suppression', $entetes)->assertOk()->assertJsonPath('data.possible', true)
            ->assertJsonPath('data.canal', 'mail')->assertJsonPath('data.destination', 'mireille@exemple.com');
        $this->postJson('/api/v1/compte/suppression/code', [], $entetes)->assertOk();
        $this->postJson('/api/v1/compte/suppression/code', [], $entetes)->assertUnprocessable()->assertJsonValidationErrors('compte');   // une minute
        $this->postJson('/api/v1/compte/suppression', ['code' => '000000'], $entetes)->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/v1/compte/suppression', ['code' => $this->dernierCode()], $entetes)->assertOk();

        $this->assertNotNull($this->client->fresh()->supprime_le);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/moi', $entetes)->assertUnauthorized();
    }

    public function test_la_page_publique(): void
    {
        $this->get('/aide/supprimer-mon-compte')->assertOk()->assertSee('Ce qui est effacé')->assertSee('suite=%2Fmon-profil%2Fsuppression', false);
        $this->get('/')->assertSee(route('aide.suppression'), false);
        $this->get('/mon-profil/suppression')->assertRedirect();
    }
}
