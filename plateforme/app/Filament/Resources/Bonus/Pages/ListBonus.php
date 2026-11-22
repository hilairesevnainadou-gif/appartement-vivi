<?php

namespace App\Filament\Resources\Bonus\Pages;

use App\Filament\Resources\Bonus\BonusResource;
use Filament\Resources\Pages\ListRecords;

class ListBonus extends ListRecords
{
    protected static string $resource = BonusResource::class;

    public function getSubheading(): ?string
    {
        return 'Bonus de parrainage et de bienvenue, et gestes offerts par l’équipe. Ils se déduisent d’une réservation, à la charge de VIVI.';
    }
}
