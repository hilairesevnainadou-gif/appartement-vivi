<?php

namespace App\Filament\Resources\Publicites\Widgets;

use App\Models\Publicite;
use App\Models\PublicitePaiement;
use App\Models\PubliciteJour;
use App\Support\Montant;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Publicités : encaissé, reste à encaisser et diffusion (en haut de la liste). */
class ChiffresPublicite extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 3];
    }

    protected function getStats(): array
    {
        $encaisse = fn ($de, $a) => (int) PublicitePaiement::whereBetween('paye_le', [$de->toDateString(), $a->toDateString()])->sum('montant');
        $mois = $encaisse(now()->startOfMonth(), now()->endOfMonth());
        $dernier = $encaisse(now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth());
        $reste = max(0, (int) Publicite::sum('prix') - (int) PublicitePaiement::sum('montant'));
        $aEncaisser = Publicite::aEncaisser()->count();
        $jours = PubliciteJour::where('jour', '>=', now()->startOfMonth()->toDateString())->selectRaw('COALESCE(SUM(vues), 0) as vues, COALESCE(SUM(clics), 0) as clics')->first();

        return [
            Stat::make('Encaissé ce mois', Montant::fcfa($mois))->description('Mois dernier : '.Montant::fcfa($dernier))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)->color('success'),
            Stat::make('Reste à encaisser', Montant::fcfa($reste))->description($aEncaisser.' publicité'.($aEncaisser > 1 ? 's' : ''))
                ->descriptionIcon(Heroicon::OutlinedClock)->color($reste ? 'warning' : 'gray'),
            Stat::make('En ligne', Publicite::diffusees()->count())
                ->description(number_format((int) $jours->vues, 0, ',', ' ').' vues et '.number_format((int) $jours->clics, 0, ',', ' ').' clics ce mois')
                ->descriptionIcon(Heroicon::OutlinedDevicePhoneMobile)->color('primary'),
        ];
    }
}
