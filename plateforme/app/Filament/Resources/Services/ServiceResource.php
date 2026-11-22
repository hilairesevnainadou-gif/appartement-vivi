<?php

namespace App\Filament\Resources\Services;

use App\Enums\Role;
use App\Filament\Concerns\AccesParRole;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Filament\Resources\Services\Schemas\ServiceForm;
use App\Filament\Resources\Services\Tables\ServicesTable;
use App\Models\Service;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** Catalogue des services à la carte ; prix identiques dans tous les logements. */
class ServiceResource extends Resource
{
    use AccesParRole;

    protected static ?string $model = Service::class;

    protected static array $rolesLecture = [Role::Administrateur, Role::Caissiere];

    protected static ?string $modelLabel = 'service';

    protected static ?string $pluralModelLabel = 'Services à la carte';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationLabel = 'Services à la carte';

    protected static ?string $recordTitleAttribute = 'nom';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Logements';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ServiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServices::route('/'),
            'create' => CreateService::route('/create'),
            'edit' => EditService::route('/{record}/edit'),
        ];
    }
}
