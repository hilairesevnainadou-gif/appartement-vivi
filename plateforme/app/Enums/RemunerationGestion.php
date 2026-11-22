<?php

namespace App\Enums;

use App\Support\Montant;
use Filament\Support\Contracts\HasLabel;

/**
 * Ce que VIVI retient quand le propriétaire lui confie la gestion d'un logement ou d'une salle.
 * Fixé par l'administration selon le contrat signé avec le propriétaire ; retiré de ce qui lui revient.
 */
enum RemunerationGestion: string implements HasLabel
{
    case Pourcentage = 'pourcentage';   // pourcentage du prix de la location
    case Fixe = 'fixe';                 // prix fixe par réservation, d'un jour ou d'une période
    case FixeJour = 'fixe_jour';        // prix fixe par jour réservé : une période de 3 jours le compte 3 fois

    public function getLabel(): string
    {
        return match ($this) {
            self::Pourcentage => 'Pourcentage de la location',
            self::Fixe => 'Prix fixe par réservation (un jour ou une période)',
            self::FixeJour => 'Prix fixe par jour réservé',
        };
    }

    public function estFixe(): bool
    {
        return $this !== self::Pourcentage;
    }

    /**
     * Montant retenu pour une location donnée, sans dépasser ce qui revient au propriétaire ($disponible).
     * $jours : jours réservés ; une réservation à l'heure ou à la demi-journée compte pour un jour.
     */
    public function montant(float $valeur, int $location, int $disponible, int $jours = 1): int
    {
        $montant = match ($this) {
            self::Pourcentage => (int) round($location * min(100, max(0, $valeur)) / 100),
            self::Fixe => (int) round(max(0, $valeur)),
            self::FixeJour => (int) round(max(0, $valeur)) * max(1, $jours),
        };

        return max(0, min($disponible, $montant));
    }

    /** « 15 % de la location », « 10 000 FCFA par réservation ». */
    public function libelleValeur(float $valeur): string
    {
        return match ($this) {
            self::Pourcentage => rtrim(rtrim(number_format($valeur, 2, ',', ' '), '0'), ',').' % de la location',
            self::Fixe => Montant::fcfa((int) round($valeur)).' par réservation',
            self::FixeJour => Montant::fcfa((int) round($valeur)).' par jour réservé',
        };
    }
}
