<?php

namespace App\Filament\Resources\Salles;

use App\Filament\Resources\Logements\LogementResource;
use App\Filament\Resources\Salles\Pages\CreateSalle;
use App\Filament\Resources\Salles\Pages\EditSalle;
use App\Filament\Resources\Salles\Pages\ListSalles;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Salles et espaces événementiels (fêtes, conférences, réunions, plein air, espaces complets) : liste séparée des
 * logements, avec le même formulaire, les mêmes actions (valider, gérant VIVI…) et ses propres permissions
 * (salles.voir, salles.gerer).
 */
class SalleResource extends LogementResource
{
    protected static string $categorie = 'salle';

    protected static ?string $slug = 'salles';

    protected static ?string $modelLabel = 'salle';

    protected static ?string $pluralModelLabel = 'Salles et espaces';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 2;

    public static function getPages(): array
    {
        return [
            'index' => ListSalles::route('/'),
            'create' => CreateSalle::route('/create'),
            'edit' => EditSalle::route('/{record}/edit'),
        ];
    }
}
