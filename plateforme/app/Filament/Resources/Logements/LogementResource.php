<?php

namespace App\Filament\Resources\Logements;

use App\Enums\Role;
use App\Enums\StatutLogement;
use App\Enums\TypeLogement;
use App\Filament\Concerns\AccesParRole;
use App\Filament\Resources\Logements\Pages\CreateLogement;
use App\Filament\Resources\Logements\Pages\EditLogement;
use App\Filament\Resources\Logements\Pages\ListLogements;
use App\Filament\Resources\Logements\RelationManagers\BlocagesRelationManager;
use App\Filament\Resources\Logements\RelationManagers\CreneauxRelationManager;
use App\Filament\Resources\Logements\RelationManagers\PhotosRelationManager;
use App\Filament\Resources\Logements\RelationManagers\TarifsRelationManager;
use App\Filament\Resources\Logements\Schemas\LogementForm;
use App\Filament\Resources\Logements\Tables\LogementsTable;
use App\Models\Logement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Logements (appartements, studios, chambres, résidences). Les salles ont leur propre liste (SalleResource), avec le
 * même formulaire et les mêmes actions : seule la catégorie affichée change.
 */
class LogementResource extends Resource
{
    use AccesParRole;

    /** Catégorie affichée par cette liste : « logement » ou « salle » (App\Enums\TypeLogement::CATEGORIES). */
    protected static string $categorie = 'logement';

    protected static ?string $model = Logement::class;

    protected static array $rolesLecture = [Role::Administrateur, Role::Caissiere];

    protected static array $rolesEcriture = [Role::Administrateur];

    protected static ?string $modelLabel = 'logement';

    protected static ?string $pluralModelLabel = 'Logements';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'titre';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|UnitEnum|null $navigationGroup = 'Logements';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return LogementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LogementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TarifsRelationManager::class,
            CreneauxRelationManager::class,
            BlocagesRelationManager::class,
            PhotosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLogements::route('/'),
            'create' => CreateLogement::route('/create'),
            'edit' => EditLogement::route('/{record}/edit'),
        ];
    }

    /** Types de la catégorie de cette liste. @return list<string> */
    public static function types(): array
    {
        return array_map(fn (TypeLogement $t) => $t->value, TypeLogement::deCategorie(static::$categorie));
    }

    public static function categorie(): string
    {
        return static::$categorie;
    }

    /** Seulement les fiches de la catégorie : les salles ne sont pas mêlées aux logements. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('type', static::types());
    }

    /** Adresse de la fiche admin, dans la bonne liste (logements ou salles). */
    public static function urlFiche(Logement $logement, ?string $panel = null): string
    {
        $ressource = $logement->estSalle() ? \App\Filament\Resources\Salles\SalleResource::class : self::class;

        return $ressource::getUrl('edit', ['record' => $logement], panel: $panel);
    }

    /** Pastille dans le menu : fiches de la catégorie en attente de validation. */
    public static function getNavigationBadge(): ?string
    {
        $n = Logement::where('statut', StatutLogement::EnValidation)->whereIn('type', static::types())->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'En attente de validation';
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
