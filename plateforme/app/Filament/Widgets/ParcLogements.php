<?php

namespace App\Filament\Widgets;

use App\Enums\ModeGestion;
use App\Enums\StatutLogement;
use App\Enums\TypeLogement;
use App\Filament\Resources\Logements\LogementResource;
use App\Filament\Resources\Salles\SalleResource;
use App\Models\Logement;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Parc de VIVI : logements et salles (fêtes, conférences, plein air, espaces complets) publiés,
 * et fiches dont la gestion est confiée à VIVI (avec ou sans gérant désigné).
 */
class ParcLogements extends StatsOverviewWidget
{
    protected static ?int $sort = 6;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Parc';

    public static function canView(): bool
    {
        return LogementResource::canViewAny();
    }

    protected function getColumns(): int|array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }

    protected function getStats(): array
    {
        $salles = array_map(fn (TypeLogement $t) => $t->value, TypeLogement::salles());
        $publies = Logement::where('statut', StatutLogement::Publie);
        $nbSalles = (clone $publies)->whereIn('type', $salles)->count();
        $nbLogements = (clone $publies)->whereNotIn('type', $salles)->count();
        $capaciteSalles = (int) (clone $publies)->whereIn('type', $salles)->sum('capacite');
        $parTypeSalle = (clone $publies)->whereIn('type', $salles)->selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type');

        $vivi = Logement::where('mode_gestion', ModeGestion::Vivi)->whereNot('statut', StatutLogement::Refuse);
        $viviSalles = (clone $vivi)->whereIn('type', $salles)->count();
        $viviLogements = (clone $vivi)->whereNotIn('type', $salles)->count();
        $sansGerantSalles = (clone $vivi)->whereNull('gerant_vivi_id')->whereIn('type', $salles)->count();
        $sansGerantLogements = (clone $vivi)->whereNull('gerant_vivi_id')->whereNotIn('type', $salles)->count();
        $sansGerant = $sansGerantSalles + $sansGerantLogements;
        // Logements et salles ont chacun leur liste dans l'administration
        $liste = fn (string $ressource, array $filtres) => $ressource::getUrl('index', ['filters' => $filtres]);
        $versSalles = fn (int $salles, int $logements) => $salles > 0 && $salles >= $logements ? SalleResource::class : LogementResource::class;

        return [
            Stat::make('Logements publiés', $nbLogements)
                ->description('Appartements, studios, chambres, résidences')
                ->icon(Heroicon::OutlinedHome)
                ->url($liste(LogementResource::class, ['statut' => ['value' => StatutLogement::Publie->value]])),
            Stat::make('Salles publiées', $nbSalles)
                ->description($nbSalles
                    ? collect($parTypeSalle)->map(fn ($n, $t) => $n.' '.mb_strtolower(TypeLogement::from($t)->court()))->implode(' · ').' · '.number_format($capaciteSalles, 0, ',', ' ').' places'
                    : 'Fêtes, conférences, réunions, plein air')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('warning')
                ->url($liste(SalleResource::class, ['statut' => ['value' => StatutLogement::Publie->value]])),
            Stat::make('Gérés par VIVI', $viviLogements + $viviSalles)
                ->description($viviLogements.' logement'.($viviLogements > 1 ? 's' : '').' · '.$viviSalles.' salle'.($viviSalles > 1 ? 's' : ''))
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('primary')
                ->url($liste($versSalles($viviSalles, $viviLogements), ['mode_gestion' => ['value' => ModeGestion::Vivi->value]])),
            Stat::make('Gérant VIVI à désigner', $sansGerant)
                ->description($sansGerant ? $sansGerantLogements.' logement'.($sansGerantLogements > 1 ? 's' : '').' · '.$sansGerantSalles.' salle'.($sansGerantSalles > 1 ? 's' : '') : 'Toutes les demandes ont un gérant')
                ->icon(Heroicon::OutlinedUserPlus)
                ->color($sansGerant ? 'danger' : 'success')
                ->url($liste($versSalles($sansGerantSalles, $sansGerantLogements), ['gerant_a_designer' => ['isActive' => true]])),
        ];
    }
}
