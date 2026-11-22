<?php

namespace App\Filament\Resources\Avis;

use App\Enums\StatutAvis;
use App\Filament\Concerns\AccesParRole;
use App\Filament\Resources\Avis\Pages\ManageAvis;
use App\Models\Avis;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Modération des avis clients avant publication sur la fiche logement. */
class AvisResource extends Resource
{
    use AccesParRole;

    protected static ?string $model = Avis::class;

    /** Écran de consultation : ces actions ne sont proposées à personne. */
    protected static array $actionsInterdites = ['create', 'update'];

    protected static ?string $modelLabel = 'avis';

    protected static ?string $pluralModelLabel = 'Avis';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationLabel = 'Avis clients';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'Réservations';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        $changer = fn (StatutAvis $statut, string $message) => function (Avis $record) use ($statut, $message) {
            $record->update(['statut' => $statut]);
            Notification::make()->title($message)->success()->send();
        };

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['client', 'logement']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Date')->date('d/m/Y')->sortable()->visibleFrom('md'),
                TextColumn::make('logement.titre')->label('Logement')->searchable(),
                TextColumn::make('client.name')->label('Client')->visibleFrom('lg'),
                TextColumn::make('note')->label('Note')->formatStateUsing(fn (int $state) => str_repeat('★', $state).str_repeat('☆', 5 - $state))->color('warning'),
                TextColumn::make('commentaire')->label('Commentaire')->wrap()->limit(160)->placeholder('—')->color('gray'),
                TextColumn::make('statut')->label('Statut')->badge(),
            ])
            ->filters([
                SelectFilter::make('statut')->label('Statut')->options(StatutAvis::class),
                SelectFilter::make('note')->label('Note')->options([1 => '1 étoile', 2 => '2 étoiles', 3 => '3 étoiles', 4 => '4 étoiles', 5 => '5 étoiles']),
            ])
            ->recordActions([
                Action::make('publier')->label('Publier')->icon(Heroicon::OutlinedEye)->color('success')
                    ->visible(fn (Avis $record) => $record->statut !== StatutAvis::Publie)
                    ->requiresConfirmation()->modalHeading('Publier cet avis ?')
                    ->modalDescription('Il apparaîtra sur la fiche du logement et comptera dans sa note.')->modalSubmitActionLabel('Publier')
                    ->action($changer(StatutAvis::Publie, 'Avis publié sur la fiche du logement')),
                Action::make('masquer')->label('Masquer')->icon(Heroicon::OutlinedEyeSlash)->color('gray')
                    ->visible(fn (Avis $record) => $record->statut !== StatutAvis::Masque)
                    ->requiresConfirmation()
                    ->action($changer(StatutAvis::Masque, 'Avis masqué')),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Avis::where('statut', StatutAvis::EnAttente)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAvis::route('/'),
        ];
    }
}
