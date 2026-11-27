<?php

namespace App\Services;

use App\Enums\StatutRetrait;
use App\Enums\TypeMouvement;
use App\Models\MouvementSolde;
use App\Models\Reservation;
use App\Models\Retrait;
use App\Models\User;
use App\Notifications\CommissionAgent;
use App\Notifications\RemboursementVerse;
use App\Notifications\RetraitTraite;
use App\Support\Prevenir;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Solde des propriétaires et retraits.
 *
 * - Un séjour démarré (QR Code scanné) crédite le solde (lot 4/5).
 * - Une demande de retrait débite le solde immédiatement : le montant ne peut
 *   pas être demandé deux fois. Propriétaire sur commission (ModeleEconomique) : VIVI garde
 *   sa part du montant demandé, la caisse verse le net (retraits.montant_net).
 * - La caissière ou l'administrateur valide le retrait en indiquant le mode de
 *   règlement, ou le refuse : le montant est alors recrédité.
 */
class Caisse
{
    /**
     * Séjour démarré : la location revient au propriétaire, et les services à la carte s'ils lui sont
     * attribués (paramètre figé dans la réservation). La commission VIVI reste à VIVI. Un seul crédit par séjour.
     * La part marketing est retirée de la location : elle est créditée à l'agent dont le lien a amené le
     * client ; sans agent, elle reste à VIVI. Gestion confiée à VIVI : sa rémunération (contrat) est aussi retirée.
     */
    public function crediterSejour(Reservation $reservation): ?MouvementSolde
    {
        // Réservation hors plateforme : le propriétaire a encaissé lui-même, VIVI n'a rien reçu à lui reverser
        if ($reservation->estManuelle()) {
            return null;
        }
        $this->crediterAgent($reservation);

        $proprietaire = $reservation->logement->proprietaire;
        if ($proprietaire->mouvementsSolde()->where('reservation_id', $reservation->getKey())->where('type', TypeMouvement::CreditSejour)->exists()) {
            return null;
        }
        $montant = $reservation->creditProprietaire();

        return $proprietaire->mouvementsSolde()->create([
            'type' => TypeMouvement::CreditSejour,
            'montant' => $montant,
            'reservation_id' => $reservation->getKey(),
            'libelle' => ($reservation->logement->estSalle() ? 'Événement ' : 'Séjour ').$reservation->reference.' démarré'
                .((int) $reservation->montant_gestion > 0 ? ' · gestion VIVI : −'.\App\Support\Montant::fcfa($reservation->montant_gestion) : ''),
        ]);
    }

    /** Commission de l'agent marketing (une seule fois par séjour), sur le même solde que celui d'un propriétaire. */
    private function crediterAgent(Reservation $reservation): ?MouvementSolde
    {
        if (! $reservation->agent_id || (int) $reservation->montant_marketing <= 0) {
            return null;
        }
        $deja = MouvementSolde::where('proprietaire_id', $reservation->agent_id)->where('reservation_id', $reservation->getKey())
            ->where('type', TypeMouvement::CreditMarketing)->exists();
        if ($deja) {
            return null;
        }

        $mouvement = MouvementSolde::create([
            'proprietaire_id' => $reservation->agent_id,
            'type' => TypeMouvement::CreditMarketing,
            'montant' => (int) $reservation->montant_marketing,
            'reservation_id' => $reservation->getKey(),
            'libelle' => 'Commission séjour '.$reservation->reference.' · '.$reservation->logement->titre,
        ]);
        Prevenir::sansBloquer(fn () => User::find($reservation->agent_id)?->notify(new CommissionAgent($reservation, (int) $mouvement->montant)));

        return $mouvement;
    }

    /**
     * Remboursement versé au client après une annulation : la caissière ou l'administrateur indique comment
     * (remboursement FedaPay, Mobile Money, espèces…). Une seule fois par réservation.
     */
    public function rembourser(Reservation $reservation, User $agent, string $mode, ?string $reference = null): Reservation
    {
        $r = DB::transaction(function () use ($reservation, $agent, $mode, $reference) {
            $r = Reservation::lockForUpdate()->findOrFail($reservation->getKey());
            if ((int) $r->montant_rembourse <= 0) {
                throw new RuntimeException('Aucun remboursement n’est dû pour cette réservation.');
            }
            if ($r->rembourse_le) {
                throw new RuntimeException('Ce remboursement a déjà été versé le '.$r->rembourse_le->format('d/m/Y').'.');
            }
            $r->update(['rembourse_le' => now(), 'rembourse_par' => $agent->getKey(), 'mode_remboursement' => $mode, 'reference_remboursement' => $reference]);

            return $r;
        });

        try {
            $r->client->notify(new RemboursementVerse($r));
        } catch (\Throwable $e) {
            report($e);
        }
        // L'administration et la caisse voient qui a versé quoi (et que ce remboursement n'est plus à traiter)
        \App\Support\Equipe::prevenir('remboursements.gerer',
            'Remboursement versé : '.\App\Support\Montant::fcfa($r->montant_rembourse),
            'Réservation '.$r->reference.' : versé par '.$agent->name.' ('.$mode.($reference ? ', réf. '.$reference : '').').',
            \App\Filament\Resources\Remboursements\RemboursementResource::getUrl('index', panel: 'admin'),
            'heroicon-o-check-circle', 'success', 'Voir les remboursements');

        return $r;
    }

