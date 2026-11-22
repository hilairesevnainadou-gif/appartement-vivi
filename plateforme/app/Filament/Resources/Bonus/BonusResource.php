<?php

namespace App\Filament\Resources\Bonus;

use App\Enums\Role;
use App\Filament\Concerns\AccesParRole;
use App\Filament\Resources\Bonus\Pages\ListBonus;
use App\Filament\Support\Colonnes;
use App\Models\Bonus;
use App\Models\User;
use App\Services\Parrainage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Bonus des clients (parrainage, bienvenue, gestes de l'équipe) : suivi, et bonus offert à la main. */
class BonusResource extends Resource
{
    use AccesParRole;

    protected static ?string $model = Bonus::class;

    protected static array $rolesLecture = [Role::Administrateur, Role::Caissiere];

    protected static array $rolesEcriture = [Role::Administrateur];

    protected static array $actionsInterdites = ['create', 'update', 'delete', 'deleteAny'];

    protected static ?string $slug = 'bonus';

    protected static ?string $modelLabel = 'bonus';

    protected static ?string $pluralModelLabel = 'Bonus clients';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationLabel = 'Parrainage et bonus';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = 'Finances';

    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'filleul']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Colonnes::personne('user', 'Bénéficiaire'),
                TextColumn::make('motif')->label('Motif')->badge()
                    ->formatStateUsing(fn (string $state) => Bonus::libelleMotif($state))
                    ->color(fn (string $state) => match ($state) { Bonus::MOTIF_PARRAINAGE => 'success', Bonus::MOTIF_BIENVENUE => 'info', default => 'gray' })
                    ->description(fn (Bonus $b) => $b->libelle),
                Colonnes::montant('montant', 'Montant'),
                Colonnes::montant('restant', 'Reste')->color(fn (Bonus $b) => $b->restant > 0 ? null : 'gray'),
                TextColumn::make('expire_le')->label('Valable jusqu’au')->date('d/m/Y')
                    ->color(fn (Bonus $b) => $b->expire_le->lt(today()) ? 'danger' : null)->visibleFrom('md'),
                Colonnes::dateHeure('created_at', 'Reçu le')->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('motif')->label('Motif')->options([
                    Bonus::MOTIF_PARRAINAGE => 'Parrainage', Bonus::MOTIF_BIENVENUE => 'Bienvenue', Bonus::MOTIF_GESTE => 'Geste VIVI',
                ]),
                TernaryFilter::make('utilisable')->label('État')->placeholder('Tous')
                    ->trueLabel('Utilisables')->falseLabel('Utilisés ou expirés')
                    ->queries(
                        true: fn (Builder $q) => $q->utilisables(),
                        false: fn (Builder $q) => $q->where(fn ($q) => $q->where('restant', 0)->orWhereDate('expire_le', '<', today())),
                    ),
            ])
            ->headerActions([
                Action::make('offrir')
                    ->label('Offrir un bonus')
                    ->icon(Heroicon::OutlinedGift)
                    ->visible(fn () => static::peutEcrire())
                    ->modalDescription('Geste commercial : le client pourra le déduire d’une réservation avant son expiration (durée des paramètres de parrainage).')
                    ->schema([
                        Select::make('user_id')->label('Client')->required()->searchable()
                            ->getSearchResultsUsing(fn (string $search) => User::where('actif', true)
                                ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('telephone', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                                ->limit(20)->get()->mapWithKeys(fn (User $u) => [$u->id => $u->name.' · '.($u->telephone ?? $u->email)])->all())
                            ->getOptionLabelUsing(fn ($value) => User::find($value)?->name),
                        TextInput::make('montant')->label('Montant')->integer()->minValue(100)->maxValue(100000)->step(500)->suffix('FCFA')->required(),
                        TextInput::make('libelle')->label('Raison')->required()->maxLength(120)->placeholder('Ex. Désagrément lors du séjour VIVI-XXXX'),
                    ])
                    ->action(function (array $data) {
                        app(Parrainage::class)->crediter(User::findOrFail($data['user_id']), (int) $data['montant'], Bonus::MOTIF_GESTE, $data['libelle'], auteur: auth()->user());
                        Notification::make()->title('Bonus offert')->success()->send();
                    }),
            ])
            ->emptyStateHeading('Aucun bonus pour le moment')
            ->emptyStateDescription('Activez le parrainage dans Administration › Paramètres.')
            ->emptyStateIcon(Heroicon::OutlinedGift);
    }

    public static function getPages(): array
    {
        return ['index' => ListBonus::route('/')];
    }
}
