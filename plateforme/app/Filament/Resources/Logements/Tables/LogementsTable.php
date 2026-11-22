<?php

namespace App\Filament\Resources\Logements\Tables;

use App\Enums\StatutLogement;
use App\Enums\TypeLogement;
use App\Filament\Resources\Logements\LogementResource;
use App\Filament\Support\Colonnes;
use App\Models\Logement;
use App\Support\Parametres;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LogementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['proprietaire', 'gerantVivi'])->withCount('reservations'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('titre')
                    ->label('Logement ou salle')
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (Logement $record) => $record->quartier.', '.$record->ville)
                    ->searchable(['titre', 'quartier', 'ville'])
                    ->sortable(),
                Colonnes::personne('proprietaire', 'Propriétaire')->visibleFrom('md'),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    // Salles (fêtes, conférences…) en orange, logements en gris
                    ->color(fn (Logement $record) => $record->estSalle() ? 'warning' : 'gray')
                    ->icon(fn (Logement $record) => $record->estSalle() ? Heroicon::OutlinedSparkles : Heroicon::OutlinedHome)
                    ->visibleFrom('lg'),
                TextColumn::make('capacite')
                    ->label('Capacité')
                    ->formatStateUsing(fn (Logement $record) => $record->personnes())
                    ->alignCenter()
                    ->visibleFrom('2xl'),
                // Gestion : par le propriétaire, ou confiée à VIVI (avec ou sans gérant désigné)
                TextColumn::make('mode_gestion')
                    ->label('Gestion')
                    ->badge()
                    ->description(fn (Logement $record) => $record->estGereParVivi() ? ($record->gerantVivi?->name ?? 'Gérant à désigner').' · '.($record->libelleGestion() ?? 'contrat à fixer') : null)
                    ->visibleFrom('xl'),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
                IconColumn::make('en_ligne')
                    ->label('En ligne')
                    ->boolean()
                    ->visibleFrom('xl'),
                TextColumn::make('reservations_count')
                    ->label('Réservations')
                    ->alignCenter()
                    ->sortable()
                    ->visibleFrom('lg'),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('statut')->label('Statut')->options(StatutLogement::class),
                SelectFilter::make('ville')->label('Ville')->options(fn () => array_combine(Parametres::get('villes'), Parametres::get('villes'))),
                // Types de la liste affichée seulement (logements, ou salles dans SalleResource)
                SelectFilter::make('type')->label('Type')->options(fn ($livewire) => collect(TypeLogement::deCategorie($livewire::getResource()::categorie()))
                    ->mapWithKeys(fn (TypeLogement $t) => [$t->value => $t->getLabel()])->all()),
                SelectFilter::make('mode_gestion')->label('Gestion')->options(\App\Enums\ModeGestion::class),
                \Filament\Tables\Filters\Filter::make('contrat_a_fixer')->label('Contrat de gestion à fixer')
                    ->query(fn (Builder $q) => $q->where('mode_gestion', \App\Enums\ModeGestion::Vivi)->whereNull('gestion_type')),
                \Filament\Tables\Filters\Filter::make('gerant_a_designer')->label('Gérant VIVI à désigner')
                    ->query(fn (Builder $q) => $q->where('mode_gestion', \App\Enums\ModeGestion::Vivi)->whereNull('gerant_vivi_id'))
                    ->toggle(),
                TrashedFilter::make()->label('Supprimés'),
            ])
            ->emptyStateHeading(fn ($livewire) => $livewire::getResource()::categorie() === 'salle' ? 'Aucune salle' : 'Aucun logement')
            ->emptyStateDescription('Les fiches créées par les propriétaires apparaîtront ici.')
            ->recordActions([self::actionValider(), EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    /** Publier un logement en attente de vérification : liste des logements et tableau de bord. */
    public static function actionValider(): Action
    {
        return Action::make('valider')
            ->label('Valider')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (Logement $record) => $record->statut === StatutLogement::EnValidation && LogementResource::canEdit($record))
            ->requiresConfirmation()
            ->modalHeading('Publier ce logement ?')
            ->modalDescription('Il apparaîtra dans la recherche, si le propriétaire ne l’a pas mis en pause.')
            ->action(function (Logement $record) {
                $record->update(['statut' => StatutLogement::Publie, 'valide_le' => now(), 'valide_par' => auth()->id(), 'motif_refus' => null]);
                \App\Support\Prevenir::sansBloquer(fn () => $record->loadMissing('proprietaire')->proprietaire?->notify(new \App\Notifications\DecisionLogement($record)));
                Notification::make()->title('Logement publié')->success()->send();
            });
    }
}
