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
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;
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
                ->schema(function () {
                    // Mêmes disponibilités que le propriétaire et l'appli : jours complets grisés, heures prises non cliquables
                    $options = app(Reservations::class)->optionsDeplacement($this->getRecord());

                    return [
                        DatePicker::make('date')->label('Nouvelle date')->native(false)->displayFormat('d/m/Y')->required()
                            ->minDate($options['date_min'])->maxDate($options['date_max'])
                            ->disabledDates($options['jours_complets'])
                            ->helperText('Les jours complets sont grisés.')
                            ->default($options['date'])
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('heure', null)),
                        ToggleButtons::make('heure')->label('Arrivée')->required()->inline()
                            ->helperText('Les heures déjà prises sont barrées et ne peuvent pas être choisies.')
                            ->options(fn (Get $get) => $this->heuresDeplacement($get('date'), barrer: true))
                            ->disableOptionWhen(fn (string $value, Get $get) => ! ($this->heuresDeplacement($get('date'))[$value] ?? false)),
                    ];
                })
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

    /** @var array<string, array<string, bool>> heures du jour déjà calculées (le formulaire les demande plusieurs fois) */
    private array $heuresCalculees = [];

    /**
     * Heures d'arrivée du jour pour déplacer : ['14:00' => libre ?], ou, avec $barrer, les libellés (heures prises barrées).
     *
     * @return array<string, bool|HtmlString>
     */
    private function heuresDeplacement(?string $date, bool $barrer = false): array
    {
        if (! $date || ! ($jour = rescue(fn () => CarbonImmutable::parse($date)->startOfDay(), null, false))) {
            return [];
        }
        $cle = $jour->toDateString();
        $this->heuresCalculees[$cle] ??= collect(app(Reservations::class)->heuresDeplacement($this->getRecord()->loadMissing('logement'), $jour))
            ->mapWithKeys(fn ($h) => [$h['heure'] => $h['libre']])->all();

        return $barrer
            ? collect($this->heuresCalculees[$cle])->map(fn ($libre, $h) => $libre ? $h : new HtmlString('<s>'.e($h).'</s>'))->all()
            : $this->heuresCalculees[$cle];
    }

    /** Charge en une fois tout ce que la fiche affiche (pas de requêtes une par une). */
    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load(['client', 'logement', 'creneau', 'services', 'paiements', 'scannePar', 'deplaceePar']);
    }
}
