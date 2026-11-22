<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Formules de location courte durée. */
enum Formule: string implements HasLabel
{
    case Heure = 'heure';
    case DemiJournee = 'demi_journee';
    case Journee = 'journee';

    public function getLabel(): string
    {
        return match ($this) {
            self::Heure => 'À l’heure',
            self::DemiJournee => 'À la demi-journée',
            self::Journee => 'À la journée',
        };
    }

    /** Libellé court des sélecteurs : « Heure », « Demi-journée », « Journée ». */
    public function court(): string
    {
        return match ($this) {
            self::Heure => 'Heure',
            self::DemiJournee => 'Demi-journée',
            self::Journee => 'Journée',
        };
    }

    /** Unité de prix après un montant : « / heure ». */
    public function unite(): string
    {
        return match ($this) {
            self::Heure => '/ heure',
            self::DemiJournee => '/ demi-journée',
            self::Journee => '/ jour',
        };
    }

    /** Unité de prix en toutes lettres : « par heure ». */
    public function par(): string
    {
        return match ($this) {
            self::Heure => 'par heure',
            self::DemiJournee => 'par demi-journée',
            self::Journee => 'par jour',
        };
    }

    /** Bornes du filtre « Budget maximum » de la recherche : minimum, maximum, pas. */
    public function budget(bool $salle = false): array
    {
        // Salles de fêtes et de conférence : bien plus chères qu'un logement
        if ($salle) {
            return match ($this) {
                self::Heure => [10000, 150000, 5000],
                self::DemiJournee => [30000, 500000, 10000],
                self::Journee => [50000, 1500000, 25000],
            };
        }

        return match ($this) {
            self::Heure => [2000, 10000, 500],
            self::DemiJournee => [5000, 30000, 1000],
            self::Journee => [10000, 80000, 2000],
        };
    }
}
