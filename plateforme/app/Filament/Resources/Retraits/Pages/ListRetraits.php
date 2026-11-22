<?php

namespace App\Filament\Resources\Retraits\Pages;

use App\Filament\Resources\Retraits\RetraitResource;
use Filament\Resources\Pages\ListRecords;

class ListRetraits extends ListRecords
{
    protected static string $resource = RetraitResource::class;

    public function getSubheading(): ?string
    {
        return 'Le montant d’une demande est retenu sur le solde du propriétaire dès qu’il la fait. Validez-la une fois le propriétaire payé, ou refusez-la pour recréditer son solde.';
    }
}
