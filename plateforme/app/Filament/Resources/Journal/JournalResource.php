<?php

namespace App\Filament\Resources\Journal;

use App\Filament\Concerns\AccesParRole;
use App\Filament\Resources\Journal\Pages\ListJournal;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * Journal d'activité : qui a fait quoi, quand (créations, modifications, suppressions, connexions,
 * permissions). Lecture seule ; les entrées de plus d'un an sont purgées (config/activitylog.php).
 */
class JournalResource extends Resource
{
    use AccesParRole;

    protected static ?string $model = Activity::class;

    protected static ?string $slug = 'journal';

    protected static ?string $modelLabel = 'entrée du journal';

    protected static ?string $pluralModelLabel = 'Journal d’activité';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationLabel = 'Journal d’activité';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 4;

    protected static array $actionsInterdites = ['create', 'update', 'delete', 'deleteAny', 'forceDelete', 'restore'];

    /** Nom lisible des éléments suivis. */
    /** Clé = alias de la table de correspondance (AppServiceProvider::enforceMorphMap). */
    public const TYPES = [
        'logement' => 'Logement', 'reservation' => 'Réservation', 'retrait' => 'Retrait', 'abonnement' => 'Abonnement',
        'formule_abonnement' => 'Formule d’abonnement', 'service' => 'Service', 'equipement' => 'Équipement', 'user' => 'Utilisateur',
        'bonus' => 'Bonus', 'parametre' => 'Paramètre', 'avis' => 'Avis', 'paiement' => 'Paiement', 'blocage' => 'Jour bloqué',
        'tarif' => 'Tarif', 'role' => 'Rôle', 'alerte' => 'Alerte',
    ];

    public static function type(?string $alias): string
    {
        return self::TYPES[$alias] ?? ($alias ? class_basename($alias) : '—');
    }

    /** Identifiant parlant de l'élément : référence, titre, nom ou clé. */
    public static function element(Activity $a): string
    {
        $s = $a->subject;
        $nom = $s?->reference ?? $s?->titre ?? $s?->name ?? $s?->nom ?? $s?->cle ?? null;

        return trim(self::type($a->subject_type).' '.($nom ?: ($a->subject_id ? '#'.$a->subject_id : '')));
    }

    public static function champ(string $cle): string
    {
        return [
            'statut' => 'Statut', 'titre' => 'Titre', 'name' => 'Nom', 'nom' => 'Nom', 'telephone' => 'Téléphone', 'email' => 'Email',
            'actif' => 'Actif', 'en_ligne' => 'En ligne', 'prix' => 'Prix', 'prix_mensuel' => 'Prix mensuel', 'montant' => 'Montant',
            'montant_total' => 'Total', 'montant_rembourse' => 'Remboursé', 'debut' => 'Début', 'fin' => 'Fin', 'valeur' => 'Valeur',
            'mode_reglement' => 'Mode de règlement', 'motif_annulation' => 'Motif', 'unites' => 'Chambres louables', 'capacite' => 'Voyageurs',
        ][$cle] ?? Str::of($cle)->replace('_', ' ')->ucfirst()->toString();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['causer', 'subject']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i')->sortable()
                    ->description(fn (Activity $record) => $record->created_at->diffForHumans()),
                TextColumn::make('causer.name')->label('Par')->placeholder('Système')
                    ->description(fn (Activity $record) => $record->causer?->libelleRole() ?? 'Tâche automatique ou visiteur'),
                TextColumn::make('description')->label('Action')->weight('semibold')->wrap()
                    ->description(fn (Activity $record) => static::element($record)),
                TextColumn::make('log_name')->label('Catégorie')->badge()
                    ->formatStateUsing(fn ($state) => match ($state) { 'connexion' => 'Connexion', 'administration' => 'Droits', 'donnees' => 'Données', default => ucfirst((string) $state) })
                    ->color(fn ($state) => match ($state) { 'connexion' => 'info', 'administration' => 'warning', default => 'gray' })
                    ->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('log_name')->label('Catégorie')->options(['donnees' => 'Données', 'connexion' => 'Connexions', 'administration' => 'Droits et rôles']),
                SelectFilter::make('subject_type')->label('Élément')->options(self::TYPES),
                SelectFilter::make('causer_id')->label('Utilisateur')->searchable()
                    ->getSearchResultsUsing(fn (string $search) => User::where('name', 'like', "%{$search}%")->limit(20)->pluck('name', 'id')->all())
                    ->getOptionLabelUsing(fn ($value) => User::find($value)?->name),
                Filter::make('periode')
                    ->schema([
                        DatePicker::make('du')->label('Du')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('au')->label('Au')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['du'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['au'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                Action::make('details')->label('Détails')->icon(Heroicon::OutlinedEye)->color('gray')
                    ->modalHeading(fn (Activity $record) => $record->description)
                    ->modalDescription(fn (Activity $record) => static::element($record).' · '.$record->created_at->translatedFormat('j F Y à H:i').' · '.($record->causer?->name ?? 'Système'))
                    ->modalContent(fn (Activity $record) => view('filament.journal-details', ['activite' => $record]))
                    ->modalSubmitAction(false)->modalCancelActionLabel('Fermer'),
            ])
            ->emptyStateHeading('Aucune activité enregistrée')
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList);
    }

    public static function getPages(): array
    {
        return ['index' => ListJournal::route('/')];
    }
}
