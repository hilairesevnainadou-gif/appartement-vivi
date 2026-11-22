<?php

namespace App\Filament\Widgets;

use App\Enums\Role;
use App\Enums\StatutLogement;
use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Enums\StatutRetrait;
use App\Filament\Resources\Logements\LogementResource;
use App\Filament\Resources\Paiements\PaiementResource;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Filament\Resources\Retraits\RetraitResource;
use App\Models\Logement;
use App\Models\Paiement;
use App\Models\Reservation;
use App\Models\Retrait;
use App\Support\Montant;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Les chiffres que l'équipe VIVI regarde en premier, en haut du tableau de bord. */
class ChiffresDuJour extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /** Réservations qui rapportent une commission (payées, non annulées). */
    private const PAYEES = [StatutReservation::Confirmee, StatutReservation::EnCours, StatutReservation::Terminee, StatutReservation::Consommee];

    /** Chiffres financiers : pour qui peut voir les réservations (pas pour un rôle technique). */
    public static function canView(): bool
    {
        return ReservationResource::canViewAny();
    }

    /** Revenus VIVI sur une période : commission, part marketing des réservations venues sans agent, gestion confiée. */
    public static function revenusVivi(\DateTimeInterface $de, \DateTimeInterface $a): array
    {
        $ligne = Reservation::whereIn('statut', self::PAYEES)->whereBetween('created_at', [$de, $a])
            ->selectRaw('COALESCE(SUM(montant_commission), 0) as commission, COALESCE(SUM('.Reservation::SQL_MARKETING_VIVI.'), 0) as marketing, COALESCE(SUM('.Reservation::SQL_MARKETING_AGENTS.'), 0) as agents, COALESCE(SUM(montant_gestion), 0) as gestion')
            ->first();

        return ['commission' => (int) $ligne->commission, 'marketing' => (int) $ligne->marketing, 'agents' => (int) $ligne->agents, 'gestion' => (int) $ligne->gestion,
            'total' => (int) $ligne->commission + (int) $ligne->marketing + (int) $ligne->gestion];
    }

    protected function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 5];
    }

    protected function getStats(): array
    {
        $aujourdhui = CarbonImmutable::today();

        // Arrivées : aujourd'hui, et les 7 derniers jours pour la courbe
        $arrivees = Reservation::whereDate('debut', $aujourdhui)
            ->whereIn('statut', [StatutReservation::Confirmee, StatutReservation::EnCours, StatutReservation::Terminee])->count();
        $surPlace = Reservation::whereDate('debut', $aujourdhui)->where('statut', StatutReservation::EnCours)->count();
        $courbeArrivees = $this->parJour(14, fn ($de, $a) => Reservation::whereIn('statut', self::PAYEES)->whereBetween('debut', [$de, $a])->count());

        // Revenus VIVI (commission + part marketing sans agent) : mois en cours contre même période du mois précédent
        $revenus = fn ($de, $a) => self::revenusVivi($de, $a)['total'];
        $detailMois = self::revenusVivi($aujourdhui->startOfMonth(), now());
        $ceMois = $detailMois['total'];
        $moisDernier = $revenus($aujourdhui->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow());
        $courbeCommissions = $this->parJour(14, $revenus);

        // Encaissements FedaPay du jour
        $encaisseJour = (int) Paiement::where('statut', StatutPaiement::Reussi)->whereDate('paye_le', $aujourdhui)->sum('montant');

        $retraits = Retrait::where('statut', StatutRetrait::EnAttente);
        $nbRetraits = (clone $retraits)->count();

        $stats = [
            Stat::make('Arrivées du jour', $arrivees)
                ->description($surPlace ? $surPlace.' déjà sur place' : 'Aucune sur place pour l’instant')
                ->descriptionIcon(Heroicon::OutlinedKey)
                ->chart($courbeArrivees)
                ->color('primary')
                ->url(ReservationResource::getUrl('index')),
            Stat::make('Revenus VIVI du mois', Montant::fcfa($ceMois))
                ->description($this->evolution($ceMois, $moisDernier))
                ->descriptionIcon($ceMois >= $moisDernier ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
                ->chart($courbeCommissions)
                ->color($ceMois >= $moisDernier ? 'success' : 'danger'),
            Stat::make('Part marketing du mois', Montant::fcfa($detailMois['marketing']))
                ->description('Revient à VIVI (sans agent) · '.Montant::fcfa($detailMois['agents']).' pour les agents · commission '.Montant::fcfa($detailMois['commission']).' · gestion '.Montant::fcfa($detailMois['gestion']))
                ->descriptionIcon(Heroicon::OutlinedMegaphone)
                ->color('primary'),
            Stat::make('Retraits à valider', $nbRetraits)
                ->description($nbRetraits ? Montant::fcfa((int) $retraits->sum('montant')).' à payer aux propriétaires' : 'Rien en attente')
                ->descriptionIcon($nbRetraits ? Heroicon::OutlinedClock : Heroicon::OutlinedCheckCircle)
                ->color($nbRetraits ? 'warning' : 'success')
                ->url(RetraitResource::getUrl('index')),
        ];

        // L'administrateur suit les logements à vérifier ; la caissière, les encaissements du jour
        if (auth()->user()->hasRole(Role::Administrateur->value)) {
            $aValider = Logement::where('statut', StatutLogement::EnValidation)->count();
            $stats[] = Stat::make('Logements à valider', $aValider)
                ->description($aValider ? 'En attente de vérification' : 'Rien en attente')
                ->descriptionIcon($aValider ? Heroicon::OutlinedHomeModern : Heroicon::OutlinedCheckCircle)
                ->color($aValider ? 'warning' : 'success')
                ->url(LogementResource::getUrl('index', ['filters' => ['statut' => ['value' => StatutLogement::EnValidation->value]]]));
        } else {
            $stats[] = Stat::make('Encaissé aujourd’hui', Montant::fcfa($encaisseJour))
                ->description('Paiements FedaPay réussis')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('primary')
                ->url(PaiementResource::getUrl('index'));
        }

        return $stats;
    }

    /**
     * Valeur par jour sur les N derniers jours (aujourd'hui compris), pour les mini-courbes.
     *
     * @return list<int>
     */
    private function parJour(int $jours, callable $valeur): array
    {
        return collect(range($jours - 1, 0))
            ->map(fn (int $n) => (int) $valeur(today()->subDays($n), today()->subDays($n)->endOfDay()))
            ->all();
    }

    private function evolution(int $valeur, int $avant): string
    {
        if ($avant === 0) {
            return $valeur ? 'Premier mois avec des revenus' : 'Pas encore de revenu ce mois-ci';
        }
        $ecart = round(($valeur - $avant) / $avant * 100);

        return ($ecart >= 0 ? '+' : '').$ecart.' % par rapport au mois dernier, à la même date';
    }
}
