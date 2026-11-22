<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Enums\Role;
use App\Enums\StatutReservation;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Models\Reservation;
use App\Services\ReservationImpossible;
use App\Services\Reservations;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class ViewReservation extends ViewRecord
{
    protected static string $resource = ReservationResource::class;

    public function getTitle(): string
    {
        return 'Réservation '.$this->getRecord()->reference;
    }

    /**
     * Déplacement par l'administrateur (décision du 12/11/2026) : réservation consommée (client absent)
     * ou confirmée, même durée, à une date où le logement est libre. Le client est prévenu.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('deplacer')
                ->label('Déplacer')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('primary')
                ->visible(fn () => auth()->user()->hasRole(Role::Administrateur->value)
                    && in_array($this->getRecord()->statut, [StatutReservation::Confirmee, StatutReservation::Consommee], true))
                ->modalHeading('Déplacer la réservation')
                ->modalDescription(fn () => 'Même durée ('.$this->getRecord()->dureeCourte().'). Le logement doit être libre, temps de préparation compris. Le client est prévenu et garde son QR Code.')
                ->schema([
                    DatePicker::make('date')->label('Nouvelle date')->native(false)->displayFormat('d/m/Y')->minDate(today())->required()
                        ->default(fn () => $this->getRecord()->debut->toDateString()),
                    TimePicker::make('heure')->label('Arrivée')->seconds(false)->required()
                        ->default(fn () => $this->getRecord()->debut->format('H:i')),
                ])
                ->action(function (array $data) {
                    /** @var Reservation $r */
                    $r = $this->getRecord();
                    $debut = CarbonImmutable::parse($data['date'])->setTimeFromTimeString($data['heure']);
                    try {
                        app(Reservations::class)->deplacer($r, $debut, auth()->user());
                        Notification::make()->title('Réservation déplacée au '.$debut->translatedFormat('j F à H:i').' : le client est prévenu')->success()->send();
                        $this->refreshFormData([]);
                        $this->redirect(ReservationResource::getUrl('view', ['record' => $r]));
                    } catch (ReservationImpossible $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    /** Charge en une fois tout ce que la fiche affiche (pas de requêtes une par une). */
    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load(['client', 'logement', 'creneau', 'services', 'paiements', 'scannePar', 'deplaceePar']);
    }
}
