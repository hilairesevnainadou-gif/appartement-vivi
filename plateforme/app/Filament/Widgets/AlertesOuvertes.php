<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Alertes\AlerteResource;
use App\Models\AlerteSysteme;
use App\Services\Surveillance;
use Filament\Widgets\Widget;

/** Bandeau en tête du tableau de bord : alertes de surveillance à traiter (rien s'il n'y en a pas). */
class AlertesOuvertes extends Widget
{
    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.alertes-ouvertes';

    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        return AlerteResource::canViewAny();
    }

    protected function getViewData(): array
    {
        app(Surveillance::class)->verifierPlanificateur();   // tâches planifiées arrêtées ? (production)
        $ouvertes = AlerteSysteme::ouvertes()->where('niveau', '!=', AlerteSysteme::INFO)->latest('derniere_le')->get();

        return [
            'ouvertes' => $ouvertes->take(3),
            'total' => $ouvertes->count(),
            'critiques' => $ouvertes->where('niveau', AlerteSysteme::CRITIQUE)->count(),
            'url' => AlerteResource::getUrl('index'),
        ];
    }
}
