<?php

namespace App\Filament\Resources\Alertes\Pages;

use App\Filament\Resources\Alertes\AlerteResource;
use Filament\Resources\Pages\ListRecords;

class ListAlertes extends ListRecords
{
    protected static string $resource = AlerteResource::class;

    public function getSubheading(): ?string
    {
        return 'Pannes et tentatives d’attaque détectées automatiquement. Les administrateurs sont prévenus (cloche, email, SMS si critique).';
    }
}
