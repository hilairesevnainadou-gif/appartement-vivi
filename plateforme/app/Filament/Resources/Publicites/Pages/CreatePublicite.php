<?php

namespace App\Filament\Resources\Publicites\Pages;

use App\Filament\Resources\Publicites\PubliciteResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePublicite extends CreateRecord
{
    protected static string $resource = PubliciteResource::class;

    protected static ?string $title = 'Ajouter une publicité';

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
