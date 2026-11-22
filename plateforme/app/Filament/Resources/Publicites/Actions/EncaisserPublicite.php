<?php

namespace App\Filament\Resources\Publicites\Actions;

use App\Models\Publicite;
use App\Support\Montant;
use App\Support\Parametres;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/** Bouton « Encaisser » : règlement total ou partiel de l'annonceur. */
class EncaisserPublicite
{
    public static function make(): Action
    {
        return Action::make('encaisser')
            ->label('Encaisser')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (Publicite $record) => $record->resteAPayer() > 0 && auth()->user()?->can('publicites.encaisser'))
            ->modalHeading(fn (Publicite $record) => 'Encaisser : '.$record->titre)
            ->modalDescription(fn (Publicite $record) => 'Reste à payer : '.Montant::fcfa($record->resteAPayer()).' sur '.Montant::fcfa($record->prix).'.')
            ->modalSubmitActionLabel('Encaisser')
            ->fillForm(fn (Publicite $record) => ['montant' => $record->resteAPayer(), 'paye_le' => today()->toDateString()])
            ->schema([
                TextInput::make('montant')->label('Montant reçu')->integer()->minValue(1)
                    ->maxValue(fn (Publicite $record) => $record->resteAPayer())->suffix('FCFA')->required(),
                Select::make('mode')->label('Mode de règlement')
                    ->options(fn () => array_combine(Parametres::get('modes_reglement'), Parametres::get('modes_reglement')))->required(),
                TextInput::make('reference')->label('Référence du règlement')->maxLength(80),
                DatePicker::make('paye_le')->label('Reçu le')->native(false)->displayFormat('d/m/Y')->maxDate(today()->endOfDay())->required(),
            ])
            ->action(function (Publicite $record, array $data) {
                $record->encaisser((int) $data['montant'], $data['mode'], $data['reference'] ?? null, $data['paye_le'], auth()->user());
                Notification::make()->success()->title(Montant::fcfa((int) $data['montant']).' encaissés')->send();
            });
    }
}
