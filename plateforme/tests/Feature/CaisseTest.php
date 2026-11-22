<?php

namespace Tests\Feature;

use App\Enums\StatutRetrait;
use App\Enums\TypeMouvement;
use App\Models\User;
use App\Services\Caisse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CaisseTest extends TestCase
{
    use RefreshDatabase;

    private Caisse $caisse;

    private User $proprietaire;

    private User $caissiere;

    protected function setUp(): void
    {
        parent::setUp();
        $this->caisse = new Caisse;
        $this->proprietaire = User::factory()->create();
        $this->caissiere = User::factory()->create();
        $this->proprietaire->mouvementsSolde()->create(['type' => TypeMouvement::CreditSejour, 'montant' => 50000, 'libelle' => 'Séjour']);
    }

    public function test_une_demande_de_retrait_debite_le_solde_tout_de_suite(): void
    {
        $retrait = $this->caisse->demanderRetrait($this->proprietaire, 30000);

        $this->assertSame(StatutRetrait::EnAttente, $retrait->statut);
        $this->assertSame(20000, $this->proprietaire->solde());
        $this->assertMatchesRegularExpression('/^RET-\d{6}$/', $retrait->reference);
    }

    public function test_on_ne_peut_pas_retirer_plus_que_le_solde(): void
    {
        $this->caisse->demanderRetrait($this->proprietaire, 30000);

        $this->expectException(RuntimeException::class);
        $this->caisse->demanderRetrait($this->proprietaire, 30000);
    }

    public function test_la_caissiere_valide_un_retrait_en_indiquant_le_mode_de_reglement(): void
    {
        $retrait = $this->caisse->demanderRetrait($this->proprietaire, 30000);

        $this->caisse->validerRetrait($retrait, $this->caissiere, 'MTN Mobile Money', 'MOMO-123');

        $retrait->refresh();
        $this->assertSame(StatutRetrait::Paye, $retrait->statut);
        $this->assertSame('MTN Mobile Money', $retrait->mode_reglement);
        $this->assertSame('MOMO-123', $retrait->reference_reglement);
        $this->assertSame($this->caissiere->id, $retrait->traite_par);
        $this->assertSame(20000, $this->proprietaire->solde());
    }

    public function test_un_retrait_refuse_est_recredite(): void
    {
        $retrait = $this->caisse->demanderRetrait($this->proprietaire, 30000);

        $this->caisse->refuserRetrait($retrait, $this->caissiere, 'Numéro Mobile Money invalide');

        $this->assertSame(StatutRetrait::Refuse, $retrait->refresh()->statut);
        $this->assertSame(50000, $this->proprietaire->solde());
    }

    public function test_un_retrait_ne_peut_etre_traite_qu_une_fois(): void
    {
        $retrait = $this->caisse->demanderRetrait($this->proprietaire, 30000);
        $this->caisse->validerRetrait($retrait, $this->caissiere, 'Espèces');

        $this->expectException(RuntimeException::class);
        $this->caisse->refuserRetrait($retrait, $this->caissiere, 'Trop tard');
    }
}
