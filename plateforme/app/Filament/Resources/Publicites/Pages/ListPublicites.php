<?php

namespace App\Filament\Resources\Publicites\Pages;

use App\Filament\Resources\Publicites\PubliciteResource;
use App\Filament\Resources\Publicites\Widgets\ChiffresPublicite;
use App\Support\Publicites;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPublicites extends ListRecords
{
    protected static string $resource = PubliciteResource::class;

    public function getSubheading(): ?string
    {
        return Publicites::actives()
            ? 'Affichées aux clients dans l’application, avec la mention « Publicité ».'
            : 'La publicité est désactivée : activez-la dans Administration › Paramètres pour l’afficher dans l’application.';
    }

    protected function getHeaderWidgets(): array
    {
        return [ChiffresPublicite::class];
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Ajouter une publicité')];
    }
}
