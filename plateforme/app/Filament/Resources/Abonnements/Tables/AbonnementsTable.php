<?php

namespace App\Filament\Resources\Abonnements\Tables;

use App\Enums\StatutAbonnement;
use App\Filament\Support\Colonnes;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AbonnementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['proprietaire', 'formule']))
            ->defaultSort('fin', 'desc')
            ->columns([
                Colonnes::personne('proprietaire', 'Propriétaire'),
                TextColumn::make('formule.nom')->label('Formule')->badge()->color('gray'),
                TextColumn::make('debut')->label('Début')->date('d/m/Y')->sortable()->visibleFrom('md'),
                TextColumn::make('fin')->label('Fin')->date('d/m/Y')->sortable()
                    ->color(fn ($record) => $record->fin->isPast() ? 'danger' : ($record->fin->diffInDays(today(), true) <= 7 ? 'warning' : null)),
                Colonnes::montant('prix_paye', 'Prix payé')->visibleFrom('md'),
                TextColumn::make('statut')->label('Statut')->badge(),
            ])
            ->filters([
                SelectFilter::make('statut')->label('Statut')->options(StatutAbonnement::class),
                SelectFilter::make('formule_abonnement_id')->label('Formule')->relationship('formule', 'nom'),
            ])
            ->recordActions([EditAction::make()]);
    }
}
