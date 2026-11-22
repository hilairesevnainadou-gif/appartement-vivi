<?php

namespace App\Filament\Resources\Reservations;

use App\Enums\Role;
use App\Filament\Concerns\AccesParRole;
use App\Filament\Resources\Reservations\Pages\ListReservations;
use App\Filament\Resources\Reservations\Pages\ViewReservation;
use App\Filament\Resources\Reservations\Schemas\ReservationInfolist;
use App\Filament\Resources\Reservations\Tables\ReservationsTable;
use App\Models\Reservation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Réservations : consultation par l'équipe VIVI.
 * Elles sont créées par les clients sur le site ; annulation et déplacement
 * par l'administrateur arrivent aux lots 6 et 7.
 */
class ReservationResource extends Resource
{
    use AccesParRole;

    protected static ?string $model = Reservation::class;

    protected static array $rolesLecture = [Role::Administrateur, Role::Caissiere];

    /** Écran de consultation : ces actions ne sont proposées à personne. */
    protected static array $actionsInterdites = ['create', 'update', 'delete', 'deleteAny'];

    protected static ?string $modelLabel = 'réservation';

    protected static ?string $pluralModelLabel = 'Réservations';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'reference';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Réservations';

    protected static ?int $navigationSort = 1;

    public static function infolist(Schema $schema): Schema
    {
        return ReservationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReservationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReservations::route('/'),
            'view' => ViewReservation::route('/{record}'),
        ];
    }
}
