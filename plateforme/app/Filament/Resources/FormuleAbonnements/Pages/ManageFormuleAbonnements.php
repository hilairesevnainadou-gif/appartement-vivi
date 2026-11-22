<?php

namespace App\Filament\Resources\FormuleAbonnements\Pages;

use App\Filament\Resources\FormuleAbonnements\FormuleAbonnementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFormuleAbonnements extends ManageRecords
{
    protected static string $resource = FormuleAbonnementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
