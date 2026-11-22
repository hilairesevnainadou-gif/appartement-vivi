<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    public function getSubheading(): ?string
    {
        return 'Ce que chaque rôle de l’équipe peut voir et faire dans l’administration. L’administrateur a toujours tout.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nouveau rôle')];
    }
}
