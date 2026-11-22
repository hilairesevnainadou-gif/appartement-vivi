<?php

namespace App\Filament\Widgets;

use App\Enums\Role;
use App\Enums\StatutLogement;
use App\Filament\Resources\Logements\LogementResource;
use App\Filament\Resources\Logements\Tables\LogementsTable;
use App\Models\Logement;
use Filament\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Logements soumis par les propriétaires, à vérifier avant publication (administrateur). */
class LogementsAValider extends TableWidget
{
    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected static ?string $heading = 'Logements à valider';

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('logements.gerer');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Logement::query()->with('proprietaire')->where('statut', StatutLogement::EnValidation)->oldest('updated_at'))
            ->columns([
                TextColumn::make('titre')->label('Logement ou salle')->weight(FontWeight::SemiBold)
                    ->description(fn (Logement $record) => $record->proprietaire?->name.' · '.$record->quartier.', '.$record->ville),
                TextColumn::make('type')->label('Type')->badge()
                    ->color(fn (Logement $record) => $record->estSalle() ? 'warning' : 'gray'),
                TextColumn::make('mode_gestion')->label('Gestion')->badge()->visibleFrom('md'),
            ])
            ->recordActions([
                LogementsTable::actionValider()->label('Publier'),
                Action::make('examiner')->label('Examiner')->icon(Heroicon::OutlinedEye)->color('gray')
                    ->url(fn (Logement $record) => LogementResource::urlFiche($record)),   // liste des logements ou des salles
            ])
            ->headerActions([
                Action::make('tout')->label('Tout voir')->link()->url(LogementResource::getUrl('index')),
            ])
            ->paginated([5])
            ->emptyStateHeading('Aucun logement en attente')
            ->emptyStateIcon(Heroicon::OutlinedCheckBadge);
    }
}
