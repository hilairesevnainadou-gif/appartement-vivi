<?php

namespace App\Filament\Resources\Abonnements;

use App\Enums\Role;
use App\Filament\Concerns\AccesParRole;
use App\Filament\Resources\Abonnements\Pages\CreateAbonnement;
use App\Filament\Resources\Abonnements\Pages\EditAbonnement;
use App\Filament\Resources\Abonnements\Pages\ListAbonnements;
use App\Filament\Resources\Abonnements\Schemas\AbonnementForm;
use App\Filament\Resources\Abonnements\Tables\AbonnementsTable;
use App\Models\Abonnement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** Abonnements des propriétaires (souscrits en ligne, ou saisis ici par l'équipe). */
class AbonnementResource extends Resource
{
    use AccesParRole;

    protected static ?string $model = Abonnement::class;

    protected static array $rolesLecture = [Role::Administrateur, Role::Caissiere];

    protected static array $rolesEcriture = [Role::Administrateur, Role::Caissiere];

    protected static ?string $modelLabel = 'abonnement';

    protected static ?string $pluralModelLabel = 'Abonnements';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Abonnements';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return AbonnementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AbonnementsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAbonnements::route('/'),
            'create' => CreateAbonnement::route('/create'),
            'edit' => EditAbonnement::route('/{record}/edit'),
        ];
    }
}
