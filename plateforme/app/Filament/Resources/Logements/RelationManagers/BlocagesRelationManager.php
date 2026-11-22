<?php

namespace App\Filament\Resources\Logements\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Périodes pendant lesquelles le logement n'est pas réservable. */
class BlocagesRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'blocages';

    protected static ?string $title = 'Jours bloqués';

    protected static ?string $modelLabel = 'blocage';

    protected static ?string $pluralModelLabel = 'blocages';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                DatePicker::make('date_debut')->label('Du')->native(false)->displayFormat('d/m/Y')->required(),
                DatePicker::make('date_fin')->label('Au (inclus)')->native(false)->displayFormat('d/m/Y')->required()->afterOrEqual('date_debut'),
                TextInput::make('motif')->label('Motif')->columnSpanFull()->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('motif')
            ->defaultSort('date_debut', 'desc')
            ->columns([
                TextColumn::make('date_debut')->label('Du')->date('d/m/Y'),
                TextColumn::make('date_fin')->label('Au')->date('d/m/Y'),
                TextColumn::make('motif')->label('Motif')->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Bloquer des dates')
                    ->mutateDataUsing(fn (array $data) => [...$data, 'cree_par' => auth()->id()]),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
