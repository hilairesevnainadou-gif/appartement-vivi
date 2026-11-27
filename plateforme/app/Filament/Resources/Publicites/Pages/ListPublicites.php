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
            ? 'Affichées dans l’application et sur le site web, avec la mention « Publicité ». Les annonceurs reçoivent leurs rapports automatiquement.'
            : 'La publicité est désactivée : activez-la dans Administration › Paramètres pour l’afficher dans l’application et sur le site.';
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
