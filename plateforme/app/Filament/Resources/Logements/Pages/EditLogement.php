<?php

namespace App\Filament\Resources\Logements\Pages;

use App\Enums\StatutLogement;
use App\Filament\Resources\Logements\LogementResource;
use App\Filament\Resources\Logements\Schemas\ContratGestion;
use App\Notifications\DecisionLogement;
use App\Support\Prevenir;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditLogement extends EditRecord
{
    protected static string $resource = LogementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('valider')
                ->label('Valider et publier')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->visible(fn () => in_array($this->record->statut, [StatutLogement::EnValidation, StatutLogement::Refuse, StatutLogement::Suspendu], true))
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['statut' => StatutLogement::Publie, 'valide_le' => now(), 'valide_par' => auth()->id(), 'motif_refus' => null]);
                    $this->refreshFormData(['statut', 'motif_refus']);
                    $this->prevenirProprietaire();
                    Notification::make()->title('Logement publié')->success()->send();
                }),
            Action::make('refuser')
                ->label('Refuser')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn () => $this->record->statut === StatutLogement::EnValidation)
                ->modalHeading('Refuser ce logement ?')->modalDescription('Le propriétaire voit le motif et peut corriger sa fiche, puis la renvoyer.')
                ->modalSubmitActionLabel('Refuser')
                ->schema([Textarea::make('motif')->label('Motif, envoyé au propriétaire')->required()])
                ->action(function (array $data) {
                    $this->record->update(['statut' => StatutLogement::Refuse, 'motif_refus' => $data['motif']]);
                    $this->refreshFormData(['statut', 'motif_refus']);
                    $this->prevenirProprietaire();
                    Notification::make()->title('Logement refusé')->warning()->send();
                }),
            Action::make('suspendre')
                ->label('Suspendre')
                ->icon(Heroicon::OutlinedPauseCircle)
                ->color('warning')
                ->visible(fn () => $this->record->statut === StatutLogement::Publie)
                ->modalHeading('Suspendre ce logement ?')->modalDescription('Il disparaît de la recherche ; les réservations déjà payées restent valables.')
                ->modalSubmitActionLabel('Suspendre')
                ->schema([Textarea::make('motif')->label('Motif de la suspension')->required()])
                ->action(function (array $data) {
                    $this->record->update(['statut' => StatutLogement::Suspendu, 'motif_refus' => $data['motif']]);
                    $this->refreshFormData(['statut', 'motif_refus']);
                    $this->prevenirProprietaire();
                    Notification::make()->title('Logement suspendu : il n’apparaît plus dans la recherche')->warning()->send();
                }),
            // Gestion confiée à VIVI : un gérant de l'équipe accueille les clients et scanne leur QR Code
            Action::make('gerantVivi')
                ->label(fn () => $this->record->gerant_vivi_id ? 'Changer le gérant VIVI' : 'Désigner le gérant VIVI')
                ->icon(Heroicon::OutlinedUserPlus)
                ->color(fn () => $this->record->estGereParVivi() && ! $this->record->gerant_vivi_id ? 'warning' : 'gray')
                ->modalHeading('Gérant VIVI et contrat')
                ->fillForm(fn () => ['gerant' => $this->record->gerant_vivi_id] + ContratGestion::valeurs($this->record))
                ->modalDescription('Il voit les arrivées et le calendrier de cette fiche, et scanne le QR Code des clients. Il ne compte pas dans les gestionnaires du propriétaire.')
                ->schema([
                    \Filament\Forms\Components\Select::make('gerant')
                        ->label('Gérant')
                        ->options(fn () => \App\Models\User::role([\App\Enums\Role::Gestionnaire->value, \App\Enums\Role::Administrateur->value, \App\Enums\Role::Caissiere->value])
                            ->where('actif', true)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->helperText('Un compte gestionnaire de l’équipe VIVI (créé dans Utilisateurs).'),
                    ...ContratGestion::champs(),
                ])
                ->action(function (array $data) {
                    app(\App\Services\GestionLogements::class)->designerGerantVivi($this->record, \App\Models\User::findOrFail($data['gerant']), $data);
                    $this->refreshFormData(['mode_gestion', 'gerant_vivi_id', 'gestion_type', 'gestion_valeur', 'gestion_contrat', 'gestion_contrat_le']);
                    Notification::make()->title('Gérant VIVI désigné : le propriétaire et le gérant sont prévenus')->success()->send();
                }),
            Action::make('rendreGestion')
                ->label('Rendre la gestion au propriétaire')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->visible(fn () => $this->record->estGereParVivi())
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['mode_gestion' => \App\Enums\ModeGestion::Proprietaire, 'gerant_vivi_id' => null]);
                    $this->refreshFormData(['mode_gestion', 'gerant_vivi_id']);
                    Notification::make()->title('Le propriétaire gère de nouveau cette fiche')->success()->send();
                }),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /** Le propriétaire apprend la décision de VIVI (espace Notifications, push, email/SMS). */
    private function prevenirProprietaire(): void
    {
        $logement = $this->record->fresh('proprietaire');
        Prevenir::sansBloquer(fn () => $logement->proprietaire?->notify(new DecisionLogement($logement)));
    }
}
