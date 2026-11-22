<?php

namespace App\Filament\Resources\Equipements;

use App\Filament\Concerns\AccesParRole;
use App\Filament\Resources\Equipements\Pages\ManageEquipements;
use App\Models\Equipement;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Liste des équipements cochés sur les fiches logement. */
class EquipementResource extends Resource
{
    use AccesParRole;

    protected static ?string $model = Equipement::class;

    protected static ?string $modelLabel = 'équipement';

    protected static ?string $pluralModelLabel = 'Équipements';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'nom';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWifi;

    protected static string|UnitEnum|null $navigationGroup = 'Logements';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextInput::make('nom')->label('Nom')->required()->unique(ignoreRecord: true)->maxLength(80),
                TextInput::make('icone')->label('Icône')->placeholder('bi-wifi'),
                TextInput::make('ordre')->label('Ordre')->integer()->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('logements'))
            ->defaultSort('ordre')
            ->reorderable('ordre')
            ->columns([
                TextColumn::make('nom')->label('Équipement')->searchable(),
                TextColumn::make('icone')->label('Icône')->fontFamily('mono')->color('gray'),
                TextColumn::make('logements_count')->label('Logements')->alignCenter(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEquipements::route('/'),
        ];
    }
}
