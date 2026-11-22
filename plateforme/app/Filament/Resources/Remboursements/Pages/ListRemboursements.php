<?php

namespace App\Filament\Resources\Remboursements\Pages;

use App\Filament\Resources\Remboursements\RemboursementResource;
use Filament\Resources\Pages\ListRecords;

class ListRemboursements extends ListRecords
{
    protected static string $resource = RemboursementResource::class;

    public function getSubheading(): ?string
    {
        return 'Montants dus aux clients après une annulation, commission VIVI déduite. Versez-les puis indiquez comment : le client est prévenu.';
    }
}
