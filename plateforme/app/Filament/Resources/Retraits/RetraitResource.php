<?php

namespace App\Filament\Resources\Retraits;

use App\Enums\Role;
use App\Enums\StatutRetrait;
use App\Filament\Concerns\AccesParRole;
use App\Filament\Resources\Retraits\Pages\ListRetraits;
use App\Filament\Resources\Retraits\Tables\RetraitsTable;
use App\Models\Retrait;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Demandes de retrait des propriétaires. La caissière ou l'administrateur
 * les valide (en indiquant le mode de règlement) ou les refuse.
 */
class RetraitResource extends Resource
{
    use AccesParRole;

    protected static ?string $model = Retrait::class;

    protected static array $rolesLecture = [Role::Administrateur, Role::Caissiere];

    protected static array $rolesEcriture = [Role::Administrateur, Role::Caissiere];

    /** Écran de consultation : ces actions ne sont proposées à personne. */
    protected static array $actionsInterdites = ['create', 'update', 'delete', 'deleteAny'];

    protected static ?string $modelLabel = 'retrait';

    protected static ?string $pluralModelLabel = 'Retraits';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationLabel = 'Retraits propriétaires';

    protected static ?string $recordTitleAttribute = 'reference';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Finances';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return RetraitsTable::configure($table);
    }

    /** Administrateur ou caissière : peut payer ou refuser un retrait. */
    public static function peutTraiter(): bool
    {
        return static::peutEcrire();
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Retrait::where('statut', StatutRetrait::EnAttente)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Retraits à valider';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRetraits::route('/'),
        ];
    }
}
