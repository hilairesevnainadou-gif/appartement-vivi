<?php

namespace App\Filament\Resources\Offres\Pages;

use App\Filament\Resources\Offres\OffreResource;
use App\Models\Offre;
use App\Services\Offres;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageOffres extends ManageRecords
{
    protected static string $resource = OffreResource::class;

    public function getSubheading(): ?string
    {
        return 'Envoyées dans l’espace Notifications et en push sur l’application. Jamais par SMS ni email ; seulement aux personnes qui acceptent les offres.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Envoyer une offre')
                ->modalHeading('Nouvelle offre')
                ->modalSubmitActionLabel('Envoyer maintenant')
                ->createAnother(false)
                ->mutateDataUsing(fn (array $data) => [...$data, 'envoyee_par' => auth()->id()])
                ->successNotification(null)
                ->after(function (Offre $record) {
                    $n = app(Offres::class)->envoyer($record, auth()->user());
                    Notification::make()->success()
                        ->title($n ? 'Offre envoyée à '.$n.' personne'.($n > 1 ? 's' : '') : 'Offre enregistrée, mais personne ne correspond à cette cible')
                        ->send();
                }),
        ];
    }
}
