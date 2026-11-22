<?php

namespace App\Filament\Resources\Alertes;

use App\Filament\Concerns\AccesParRole;
use App\Filament\Resources\Alertes\Pages\ListAlertes;
use App\Models\AlerteSysteme;
use App\Services\Surveillance;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

/** Alertes de surveillance (pannes, attaques) : à examiner puis marquer comme traitées. */
class AlerteResource extends Resource
{
    use AccesParRole;

    protected static ?string $model = AlerteSysteme::class;

    protected static ?string $slug = 'alertes';

    protected static ?string $modelLabel = 'alerte';

    protected static ?string $pluralModelLabel = 'Alertes système';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationLabel = 'Alertes système';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 5;

    protected static array $actionsInterdites = ['create', 'update', 'delete', 'deleteAny'];

    /** Couleur Filament d'un niveau. */
    public static function couleur(string $niveau): string
    {
        return match ($niveau) { AlerteSysteme::CRITIQUE => 'danger', AlerteSysteme::AVERTISSEMENT => 'warning', default => 'info' };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('resoluePar'))
            ->defaultSort('derniere_le', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('niveau')->label('Niveau')->badge()
                    ->formatStateUsing(fn (string $state) => AlerteSysteme::libelleNiveau($state))
                    ->color(fn (string $state) => static::couleur($state))
                    ->icon(fn (string $state) => $state === AlerteSysteme::CRITIQUE ? 'heroicon-m-shield-exclamation' : 'heroicon-m-exclamation-triangle'),
                TextColumn::make('titre')->label('Alerte')->weight('semibold')->wrap()
                    ->description(fn (AlerteSysteme $r) => \Illuminate\Support\Str::limit($r->message, 160)),
                TextColumn::make('source')->label('Source')->fontFamily('mono')->placeholder('—')->visibleFrom('lg')->copyable(),
                TextColumn::make('occurrences')->label('Fois')->alignCenter()->visibleFrom('md'),
                TextColumn::make('derniere_le')->label('Dernière fois')->dateTime('d/m/Y H:i')->sortable()
                    ->description(fn (AlerteSysteme $r) => $r->derniere_le?->diffForHumans()),
                TextColumn::make('etat')->label('État')->badge()
                    ->getStateUsing(fn (AlerteSysteme $record) => $record->resolue_le
                        ? 'Traitée'.($record->resoluePar ? ' par '.$record->resoluePar->prenom() : '')
                        : 'À traiter')
                    ->color(fn (AlerteSysteme $record) => $record->resolue_le ? 'success' : ($record->niveau === AlerteSysteme::CRITIQUE ? 'danger' : 'warning'))
                    ->visibleFrom('md'),
            ])
            ->filters([
                TernaryFilter::make('ouvertes')->label('État')->placeholder('Toutes')
                    ->trueLabel('À traiter')->falseLabel('Traitées')
                    ->queries(true: fn (Builder $query) => $query->whereNull('resolue_le'), false: fn (Builder $query) => $query->whereNotNull('resolue_le'))
                    ->default(true),
                SelectFilter::make('niveau')->label('Niveau')->options([
                    AlerteSysteme::CRITIQUE => 'Critique', AlerteSysteme::AVERTISSEMENT => 'Avertissement', AlerteSysteme::INFO => 'Information',
                ]),
                SelectFilter::make('type')->label('Type')->options(Surveillance::TYPES),
            ])
            ->recordActions([
                Action::make('details')->label('Détails')->icon(Heroicon::OutlinedEye)->color('gray')
                    ->modalHeading(fn (AlerteSysteme $r) => $r->titre)
                    ->modalDescription(fn (AlerteSysteme $r) => AlerteSysteme::libelleNiveau($r->niveau).' · première fois le '.$r->created_at->format('d/m/Y H:i')
                        .' · '.$r->occurrences.' occurrence(s)')
                    ->modalContent(fn (AlerteSysteme $r) => view('filament.alerte-details', ['alerte' => $r]))
                    ->modalSubmitAction(false)->modalCancelActionLabel('Fermer'),
                Action::make('traiter')->label('Traitée')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                    ->visible(fn (AlerteSysteme $r) => ! $r->resolue_le && static::peutEcrire())
                    ->requiresConfirmation()->modalHeading('Marquer l’alerte comme traitée ?')
                    ->modalDescription('Si l’incident se reproduit, une nouvelle alerte sera créée.')
                    ->action(fn (AlerteSysteme $r) => static::traiter(collect([$r]))),
            ])
            ->toolbarActions([
                BulkAction::make('traiter')->label('Marquer comme traitées')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                    ->visible(fn () => static::peutEcrire())->requiresConfirmation()
                    ->action(fn (Collection $records) => static::traiter($records)),
            ])
            ->emptyStateHeading('Aucune alerte à traiter')
            ->emptyStateDescription('La surveillance n’a rien détecté d’anormal.')
            ->emptyStateIcon(Heroicon::OutlinedShieldCheck);
    }

    public static function traiter(Collection $alertes): void
    {
        foreach ($alertes->whereNull('resolue_le') as $a) {
            $a->forceFill(['resolue_le' => now(), 'resolue_par' => auth()->id()])->save();
            activity('surveillance')->performedOn($a)->event('traitee')->log('Alerte traitée : '.$a->titre);
        }
    }

    public static function getNavigationBadge(): ?string
    {
        $n = AlerteSysteme::ouvertes()->where('niveau', '!=', AlerteSysteme::INFO)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return AlerteSysteme::ouvertes()->where('niveau', AlerteSysteme::CRITIQUE)->exists() ? 'danger' : 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Alertes à traiter';
    }

    public static function getPages(): array
    {
        return ['index' => ListAlertes::route('/')];
    }
}
