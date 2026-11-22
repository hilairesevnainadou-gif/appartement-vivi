<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Façon de calculer la part de l'agent marketing, fixée par le propriétaire pour chaque logement. */
enum TypeMarketing: string implements HasLabel
{
    case Pourcentage = 'pourcentage';   // pourcentage du prix de la location
    case Fixe = 'fixe';                 // montant fixe par réservation

    /** Pourcentage le plus élevé que le propriétaire peut accorder. */
    public const POURCENTAGE_MAX = 50;

    public function getLabel(): string
    {
        return match ($this) {
            self::Pourcentage => 'Pourcentage de la location',
            self::Fixe => 'Montant fixe par réservation',
        };
    }

    /** Part revenant à l'agent (ou à VIVI sans agent) pour une location donnée : jamais plus que la location. */
    public function montant(float $valeur, int $location): int
    {
        $montant = match ($this) {
            self::Pourcentage => (int) round($location * min(self::POURCENTAGE_MAX, max(0, $valeur)) / 100),
            self::Fixe => (int) round(max(0, $valeur)),
        };

        return max(0, min($location, $montant));
    }

    /** « 10 % de la location », « 2 000 FCFA par réservation ». */
    public function libelleValeur(float $valeur): string
    {
        return match ($this) {
            self::Pourcentage => rtrim(rtrim(number_format($valeur, 2, ',', ' '), '0'), ',').' % de la location',
            self::Fixe => \App\Support\Montant::fcfa((int) round($valeur)).' par réservation',
        };
    }
}
