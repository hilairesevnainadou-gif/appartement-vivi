<?php

namespace App\Filament\Resources\Reservations\Tables;

use App\Enums\Formule;
use App\Enums\StatutReservation;
use App\Filament\Support\Colonnes;
use App\Models\Reservation;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['client', 'logement']))
            ->defaultSort('debut', 'desc')
            ->columns([
                Colonnes::reference(),
                TextColumn::make('logement.titre')->label('Logement')
                    ->description(fn (Reservation $record) => $record->logement?->quartier.', '.$record->logement?->ville)
                    ->searchable()
                    ->visibleFrom('lg'),
                Colonnes::personne('client', 'Client'),
                Colonnes::dateHeure('debut', 'Arrivée')
                    ->description(fn (Reservation $record) => $record->formule?->getLabel()),
                Colonnes::montant('montant_total', 'Total payé'),
                Colonnes::montant('montant_commission', 'Commission')->color('primary')->toggleable()->visibleFrom('2xl'),
                TextColumn::make('statut')->label('Statut')->badge()
                    ->description(fn (Reservation $record) => $record->estManuelle() ? 'Hors plateforme' : null),
            ])
            ->emptyStateHeading('Aucune réservation')
            ->emptyStateDescription('Les réservations payées apparaîtront ici.')
            ->filters([
                SelectFilter::make('statut')->label('Statut')->options(StatutReservation::class)->multiple(),
                SelectFilter::make('formule')->label('Formule')->options(Formule::class),
                SelectFilter::make('origine')->label('Origine')->options(['plateforme' => 'Réservée en ligne', 'manuelle' => 'Saisie par le propriétaire']),
                SelectFilter::make('logement_id')->label('Logement')->relationship('logement', 'titre')->searchable()->preload(),
                Filter::make('periode')
                    ->schema([
                        DatePicker::make('du')->label('Du')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('au')->label('Au')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['du'] ?? null, fn ($q, $d) => $q->whereDate('debut', '>=', $d))
                        ->when($data['au'] ?? null, fn ($q, $d) => $q->whereDate('debut', '<=', $d))),
            ])
            ->recordActions([ViewAction::make()]);
    }
}
