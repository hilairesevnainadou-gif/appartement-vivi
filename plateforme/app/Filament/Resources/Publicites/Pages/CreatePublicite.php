<?php

namespace App\Filament\Resources\Publicites\Pages;

use App\Filament\Resources\Publicites\PubliciteResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePublicite extends CreateRecord
{
    protected static string $resource = PubliciteResource::class;

    /** Rappel : tant que la publicité est coupée dans les Paramètres, rien ne s'affiche nulle part. */
    public function getSubheading(): ?string
    {
        return \App\Support\Publicites::actives() ? null
            : '⚠ La publicité est désactivée : activez-la dans Administration › Paramètres, sinon cette publicité ne s’affiche ni dans l’application ni sur le site.';
    }

    protected static ?string $title = 'Ajouter une publicité';

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
