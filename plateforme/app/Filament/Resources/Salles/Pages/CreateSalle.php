<?php

namespace App\Filament\Resources\Salles\Pages;

use App\Filament\Resources\Logements\Pages\CreateLogement;
use App\Filament\Resources\Salles\SalleResource;

/** Même écran que pour les logements, dans la liste des salles. */
class CreateSalle extends CreateLogement
{
    protected static string $resource = SalleResource::class;
}