    public function demanderRetrait(User $proprietaire, int $montant): Retrait
    {
        if ($montant <= 0) {
            throw new InvalidArgumentException('Le montant du retrait doit être positif.');
        }

        return DB::transaction(function () use ($proprietaire, $montant) {
            // Verrou sur les mouvements du propriétaire : deux demandes simultanées ne peuvent pas dépasser le solde
            $solde = (int) $proprietaire->mouvementsSolde()->lockForUpdate()->sum('montant');
            if ($montant > $solde) {
                throw new RuntimeException('Solde insuffisant : '.$solde.' FCFA disponibles.');
            }

            // Propriétaire sur commission : la part de VIVI est figée à la demande (un changement de taux ne la modifie plus)
            $decompte = app(ModeleEconomique::class)->decompte($proprietaire, $montant);

            $retrait = Retrait::create([
                'reference' => $this->nouvelleReference(),
                'proprietaire_id' => $proprietaire->id,
                'montant' => $montant,
                'taux_commission' => $decompte['taux'],
                'montant_commission' => $decompte['commission'],
                'montant_net' => $decompte['net'],
                // Compte de retrait du demandeur, figé au moment de la demande
                'compte_moyen' => $proprietaire->retrait_moyen,
                'compte_numero' => $proprietaire->retrait_numero,
                'compte_titulaire' => $proprietaire->retrait_titulaire,
                'statut' => StatutRetrait::EnAttente,
            ]);

            $proprietaire->mouvementsSolde()->create([
                'type' => TypeMouvement::DebitRetrait,
                'montant' => -$montant,
                'retrait_id' => $retrait->id,
                'libelle' => 'Retrait '.$retrait->reference
                    .($decompte['commission'] > 0 ? ' · versé '.\App\Support\Montant::fcfa($decompte['net']).', commission VIVI '.\App\Support\Montant::fcfa($decompte['commission']) : ''),
            ]);

            DB::afterCommit(fn () => \App\Support\Equipe::prevenir('retraits.gerer',
                'Retrait demandé : '.\App\Support\Montant::fcfa($decompte['net']).' à verser',
                $proprietaire->name.' demande un retrait ('.$retrait->reference.')'
                    .($decompte['commission'] > 0 ? ' : '.\App\Support\Montant::fcfa($montant).' retenus sur son solde, dont '.\App\Support\Montant::fcfa($decompte['commission']).' de commission VIVI ('.self::taux($decompte['taux']).')' : '')
                    .'. À payer puis valider avec le mode de règlement.',
                \App\Filament\Resources\Retraits\RetraitResource::getUrl('index', panel: 'admin'),
                'heroicon-o-banknotes', 'warning', 'Voir les retraits'));

            return $retrait;
        });
    }

    public function validerRetrait(Retrait $retrait, User $agent, string $modeReglement, ?string $referenceReglement = null, ?string $note = null): Retrait
    {
        return DB::transaction(function () use ($retrait, $agent, $modeReglement, $referenceReglement, $note) {
            $retrait = Retrait::lockForUpdate()->findOrFail($retrait->id);
            $this->verifierEnAttente($retrait);

            $retrait->update([
                'statut' => StatutRetrait::Paye,
                'mode_reglement' => $modeReglement,
                'reference_reglement' => $referenceReglement,
                'note' => $note,
                'traite_par' => $agent->id,
                'traite_le' => now(),
            ]);
            $this->prevenirDuRetrait($retrait);

            return $retrait;
        });
    }

    /** Le bénéficiaire (propriétaire ou agent) apprend que son retrait est payé ou refusé. */
    private function prevenirDuRetrait(Retrait $retrait): void
    {
        Prevenir::sansBloquer(fn () => $retrait->loadMissing('proprietaire')->proprietaire?->notify(new RetraitTraite($retrait)));
    }

    public function refuserRetrait(Retrait $retrait, User $agent, string $motif): Retrait
    {
        return DB::transaction(function () use ($retrait, $agent, $motif) {
            $retrait = Retrait::lockForUpdate()->findOrFail($retrait->id);
            $this->verifierEnAttente($retrait);

            $retrait->update([
                'statut' => StatutRetrait::Refuse,
                'note' => $motif,
                'traite_par' => $agent->id,
                'traite_le' => now(),
            ]);

            // Le montant retenu à la demande revient sur le solde
            $retrait->proprietaire->mouvementsSolde()->create([
                'type' => TypeMouvement::Ajustement,
                'montant' => $retrait->montant,
                'retrait_id' => $retrait->id,
                'libelle' => 'Retrait '.$retrait->reference.' refusé : montant recrédité',
            ]);
            $this->prevenirDuRetrait($retrait);

            return $retrait;
        });
    }

    private function verifierEnAttente(Retrait $retrait): void
    {
        if ($retrait->statut !== StatutRetrait::EnAttente) {
            throw new RuntimeException('Ce retrait a déjà été traité.');
        }
    }

    /** 10.0 → « 10 % », 7.5 → « 7,5 % ». */
    public static function taux(float $taux): string
    {
        return rtrim(rtrim(number_format($taux, 2, ',', ''), '0'), ',')."\u{00A0}%";
    }

    private function nouvelleReference(): string
    {
        $suivant = (int) Retrait::max('id') + 1;

        return 'RET-'.str_pad((string) $suivant, 6, '0', STR_PAD_LEFT);
    }
}
