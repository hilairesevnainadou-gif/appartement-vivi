<?php

namespace App\Filament\Resources\Publicites\RelationManagers;

use App\Filament\Resources\Publicites\PubliciteResource;
use App\Models\PublicitePaiement;
use App\Support\Montant;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Règlements reçus pour une publicité. Une erreur se corrige en supprimant le règlement (gardé dans le journal d'activité). */
class PaiementsRelationManager extends RelationManager
{
    protected static string $relationship = 'paiements';

    protected static ?string $title = 'Règlements de l’annonceur';

    protected static ?string $modelLabel = 'règlement';

    protected static ?string $pluralModelLabel = 'règlements';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('caissier'))
            ->defaultSort('paye_le', 'desc')
            ->columns([
                TextColumn::make('paye_le')->label('Reçu le')->date('d/m/Y'),
                TextColumn::make('montant')->label('Montant')->formatStateUsing(fn (int $state) => Montant::fcfa($state)),
                TextColumn::make('mode')->label('Mode'),
                TextColumn::make('reference')->label('Référence')->placeholder('—'),
                TextColumn::make('caissier.name')->label('Encaissé par')->placeholder('—')->color('gray'),
            ])
            ->recordActions([
                DeleteAction::make()->label('Annuler')
                    ->modalHeading(fn (PublicitePaiement $record) => 'Annuler le règlement de '.Montant::fcfa($record->montant).' ?')
                    ->visible(fn () => PubliciteResource::canCreate()),
            ])
            ->emptyStateHeading('Aucun règlement')
            ->emptyStateDescription('Les règlements s’ajoutent avec le bouton « Encaisser ».');
    }
}
