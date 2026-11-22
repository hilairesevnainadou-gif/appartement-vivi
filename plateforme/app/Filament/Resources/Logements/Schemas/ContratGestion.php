<?php

namespace App\Filament\Resources\Logements\Schemas;

use App\Enums\RemunerationGestion;
use App\Models\Logement;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

/** Champs du contrat de gestion (rémunération de VIVI) : fiche et fenêtre « Désigner le gérant VIVI ». */
class ContratGestion
{
    /** @return list<\Filament\Schemas\Components\Component> */
    public static function champs(?callable $visible = null): array
    {
        $visible ??= fn () => true;
        $type = fn (Get $get): ?RemunerationGestion => ($t = $get('gestion_type')) instanceof RemunerationGestion ? $t : RemunerationGestion::tryFrom((string) $t);

        return [
            Select::make('gestion_type')->label('Rémunération de VIVI')
                ->options(RemunerationGestion::class)
                ->placeholder('À fixer avec le propriétaire')
                ->helperText('Selon le contrat. Retirée de ce qui revient au propriétaire, sur les nouvelles réservations.')
                ->live()->visible($visible),
            TextInput::make('gestion_valeur')
                ->label(fn (Get $get) => $type($get)?->estFixe() ? 'Prix fixe' : 'Pourcentage')
                ->suffix(fn (Get $get) => match ($type($get)) {
                    RemunerationGestion::Fixe => 'FCFA par réservation',
                    RemunerationGestion::FixeJour => 'FCFA par jour',
                    default => '%',
                })
                ->helperText(fn (Get $get) => $type($get) === RemunerationGestion::FixeJour ? 'Multiplié par le nombre de jours. À l’heure ou à la demi-journée : un jour.' : null)
                ->numeric()->minValue(0.01)
                ->maxValue(fn (Get $get) => $type($get)?->estFixe() ? 10000000 : 100)
                ->visible(fn (Get $get) => $visible($get) && $type($get) !== null)
                ->required(fn (Get $get) => $type($get) !== null),
            TextInput::make('gestion_contrat')->label('Référence du contrat')->maxLength(60)->visible($visible),
            DatePicker::make('gestion_contrat_le')->label('Contrat signé le')->visible($visible),
        ];
    }

    /** Valeurs actuelles, pour préremplir la fenêtre « Désigner le gérant VIVI ». */
    public static function valeurs(Logement $l): array
    {
        return [
            'gestion_type' => $l->gestion_type?->value,
            'gestion_valeur' => $l->gestion_type ? (float) $l->gestion_valeur : null,
            'gestion_contrat' => $l->gestion_contrat,
            'gestion_contrat_le' => $l->gestion_contrat_le?->toDateString(),
        ];
    }
}
