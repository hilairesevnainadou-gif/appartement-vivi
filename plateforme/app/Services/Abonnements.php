<?php

namespace App\Services;

use App\Enums\StatutAbonnement;
use App\Enums\StatutPaiement;
use App\Models\Abonnement;
use App\Models\FormuleAbonnement;
use App\Models\Paiement;
use App\Models\User;
use App\Paiement\EtatTransaction;
use App\Support\Parametres;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Abonnement mensuel des propriétaires, payé par la même passerelle que les réservations.
 *
 *  - Même formule que l'abonnement en cours : renouvellement, la nouvelle période suit la période en cours.
 *  - Autre formule : elle commence dès le paiement et remplace l'abonnement en cours (sans prorata).
 *  - Une formule trop petite pour les logements déjà publiés ou en vérification est refusée.
 */
class Abonnements
{
    /* ---------- Promotions (Administration › Paramètres) ---------- */

    /** Le propriétaire n'a jamais eu d'essai ni d'abonnement payé. */
    public function estNouveau(User $proprietaire): bool
    {
        // Un abonnement déjà actif ou expiré compte aussi (abonnement créé par l'équipe, sans paiement en ligne)
        return ! Abonnement::where('proprietaire_id', $proprietaire->getKey())
            ->where(fn ($q) => $q->where('essai', true)
                ->orWhereIn('statut', [StatutAbonnement::Actif, StatutAbonnement::Expire])
                ->orWhereHas('paiements', fn ($p) => $p->where('statut', StatutPaiement::Reussi)))
            ->exists();
    }

    public function aDejaPaye(User $proprietaire): bool
    {
        return Abonnement::where('proprietaire_id', $proprietaire->getKey())
            ->where(fn ($q) => $q->whereHas('paiements', fn ($p) => $p->where('statut', StatutPaiement::Reussi))
                ->orWhere(fn ($q) => $q->where('essai', false)->whereIn('statut', [StatutAbonnement::Actif, StatutAbonnement::Expire])))
            ->exists();
    }

    /** Essai gratuit possible pour ce propriétaire (et cette formule, si l'administrateur en a fixé une). */
    public function essaiPossible(User $proprietaire, ?FormuleAbonnement $formule = null): bool
    {
        if (! Parametres::get('essai_actif') || (int) Parametres::get('essai_jours') < 1 || ! $this->estNouveau($proprietaire)
            || app(ModeleEconomique::class)->surCommission($proprietaire)) {   // sur commission : pas de formule, donc pas d'essai
            return false;
        }
        $imposee = (int) Parametres::get('essai_formule_id');

        return ! $formule || $imposee === 0 || $imposee === $formule->getKey();
    }

    /** Promotion en cours aujourd'hui : ['pourcentage' => 20, 'libelle' => …, 'fin' => '2026-12-31', 'nouveaux' => true], sinon null. */
    public function promotionEnCours(): ?array
    {
        $pourcentage = max(0, min(100, (int) Parametres::get('promo_pourcentage')));
        $debut = Parametres::get('promo_debut');
        $fin = Parametres::get('promo_fin');
        if (! Parametres::get('promo_actif') || $pourcentage === 0
            || ($debut !== '' && today()->lt($debut)) || ($fin !== '' && today()->gt($fin))) {
            return null;
        }

        return ['pourcentage' => $pourcentage, 'libelle' => Parametres::get('promo_libelle') ?: 'Promotion',
            'fin' => $fin ?: null, 'nouveaux' => (bool) Parametres::get('promo_nouveaux_seulement')];
    }

    /**
     * Prix d'une formule pour ce propriétaire, promotion déduite.
     *
     * @return array{prix: int, remise: int, net: int, libelle: ?string}
     */
    public function tarif(?User $proprietaire, FormuleAbonnement $formule): array
    {
        $prix = (int) $formule->prix_mensuel;
        $promo = $this->promotionEnCours();
        // « Nouveaux seulement » : premier abonnement payé (un essai gratuit ne compte pas)
        $eligible = $promo && (! $promo['nouveaux'] || ! $proprietaire || ! $this->aDejaPaye($proprietaire));
        $remise = $eligible ? (int) round($prix * $promo['pourcentage'] / 100) : 0;

        return ['prix' => $prix, 'remise' => $remise, 'net' => $prix - $remise, 'libelle' => $remise > 0 ? $promo['libelle'] : null];
    }

    /**
     * Essai gratuit : la formule démarre aujourd'hui, sans paiement, pour la durée fixée par l'administrateur.
     * Une seule fois par propriétaire ; il paie ensuite pour continuer (renouvellement à la fin de l'essai).
     *
     * @throws ReservationImpossible
     */
    public function commencerEssai(User $proprietaire, FormuleAbonnement $formule): Abonnement
    {
        abort_unless($formule->actif, 404);
        $this->refuserSiCommission($proprietaire);

        return DB::transaction(function () use ($proprietaire, $formule) {
            User::whereKey($proprietaire->getKey())->lockForUpdate()->first();
            if (! $this->essaiPossible($proprietaire, $formule)) {
                throw new ReservationImpossible('L’essai gratuit n’est pas disponible pour ce compte ou pour cette formule.');
            }
            $proprietaire->abonnements()->where('statut', StatutAbonnement::EnAttente)->update(['statut' => StatutAbonnement::Resilie]);
            $jours = (int) Parametres::get('essai_jours');

            return $proprietaire->abonnements()->create([
                'formule_abonnement_id' => $formule->getKey(),
                'debut' => today(), 'fin' => today()->addDays($jours - 1),
                'prix_paye' => 0, 'essai' => true, 'libelle_promo' => 'Essai gratuit de '.$jours.' jours',
                'statut' => StatutAbonnement::Actif,
            ]);
        });
    }

