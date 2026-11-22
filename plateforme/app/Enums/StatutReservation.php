<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Cycle de vie d'une réservation. */
enum StatutReservation: string implements HasColor, HasLabel
{
    case EnAttentePaiement = 'en_attente_paiement';
    case Confirmee = 'confirmee';
    case EnCours = 'en_cours';
    case Terminee = 'terminee';
    case Consommee = 'consommee';
    case Annulee = 'annulee';

    public function getLabel(): string
    {
        return match ($this) {
            self::EnAttentePaiement => 'En attente de paiement',
            self::Confirmee => 'Confirmée',
            self::EnCours => 'Séjour en cours',
            self::Terminee => 'Terminée',
            self::Consommee => 'Consommée (client absent)',
            self::Annulee => 'Annulée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EnAttentePaiement => 'gray',
            self::Confirmee => 'primary',
            self::EnCours => 'success',
            self::Terminee => 'gray',
            self::Consommee => 'warning',
            self::Annulee => 'danger',
        };
    }
}
