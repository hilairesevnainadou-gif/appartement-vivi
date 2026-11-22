<?php

namespace App\Filament\Resources\Abonnements\Pages;

use App\Filament\Resources\Abonnements\AbonnementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAbonnements extends ListRecords
{
    protected static string $resource = AbonnementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
