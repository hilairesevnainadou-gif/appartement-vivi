<?php

namespace App\Filament\Widgets;

use App\Enums\StatutRetrait;
use App\Filament\Resources\Retraits\RetraitResource;
use App\Filament\Resources\Retraits\Tables\RetraitsTable;
use App\Models\Retrait;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** File de la caisse : retraits des propriétaires à payer, avec les mêmes actions que la liste. */
class RetraitsAValider extends TableWidget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected static ?string $heading = 'Retraits à valider';

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    public static function canView(): bool
    {
        return RetraitResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Retrait::query()->with('proprietaire')->where('statut', StatutRetrait::EnAttente)->oldest())
            // Une seule colonne (montant en gras, puis qui et quand) : les boutons restent visibles dans la demi-largeur
            ->columns([
                TextColumn::make('montant')->label('Demande')
                    ->formatStateUsing(fn ($state) => \App\Support\Montant::fcfa($state))
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->wrap()
                    ->description(fn (Retrait $record) => $record->proprietaire->name.' · '.$record->reference.' · '.$record->created_at->diffForHumans()),
            ])
            // Boutons avec libellé (Valider, Refuser) : plus lisibles et plus faciles à toucher que des icônes
            ->recordActions(RetraitsTable::actionsTraitement())
            ->headerActions([
                Action::make('tout')->label('Tout voir')->link()->url(RetraitResource::getUrl('index')),
            ])
            ->paginated([5])
            ->emptyStateHeading('Aucun retrait en attente')
            ->emptyStateDescription('Les demandes des propriétaires arriveront ici.')
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle);
    }
}
