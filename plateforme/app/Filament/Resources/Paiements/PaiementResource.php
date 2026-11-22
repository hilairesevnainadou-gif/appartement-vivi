<?php

namespace App\Filament\Resources\Paiements;

use App\Enums\Role;
use App\Enums\StatutPaiement;
use App\Filament\Concerns\AccesParRole;
use App\Filament\Resources\Paiements\Pages\ManagePaiements;
use App\Filament\Support\Colonnes;
use App\Models\Abonnement;
use App\Models\Paiement;
use App\Models\Reservation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Paiements reçus par FedaPay. Lecture seule : ils sont enregistrés par la passerelle. */
class PaiementResource extends Resource
{
    use AccesParRole;

    protected static ?string $model = Paiement::class;

    protected static array $rolesLecture = [Role::Administrateur, Role::Caissiere];

    /** Écran de consultation : ces actions ne sont proposées à personne. */
    protected static array $actionsInterdites = ['create', 'update', 'delete', 'deleteAny'];

    protected static ?string $modelLabel = 'paiement';

    protected static ?string $pluralModelLabel = 'Paiements reçus';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationLabel = 'Paiements reçus';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Finances';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['payable', 'payeur']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Colonnes::dateHeure('created_at', 'Date'),
                TextColumn::make('objet')->label('Pour')
                    ->state(fn (Paiement $record) => match (true) {
                        $record->payable instanceof Reservation => 'Réservation '.$record->payable->reference,
                        $record->payable instanceof Abonnement => 'Abonnement',
                        default => '—',
                    }),
                Colonnes::personne('payeur', 'Payé par')->placeholder('—'),
                TextColumn::make('moyen')->label('Moyen')->badge()->color('gray')->placeholder('—')->visibleFrom('md'),
                Colonnes::montant('montant', 'Montant'),
                Colonnes::reference('reference_passerelle', 'Réf. FedaPay')->placeholder('—')->visibleFrom('lg'),
                TextColumn::make('statut')->label('Statut')->badge(),
            ])
            ->filters([
                SelectFilter::make('statut')->label('Statut')->options(StatutPaiement::class),
                SelectFilter::make('payable_type')->label('Pour')->options(['reservation' => 'Réservations', 'abonnement' => 'Abonnements']),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePaiements::route('/'),
        ];
    }
}
