<?php

namespace App\Filament\Resources\Services\Tables;

use App\Filament\Resources\Services\ServiceResource;
use App\Support\Montant;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('logements'))
            ->defaultSort('ordre')
            ->reorderable('ordre')
            ->columns([
                TextColumn::make('nom')->label('Service')->description(fn ($record) => $record->description)->searchable()->wrap(),
                TextColumn::make('prix')->label('Prix')->formatStateUsing(fn ($state, $record) => Montant::fcfa($state).' '.$record->unite)->sortable(),
                TextColumn::make('logements_count')->label('Logements')->alignCenter(),
                ToggleColumn::make('actif')->label('Proposé')->disabled(fn () => ! ServiceResource::canCreate()),
            ])
            ->recordActions([EditAction::make()]);
    }
}