    /** @throws ReservationImpossible message pour le propriétaire */
    public function souscrire(User $proprietaire, FormuleAbonnement $formule, string $passerelle): Paiement
    {
        abort_unless($formule->actif, 404);
        $this->refuserSiCommission($proprietaire);
        // Quotas séparés : logements d'un côté, salles de l'autre
        $q = app(GestionLogements::class)->quota($proprietaire);
        if ($q['utilises'] > $formule->logements_max) {
            throw new ReservationImpossible("La formule {$formule->nom} inclut {$formule->logements_max} logement(s) : vous en avez {$q['utilises']} publiés ou en vérification.");
        }
        if ($q['salles']['utilises'] > $formule->salles_max) {
            throw new ReservationImpossible("La formule {$formule->nom} inclut {$formule->salles_max} salle(s) : vous en avez {$q['salles']['utilises']} publiées ou en vérification.");
        }

        return DB::transaction(function () use ($proprietaire, $formule, $passerelle) {
            // Une souscription non payée précédente est abandonnée
            $proprietaire->abonnements()->where('statut', StatutAbonnement::EnAttente)->each(function (Abonnement $a) {
                $a->paiements()->where('statut', StatutPaiement::EnAttente)->update(['statut' => StatutPaiement::Echoue]);
                $a->update(['statut' => StatutAbonnement::Resilie]);
            });

            $tarif = $this->tarif($proprietaire, $formule);
            $abonnement = $proprietaire->abonnements()->create([
                'formule_abonnement_id' => $formule->getKey(),
                'debut' => today(), 'fin' => today()->addMonthNoOverflow()->subDay(),   // dates définitives fixées au paiement
                'prix_paye' => $tarif['net'],
                'montant_remise' => $tarif['remise'],
                'libelle_promo' => $tarif['libelle'],
                'statut' => StatutAbonnement::EnAttente,
            ]);

            return $abonnement->paiements()->create([
                'payeur_id' => $proprietaire->getKey(),
                'passerelle' => $passerelle,
                'montant' => $tarif['net'],
                'statut' => StatutPaiement::EnAttente,
            ]);
        });
    }

    /** Propriétaire sur commission : aucune formule à payer (il paierait deux fois). @throws ReservationImpossible */
    private function refuserSiCommission(User $proprietaire): void
    {
        if (app(ModeleEconomique::class)->surCommission($proprietaire)) {
            throw new ReservationImpossible('Vous travaillez sur commission : aucun abonnement n’est nécessaire pour publier vos logements.');
        }
    }

    /** Paiement reçu : l'abonnement démarre (ou prolonge l'abonnement en cours). Sans effet si c'est déjà fait. */
    public function activer(Paiement $paiement, EtatTransaction $etat): Abonnement
    {
        return DB::transaction(function () use ($paiement, $etat) {
            $paiement = Paiement::whereKey($paiement->getKey())->lockForUpdate()->firstOrFail();
            /** @var Abonnement $abonnement */
            $abonnement = Abonnement::whereKey($paiement->payable_id)->lockForUpdate()->firstOrFail();
            if ($paiement->statut === StatutPaiement::Reussi) {
                return $abonnement;
            }
            $paiement->update([
                'statut' => StatutPaiement::Reussi, 'paye_le' => now(), 'moyen' => $etat->moyen ?? $paiement->moyen,
                'donnees' => array_merge($paiement->donnees ?? [], ['transaction' => $etat->donnees]),
            ]);
            if ($abonnement->statut !== StatutAbonnement::EnAttente) {
                return $abonnement;
            }

            $enCours = Abonnement::where('proprietaire_id', $abonnement->proprietaire_id)->whereKeyNot($abonnement->getKey())
                ->where('statut', StatutAbonnement::Actif)->whereDate('fin', '>=', today())->orderByDesc('fin')->first();

            if ($enCours && $enCours->formule_abonnement_id === $abonnement->formule_abonnement_id) {
                $debut = CarbonImmutable::instance($enCours->fin)->addDay();     // renouvellement
            } else {
                $debut = CarbonImmutable::today();
                Abonnement::where('proprietaire_id', $abonnement->proprietaire_id)->whereKeyNot($abonnement->getKey())
                    ->where('statut', StatutAbonnement::Actif)->update(['statut' => StatutAbonnement::Resilie]);
            }
            $abonnement->update([
                'debut' => $debut, 'fin' => $debut->addMonthNoOverflow()->subDay(), 'statut' => StatutAbonnement::Actif,
            ]);
            DB::afterCommit(fn () => \App\Support\Equipe::prevenir('abonnements.voir',
                'Abonnement payé : '.\App\Support\Montant::fcfa((int) $paiement->montant),
                $abonnement->proprietaire->name.' · formule '.$abonnement->formule->nom.', jusqu’au '.$abonnement->fin->translatedFormat('j F Y').'.',
                \App\Filament\Resources\Abonnements\AbonnementResource::getUrl('index', panel: 'admin'),
                'heroicon-o-identification', 'success', 'Voir les abonnements'));

            return $abonnement;
        });
    }

    public function echouer(Paiement $paiement, EtatTransaction $etat): void
    {
        $paiement->refresh();
        if ($paiement->statut === StatutPaiement::EnAttente) {
            $paiement->update(['statut' => StatutPaiement::Echoue, 'donnees' => array_merge($paiement->donnees ?? [], ['transaction' => $etat->donnees])]);
        }
    }

    /** Abonnements arrivés à échéance : marqués expirés (tâche quotidienne). */
    public function expirer(): int
    {
        return Abonnement::where('statut', StatutAbonnement::Actif)->whereDate('fin', '<', today())->update(['statut' => StatutAbonnement::Expire]);
    }
}
