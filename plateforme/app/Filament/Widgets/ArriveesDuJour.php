<?php

namespace App\Filament\Widgets;

use App\Enums\StatutReservation;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Filament\Support\Colonnes;
use App\Models\Reservation;
use Filament\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Séjours qui commencent aujourd'hui, dans l'ordre des arrivées. */
class ArriveesDuJour extends TableWidget
{
    protected static ?int $sort = 5;

    protected static bool $isLazy = false;

    protected static ?string $heading = 'Arrivées du jour';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return ReservationResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Reservation::query()->with(['client', 'logement'])
                ->whereDate('debut', today())
                ->whereIn('statut', [StatutReservation::Confirmee, StatutReservation::EnCours, StatutReservation::Terminee, StatutReservation::Consommee])
                ->orderBy('debut'))
            ->columns([
                TextColumn::make('debut')->label('Heure')->dateTime('H:i')->weight(FontWeight::Bold)->color('primary'),
                Colonnes::personne('client', 'Client')->searchable(false),
                TextColumn::make('logement.titre')->label('Logement')
                    ->description(fn (Reservation $record) => $record->logement?->quartier)
                    ->visibleFrom('md'),
                Colonnes::montant('montant_total', 'Total')->sortable(false)->visibleFrom('lg'),
                TextColumn::make('statut')->label('Statut')->badge(),
            ])
            ->recordUrl(fn (Reservation $record) => ReservationResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                Action::make('tout')->label('Toutes les réservations')->link()->url(ReservationResource::getUrl('index')),
            ])
            ->paginated([5])
            ->emptyStateHeading('Aucune arrivée aujourd’hui')
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays);
    }
}
