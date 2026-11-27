<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\StatutAbonnement;
use App\Models\User;
use App\Support\Parametres;
use Illuminate\Database\Eloquent\Builder;

/**
 * Comment VIVI est rémunérée par un propriétaire :
 *
 *  - « abonnement » : il paie une formule mensuelle (quotas de la formule), ses retraits sont versés en entier ;
 *  - « commission » : aucune formule à payer, ses logements sont visibles sans abonnement (quotas des Paramètres),
 *    et VIVI garde un pourcentage de chaque retrait.
 *
 * Règle générale dans Administration › Paramètres (modele_defaut, commission_retrait_taux). L'administrateur peut la
 * personnaliser sur la fiche d'un propriétaire : modèle imposé (users.modele_economique) et/ou taux (users.commission_retrait_taux).
 * La commission ne concerne que les propriétaires : un agent marketing reçoit toujours ses retraits en entier.
 */
class ModeleEconomique
{
    public const ABONNEMENT = 'abonnement';

    public const COMMISSION = 'commission';

    /** Quotas à partir desquels il n'y a plus de limite (« illimité » dans les Paramètres). */
    public const ILLIMITE_LOGEMENTS = 999;

    public const ILLIMITE_GESTIONNAIRES = 99;

    /** @return array<string, string> */
    public static function options(): array
    {
        return [self::ABONNEMENT => 'Abonnement (formules mensuelles)', self::COMMISSION => 'Commission sur chaque retrait'];
    }

    public static function defaut(): string
    {
        return Parametres::get('modele_defaut') === self::COMMISSION ? self::COMMISSION : self::ABONNEMENT;
    }

    /** Les formules d'abonnement sont-elles proposées par défaut (page Propriétaires, inscription) ? */
    public static function formulesProposees(): bool
    {
        return self::defaut() === self::ABONNEMENT;
    }

    public function modele(User $proprietaire): string
    {
        if (in_array($proprietaire->modele_economique, [self::ABONNEMENT, self::COMMISSION], true)) {
            return $proprietaire->modele_economique;
        }

        // Règle générale passée à la commission : celui qui a déjà payé une formule la garde jusqu'à son terme
        // (sinon il paierait deux fois : l'abonnement et la commission sur ses retraits).
        if (self::defaut() === self::COMMISSION && $proprietaire->abonnementActif()->exists()) {
            return self::ABONNEMENT;
        }

        return self::defaut();
    }

    public function surCommission(User $proprietaire): bool
    {
        return $this->modele($proprietaire) === self::COMMISSION;
    }

    /** Le modèle ou le taux a été réglé spécialement pour ce propriétaire. */
    public function personnalise(User $proprietaire): bool
    {
        return $proprietaire->modele_economique !== null || $proprietaire->commission_retrait_taux !== null;
    }

    /** Taux retenu sur ses retraits, en % (0 sur abonnement ou pour un agent marketing). */
    public function taux(User $beneficiaire): float
    {
        if (! $beneficiaire->hasRole(Role::Proprietaire->value) || ! $this->surCommission($beneficiaire)) {
            return 0.0;
        }
        $taux = $beneficiaire->commission_retrait_taux ?? Parametres::get('commission_retrait_taux');

        return max(0.0, min(100.0, (float) $taux));
    }

    /**
     * Décompte d'un retrait : ce qui sort du solde, la part de VIVI, ce que le bénéficiaire reçoit.
     *
     * @return array{montant: int, taux: float, commission: int, net: int}
     */
    public function decompte(User $beneficiaire, int $montant): array
    {
        $taux = $this->taux($beneficiaire);
        $commission = (int) round($montant * $taux / 100);

        return ['montant' => $montant, 'taux' => $taux, 'commission' => $commission, 'net' => $montant - $commission];
    }

    /**
     * Quotas d'un propriétaire sur commission (null sur abonnement : ce sont ceux de la formule).
     *
     * @return array{logements: int, salles: int, gestionnaires: int}|null
     */
    public function quotas(User $proprietaire): ?array
    {
        if (! $this->surCommission($proprietaire)) {
            return null;
        }

        return [
            'logements' => max(0, (int) Parametres::get('commission_logements_max')),
            'salles' => max(0, (int) Parametres::get('commission_salles_max')),
            'gestionnaires' => max(0, (int) Parametres::get('commission_gestionnaires_max')),
        ];
    }

    /** Ce propriétaire peut-il être visible des clients : abonnement en cours, ou commission sur retrait. */
    public function peutPublier(User $proprietaire): bool
    {
        return $this->surCommission($proprietaire) || $proprietaire->abonnementActif()->exists();
    }

    /** Condition SQL équivalente à peutPublier(), sur une requête de User (scope Logement::visibles). */
    public static function filtrerPublication(Builder $users): Builder
    {
        return $users->where(function (Builder $q) {
            $q->where('modele_economique', self::COMMISSION)
                ->when(self::defaut() === self::COMMISSION, fn (Builder $q) => $q->orWhereNull('modele_economique'))
                ->orWhereHas('abonnements', fn (Builder $a) => $a->where('statut', StatutAbonnement::Actif)
                    ->whereDate('debut', '<=', today())->whereDate('fin', '>=', today()));
        });
    }
}
