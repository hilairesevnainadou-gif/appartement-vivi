<?php

namespace App\Filament\Resources\Journal\Pages;

use App\Filament\Resources\Journal\JournalResource;
use Filament\Resources\Pages\ListRecords;

class ListJournal extends ListRecords
{
    protected static string $resource = JournalResource::class;

    public function getSubheading(): ?string
    {
        return 'Qui a fait quoi, et quand : créations, modifications, suppressions, connexions et changements de droits. Conservé un an.';
    }
}
