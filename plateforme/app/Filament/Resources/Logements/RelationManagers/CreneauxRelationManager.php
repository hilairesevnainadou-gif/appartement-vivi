<?php

namespace App\Filament\Resources\Logements\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Créneaux de demi-journée, nommés librement (une fin avant le début = le lendemain). */
class CreneauxRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'creneaux';

    protected static ?string $title = 'Créneaux de demi-journée';

    protected static ?string $modelLabel = 'créneau';

    protected static ?string $pluralModelLabel = 'créneaux';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextInput::make('nom')->label('Nom')->placeholder('Matin, Après-midi, Soirée…')->required()->maxLength(60),
                TimePicker::make('heure_debut')->label('Début')->seconds(false)->required(),
                TimePicker::make('heure_fin')->label('Fin')->seconds(false)->required()
                    ->helperText('Une fin avant le début signifie le lendemain (ex. 20:00 → 01:00).'),
                TextInput::make('ordre')->label('Ordre d’affichage')->integer()->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nom')
            ->defaultSort('ordre')
            ->reorderable('ordre')
            ->columns([
                TextColumn::make('nom')->label('Créneau'),
                TextColumn::make('heure_debut')->label('Début')->time('H:i'),
                TextColumn::make('heure_fin')->label('Fin')->time('H:i')
                    ->description(fn ($record) => $record->finitLendemain() ? 'le lendemain' : null),
            ])
            ->headerActions([CreateAction::make()->label('Ajouter un créneau')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
