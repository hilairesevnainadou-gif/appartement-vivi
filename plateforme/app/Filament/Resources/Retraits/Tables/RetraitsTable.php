<?php

namespace App\Filament\Resources\Retraits\Tables;

use App\Enums\StatutRetrait;
use App\Filament\Resources\Retraits\RetraitResource;
use App\Filament\Support\Colonnes;
use App\Models\Retrait;
use App\Services\Caisse;
use App\Support\Montant;
use App\Support\Parametres;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use RuntimeException;

class RetraitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['proprietaire', 'traitePar']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Colonnes::reference(),
                Colonnes::personne('proprietaire', 'Bénéficiaire'),   // propriétaire ou agent marketing
                Colonnes::montant('montant', 'Montant'),
                // Compte indiqué par le bénéficiaire dans l'application : là où payer
                TextColumn::make('compte_numero')->label('Payer sur')
                    ->description(fn (Retrait $record) => trim($record->compte_moyen.' · '.$record->compte_titulaire, ' ·'))
                    ->placeholder('Non renseigné')
                    ->copyable(),
                TextColumn::make('statut')->label('Statut')->badge(),
                Colonnes::dateHeure('created_at', 'Demandé le')->visibleFrom('md'),
                TextColumn::make('mode_reglement')->label('Règlement')
                    ->description(fn (Retrait $record) => $record->reference_reglement)
                    ->placeholder('—')
                    ->visibleFrom('lg'),
                TextColumn::make('traitePar.name')->label('Traité par')->toggleable(isToggledHiddenByDefault: true)
                    ->description(fn (Retrait $record) => $record->traite_le?->format('d/m/Y H:i'))
                    ->placeholder('—'),
            ])
            ->emptyStateHeading('Aucun retrait')
            ->emptyStateDescription('Les demandes de retrait des propriétaires apparaîtront ici.')
            ->emptyStateIcon(Heroicon::OutlinedBanknotes)
            ->filters([
                SelectFilter::make('statut')->label('Statut')->options(StatutRetrait::class)->default(StatutRetrait::EnAttente->value),
            ])
            ->recordActions(self::actionsTraitement());
    }

    /** Valider (avec le mode de règlement) ou refuser un retrait : liste des retraits et tableau de bord. */
    public static function actionsTraitement(): array
    {
        $peutTraiter = fn (Retrait $record) => $record->statut === StatutRetrait::EnAttente && RetraitResource::peutTraiter();

        return [
            Action::make('payer')
                ->label('Valider')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible($peutTraiter)
                ->modalHeading(fn (Retrait $record) => 'Payer '.Montant::fcfa($record->montant).' à '.$record->proprietaire->name)
                ->modalDescription('Indiquez comment le propriétaire a été payé. Cette action est définitive.')
                ->schema([
                    Select::make('mode_reglement')
                        ->label('Mode de règlement')
                        ->options(fn () => array_combine(Parametres::get('modes_reglement'), Parametres::get('modes_reglement')))
                        ->required(),
                    TextInput::make('reference_reglement')
                        ->label('Référence de la transaction')
                        ->helperText('Numéro de transaction Mobile Money, de virement… (facultatif pour les espèces)'),
                    Textarea::make('note')->label('Note interne')->rows(2),
                ])
                ->action(function (Retrait $record, array $data) {
                    self::executer(fn () => app(Caisse::class)->validerRetrait($record, auth()->user(), $data['mode_reglement'], $data['reference_reglement'] ?? null, $data['note'] ?? null),
                        'Retrait payé par '.$data['mode_reglement']);
                }),
            Action::make('refuser')
                ->label('Refuser')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible($peutTraiter)
                ->modalDescription('Le montant sera recrédité sur le solde du propriétaire.')
                ->schema([Textarea::make('motif')->label('Motif, communiqué au propriétaire')->required()])
                ->action(function (Retrait $record, array $data) {
                    self::executer(fn () => app(Caisse::class)->refuserRetrait($record, auth()->user(), $data['motif']),
                        'Retrait refusé : montant recrédité au propriétaire');
                }),
        ];
    }

    private static function executer(callable $operation, string $succes): void
    {
        try {
            $operation();
            Notification::make()->title($succes)->success()->send();
        } catch (RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }
}
