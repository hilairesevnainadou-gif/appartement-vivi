<?php

namespace Tests\Feature;

use App\Enums\EmplacementPub;
use App\Enums\Role;
use App\Filament\Pages\Parametres as PageParametres;
use App\Filament\Resources\Publicites\Pages\CreatePublicite;
use App\Filament\Resources\Publicites\Pages\EditPublicite;
use App\Filament\Resources\Publicites\Pages\ListPublicites;
use App\Filament\Resources\Publicites\PubliciteResource;
use App\Filament\Resources\Publicites\RelationManagers\PaiementsRelationManager;
use App\Models\Publicite;
use App\Models\User;
use App\Support\Parametres;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Publicités de l'application : création, diffusion par espace, ville et période, vues et clics, AdMob ; villes du bandeau. */
class PublicitesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-22 09:00'));
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function compte(Role $role): User
    {
        return tap(User::factory()->create(['actif' => true]), fn (User $u) => $u->assignRole($role->value));
    }

    private function pub(array $valeurs = []): Publicite
    {
        return Publicite::create($valeurs + ['titre' => 'Traiteur Saveurs', 'image' => 'publicites/a.jpg', 'emplacement' => 'accueil_haut', 'actif' => true]);
    }

    private function espaces(string $ville = ''): array
    {
        return $this->getJson('/api/v1/publicites'.($ville ? '?ville='.$ville : ''))->assertOk()->json('data.emplacements');
    }

    public function test_l_administrateur_cree_et_supprime_une_publicite(): void
    {
        $this->actingAs($this->compte(Role::Administrateur));
        $formulaire = ['titre' => 'Traiteur Saveurs', 'annonceur' => 'Saveurs SARL', 'emplacement' => 'accueil_haut', 'lien' => 'https://saveurs.example',
            'image' => UploadedFile::fake()->image('pub.jpg', 1200, 400), 'actif' => true, 'ordre' => 0];

        Livewire::test(CreatePublicite::class)->fillForm(['lien' => 'http://saveurs.example'] + $formulaire)->call('create')->assertHasFormErrors(['lien']);
        Livewire::test(CreatePublicite::class)->fillForm($formulaire)->call('create')->assertHasNoFormErrors();
        $p = Publicite::sole();
        $this->assertSame(EmplacementPub::AccueilHaut, $p->emplacement);
        Storage::disk('public')->assertExists($p->image);
        $this->get('/admin/publicites')->assertOk()->assertSee('Saveurs SARL')->assertSee('En ligne');

        // L'ancienne image est effacée quand elle change, la dernière à la suppression
        $ancienne = $p->image;
        Livewire::test(EditPublicite::class, ['record' => $p->getKey()])
            ->set('data.image', [])->set('data.image', [UploadedFile::fake()->image('pub2.png', 1200, 400)])->call('save')->assertHasNoFormErrors();
        Storage::disk('public')->assertMissing($ancienne);
        $image = $p->fresh()->image;
        Storage::disk('public')->assertExists($image);
        $p->fresh()->delete();
        Storage::disk('public')->assertMissing($image);

        $this->actingAs($this->compte(Role::Caissiere))->get('/admin/publicites')->assertOk();
        $this->get('/admin/publicites/create')->assertForbidden();
        $this->actingAs($this->compte(Role::Technicien))->get('/admin/publicites')->assertForbidden();
    }

    public function test_prix_et_reglements_de_l_annonceur(): void
    {
        $p = $this->pub(['annonceur' => 'Saveurs SARL', 'annonceur_contact' => '01 97 00 00 00', 'prix' => 50000]);
        $gratuite = $this->pub(['titre' => 'Offre VIVI']);
        $this->assertSame(['a_payer', 'gratuite'], [$p->etatReglement(), $gratuite->etatReglement()]);

        // La caissière encaisse en deux fois, jamais plus que le reste
        $this->actingAs($this->compte(Role::Caissiere));
        $encaisser = fn (int $montant) => Livewire::test(ListPublicites::class)->callAction(TestAction::make('encaisser')->table($p), [
            'montant' => $montant, 'mode' => 'Espèces', 'reference' => 'R-1', 'paye_le' => '2026-11-22']);
        $encaisser(60000)->assertHasFormErrors(['montant']);
        $encaisser(20000)->assertHasNoFormErrors();
        $this->assertSame(['partiel', 30000], [$p->etatReglement(), $p->resteAPayer()]);
        $this->assertSame([$p->id], Publicite::aEncaisser()->pluck('id')->all());
        $this->get('/admin/publicites')->assertOk()->assertSee('Reste à encaisser')->assertSee('Partiel')->assertSee("30\u{00A0}000");
        $encaisser(30000)->assertHasNoFormErrors();
        $this->assertSame('payee', $p->etatReglement());
        Livewire::test(ListPublicites::class)->assertActionHidden(TestAction::make('encaisser')->table($p))->assertActionHidden(TestAction::make('encaisser')->table($gratuite));
        $csv = $this->get('/admin-exports/publicites?du=2026-11-01&au=2026-11-30')->assertOk()->streamedContent();
        $this->assertStringContainsString('50000;20000;0;Espèces;R-1;', $csv);

        // Prix jamais sous l'encaissé ; publicité encaissée non supprimable ; contact jamais envoyé à l'application
        $admin = $this->compte(Role::Administrateur);
        $this->actingAs($admin);
        Livewire::test(EditPublicite::class, ['record' => $p->getKey()])->assertActionHidden('delete')
            ->fillForm(['prix' => 40000])->call('save')->assertHasFormErrors(['prix']);
        $this->assertTrue(PubliciteResource::canDelete($gratuite));
        Parametres::enregistrer(['pub_active' => true]);
        $this->getJson('/api/v1/publicites')->assertOk()->assertSee('Saveurs SARL')->assertDontSee('01 97 00 00 00');

        // Un règlement saisi par erreur s'annule
        Livewire::test(PaiementsRelationManager::class, ['ownerRecord' => $p, 'pageClass' => EditPublicite::class])
            ->callAction(TestAction::make('delete')->table($p->paiements()->latest('id')->first()));
        $this->assertSame(30000, $p->resteAPayer());

        $this->actingAs($this->compte(Role::Client))->get('/admin-exports/publicites?du=2026-11-01&au=2026-11-30')->assertForbidden();
    }

    public function test_la_politique_de_confidentialite_est_publiee_par_l_administrateur(): void
    {
        $this->get('/aide/confidentialite')->assertNotFound();
        $this->get('/')->assertDontSee('/aide/confidentialite', false);
        $this->getJson('/api/v1/configuration')->assertJsonPath('data.pages.confidentialite', null);
        $admin = $this->compte(Role::Administrateur);
        $this->actingAs($admin)->get('/aide/confidentialite')->assertOk()->assertSee('Projet, visible de l’équipe VIVI seulement');

        Livewire::test(PageParametres::class)->fillForm(['confidentialite_publiee' => true])->call('enregistrer')->assertHasNoFormErrors();
        auth()->logout();
        $this->get('/aide/confidentialite')->assertOk()->assertDontSee('Projet, visible')->assertDontSee('AdMob')->assertSee('365 jours');
        $this->get('/')->assertSee('/aide/confidentialite', false);
        $this->getJson('/api/v1/configuration')->assertJsonPath('data.pages.confidentialite', route('aide.confidentialite'));

        Parametres::enregistrer(['pub_active' => true, 'admob_actif' => true]);
        $this->get('/aide/confidentialite')->assertSee('Google AdMob peut utiliser');
    }

    public function test_l_application_recoit_les_publicites_du_jour_par_espace_et_par_ville(): void
    {
        $p = $this->pub(['lien' => url('/logements/studio')]);
        $this->pub(['titre' => 'Pause', 'actif' => false]);
        $this->pub(['titre' => 'Finie', 'fin' => '2026-11-21']);
        $this->pub(['titre' => 'Bientôt', 'debut' => '2026-11-23']);
        $this->pub(['titre' => 'Dernier jour', 'fin' => '2026-11-22', 'ordre' => 5]);
        $this->pub(['titre' => 'Ouidah', 'villes' => ['Ouidah'], 'ordre' => 9]);

        // Publicité désactivée : rien, et aucun comptage
        $this->assertSame([], $this->espaces()['accueil_haut']['annonces']);
        $this->getJson('/api/v1/configuration')->assertJsonPath('data.publicite', false);
        $this->postJson("/api/v1/publicites/{$p->id}/vue")->assertNoContent();
        $this->assertSame(0, (int) $p->jours()->sum('vues'));

        Parametres::enregistrer(['pub_active' => true]);
        $this->getJson('/api/v1/configuration')->assertJsonPath('data.publicite', true);
        $espaces = $this->espaces();
        $this->assertSame(['Traiteur Saveurs', 'Dernier jour'], array_column($espaces['accueil_haut']['annonces'], 'titre'));
        $this->assertTrue($espaces['accueil_haut']['annonces'][0]['lien_interne']);
        $this->assertSame([], $espaces['recherche']['annonces']);
        $this->assertNull($espaces['accueil_haut']['admob']);
        $this->assertContains('Ouidah', array_column($this->espaces('ouidah')['accueil_haut']['annonces'], 'titre'));

        // Vues et clics, cumulés par jour
        foreach (['vue', 'vue', 'clic'] as $type) {
            $this->postJson("/api/v1/publicites/{$p->id}/{$type}")->assertNoContent();
        }
        $this->assertSame([2, 1], [(int) $p->jours()->sum('vues'), (int) $p->jours()->sum('clics')]);
        $this->travelTo(CarbonImmutable::parse('2026-11-23 09:00'));
        $this->postJson("/api/v1/publicites/{$p->id}/vue")->assertNoContent();
        $this->assertSame(2, $p->jours()->count());
        $this->postJson('/api/v1/publicites/999/vue')->assertNotFound();
    }

    public function test_reglages_admob(): void
    {
        $this->actingAs($this->compte(Role::Administrateur));
        $this->get('/app-ads.txt')->assertNotFound();
        $bloc = 'ca-app-pub-1234567890123456/1234567890';

        Livewire::test(PageParametres::class)->fillForm(['pub_active' => true, 'admob_actif' => true])
            ->fillForm(['pub_emplacements.fiche.admob' => 'ca-app-pub-1234567890123456~1234567890'])
            ->call('enregistrer')->assertHasFormErrors(['pub_emplacements.fiche.admob']);   // identifiant de l'application, pas d'un bloc
        Livewire::test(PageParametres::class)->fillForm(['pub_active' => true, 'admob_actif' => true])
            ->fillForm(['admob_editeur' => 'pub-1234567890123456', 'pub_emplacements.fiche.admob' => $bloc, 'pub_emplacements.recherche.source' => 'vivi'])
            ->call('enregistrer')->assertHasNoFormErrors();

        // Mode test : blocs de démonstration de Google, sauf où AdMob est exclu
        $espaces = $this->espaces();
        $this->assertSame(['format' => 'banniere_adaptative', 'bloc' => EmplacementPub::Fiche->blocTest()], $espaces['fiche']['admob']);
        $this->assertNull($espaces['recherche']['admob']);
        $this->get('/app-ads.txt')->assertOk()->assertSeeText('google.com, pub-1234567890123456, DIRECT, f08c47fec0942fa0');

        // En vrai : seulement les espaces qui ont un bloc
        Parametres::enregistrer(['admob_test' => false]);
        $espaces = $this->espaces();
        $this->assertSame($bloc, $espaces['fiche']['admob']['bloc']);
        $this->assertNull($espaces['accueil_haut']['admob']);

        // AdMob coupé puis enregistré : les identifiants des blocs sont gardés
        Livewire::test(PageParametres::class)->fillForm(['admob_actif' => false])->call('enregistrer')->assertHasNoFormErrors();
        $this->assertNull($this->espaces()['fiche']['admob']);
        $this->assertSame($bloc, Parametres::get('pub_emplacements')['fiche']['admob']);
        $this->assertSame('vivi', Parametres::get('pub_emplacements')['recherche']['source']);
    }

    public function test_le_bandeau_de_l_accueil_ne_cite_que_quelques_villes(): void
    {
        Parametres::enregistrer(['villes' => ['Cotonou', 'Ouidah']]);
        $this->get('/')->assertOk()->assertSee('Cotonou · Ouidah</span>', false)->assertDontSee('autres villes');

        Parametres::enregistrer(['villes' => ['Cotonou', 'Ouidah', 'Porto-Novo', 'Parakou']]);
        $this->get('/')->assertOk()->assertSee('Cotonou · Ouidah · Porto-Novo et 1 autre ville</span>', false);

        // Pied de page : huit villes au plus ; la liste « Où ? » garde toutes les villes
        Parametres::enregistrer(['villes' => array_map(fn ($i) => 'Ville '.$i.'x', range(1, 12))]);
        $this->get('/')->assertOk()->assertSee('et 9 autres villes')->assertSee('<option>Ville 12x</option>', false)
            ->assertSee('ville=Ville%208x', false)->assertDontSee('ville=Ville%209x', false)->assertSee('Toutes les villes</a>', false);
    }
}
