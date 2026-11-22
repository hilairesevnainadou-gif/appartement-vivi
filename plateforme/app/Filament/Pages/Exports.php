<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Http\Controllers\Admin\ExportsController;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Exports comptables : un fichier CSV par sujet et par période (administrateur et caissière). */
class Exports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static string|UnitEnum|null $navigationGroup = 'Finances';

    protected static ?string $navigationLabel = 'Exports comptables';

    protected static ?string $title = 'Exports comptables';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.exports';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('exports.telecharger');
    }

    public function getSubheading(): ?string
    {
        return 'Fichiers CSV (séparateur « ; ») qui s’ouvrent directement dans Excel. Montants en francs CFA.';
    }

    protected function getViewData(): array
    {
        return ['types' => ExportsController::TYPES];
    }
}
