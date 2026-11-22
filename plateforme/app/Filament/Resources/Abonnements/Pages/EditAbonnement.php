<?php

namespace App\Filament\Resources\Abonnements\Pages;

use App\Filament\Resources\Abonnements\AbonnementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAbonnement extends EditRecord
{
    protected static string $resource = AbonnementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
