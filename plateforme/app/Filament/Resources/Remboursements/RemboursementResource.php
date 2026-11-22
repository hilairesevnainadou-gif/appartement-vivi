<?php

namespace App\Filament\Resources\Remboursements;

use App\Enums\Role;
use App\Enums\StatutReservation;
use App\Filament\Concerns\AccesParRole;
use App\Filament\Resources\Remboursements\Pages\ListRemboursements;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Filament\Support\Colonnes;
use App\Models\Reservation;
use App\Services\Caisse;
use App\Support\Montant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;
use UnitEnum;

/**
 * Remboursements dus aux clients après une annulation (la commission VIVI n'est jamais remboursée).
 * La caissière ou l'administrateur verse le montant puis indique comment il a été versé.
 */
class RemboursementResource extends Resource
{
    use AccesParRole;

    /** Moyens de versement proposés à la caisse. */
    public const MODES = ['Remboursement FedaPay', 'MTN Mobile Money', 'Moov Money', 'Celtiis Cash', 'Virement bancaire', 'Espèces'];

    protected static ?string $model = Reservation::class;

    protected static array $rolesLecture = [Role::Administrateur, Role::Caissiere];

    protected static array $rolesEcriture = [Role::Administrateur, Role::Caissiere];

    protected static array $actionsInterdites = ['create', 'update', 'delete', 'deleteAny'];

    protected static ?string $slug = 'remboursements';

    protected static ?string $modelLabel = 'remboursement';

    protected static ?string $pluralModelLabel = 'Remboursements';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationLabel = 'Remboursements clients';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Finances';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['client', 'logement'])
            ->where('statut', StatutReservation::Annulee)->where('montant_rembourse', '>', 0);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('annulee_le')
            ->columns([
                Colonnes::reference()->url(fn (Reservation $r) => ReservationResource::getUrl('view', ['record' => $r])),
                Colonnes::personne('client', 'Client'),
                Colonnes::montant('montant_rembourse', 'À rembourser'),
                Colonnes::montant('montant_total', 'Payé')->color('gray')->visibleFrom('xl'),
                Colonnes::dateHeure('annulee_le', 'Annulée le')->visibleFrom('md'),
                TextColumn::make('rembourse_le')->label('Versé')
                    ->formatStateUsing(fn ($state, Reservation $r) => $state ? $r->mode_remboursement.' · '.$state->format('d/m/Y') : null)
                    ->placeholder('À verser')->badge()->color(fn ($state) => $state ? 'success' : 'warning'),
            ])
            ->filters([
                TernaryFilter::make('verse')->label('Remboursement')
                    ->placeholder('Tous')->trueLabel('Versés')->falseLabel('À verser')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('rembourse_le'),
                        false: fn (Builder $q) => $q->whereNull('rembourse_le'),
                    )
                    ->default(false),
            ])
            ->recordActions([
                Action::make('verser')
                    ->label('Verser')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Reservation $r) => ! $r->rembourse_le && static::peutEcrire())
                    ->modalHeading(fn (Reservation $r) => 'Rembourser '.Montant::fcfa($r->montant_rembourse).' à '.$r->client->name)
                    ->modalDescription('Versez le montant, puis indiquez comment. Le client est prévenu. Cette action est définitive.')
                    ->schema([
                        Select::make('mode')->label('Versé par')->options(array_combine(self::MODES, self::MODES))->required(),
                        TextInput::make('reference')->label('Référence de l’opération')->helperText('Numéro de transaction (facultatif pour les espèces)'),
                    ])
                    ->action(function (Reservation $record, array $data) {
                        try {
                            app(Caisse::class)->rembourser($record, auth()->user(), $data['mode'], $data['reference'] ?? null);
                            Notification::make()->title('Remboursement enregistré : le client est prévenu')->success()->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->emptyStateHeading('Aucun remboursement à verser')
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle);
    }

    public static function getNavigationBadge(): ?string
    {
        $n = static::getEloquentQuery()->whereNull('rembourse_le')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Remboursements à verser';
    }

    public static function getPages(): array
    {
        return ['index' => ListRemboursements::route('/')];
    }
}
