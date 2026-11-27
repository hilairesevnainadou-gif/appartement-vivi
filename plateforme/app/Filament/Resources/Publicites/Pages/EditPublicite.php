<?php

namespace App\Filament\Resources\Publicites\Pages;

use App\Filament\Resources\Publicites\Actions\EncaisserPublicite;
use App\Filament\Resources\Publicites\PubliciteResource;
use App\Models\Publicite;
use App\Services\RapportsPublicites;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPublicite extends EditRecord
{
    protected static string $resource = PubliciteResource::class;

    /** Rappel : tant que la publicité est coupée dans les Paramètres, rien ne s'affiche nulle part. */
    public function getSubheading(): ?string
    {
        return \App\Support\Publicites::actives() ? null
            : '⚠ La publicité est désactivée : activez-la dans Administration › Paramètres, sinon cette publicité ne s’affiche ni dans l’application ni sur le site.';
    }

    protected function getHeaderActions(): array
    {
        return [$this->envoyerRapport(), EncaisserPublicite::make(), DeleteAction::make()];
    }

    /** Envoi immédiat du rapport à l'annonceur, sur la période choisie (par défaut : depuis le début). */
    private function envoyerRapport(): Action
    {
        return Action::make('envoyerRapport')
            ->label('Envoyer le rapport')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('gray')
            ->visible(fn () => PubliciteResource::canCreate())
            ->disabled(fn (Publicite $record) => $record->destinatairesRapport() === [] && ! $record->annonceur_id)
            ->tooltip(fn (Publicite $record) => $record->destinatairesRapport() === [] && ! $record->annonceur_id
                ? 'Choisissez le compte de l’annonceur ou saisissez un email, puis enregistrez.' : null)
            ->modalHeading('Envoyer le rapport à l’annonceur')
            ->modalDescription(fn (Publicite $record) => 'Destinataire(s) : '.(implode(', ', $record->destinatairesRapport()) ?: $record->annonceurCompte?->name).'.')
            ->modalSubmitActionLabel('Envoyer')
            ->schema([
                DatePicker::make('du')->label('Du')->native(false)->displayFormat('d/m/Y')->required()
                    ->default(fn (Publicite $record) => ($record->debut ?? $record->created_at)->toDateString()),
                DatePicker::make('au')->label('Au')->native(false)->displayFormat('d/m/Y')->required()->afterOrEqual('du')
                    ->default(fn (Publicite $record) => ($record->fin && $record->fin->lt(today()) ? $record->fin : today())->toDateString()),
            ])
            ->action(function (Publicite $record, array $data) {
                $envoye = app(RapportsPublicites::class)->envoyer($record, \Carbon\Carbon::parse($data['du']), \Carbon\Carbon::parse($data['au']));
                $envoye
                    ? Notification::make()->success()->title('Rapport envoyé')->send()
                    : Notification::make()->warning()->title('Aucun destinataire')->body('Choisissez le compte de l’annonceur ou saisissez un email.')->send();
            });
    }
}
