<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Demande de retrait d'un propriétaire, validée par la caissière ou l'administrateur. */
enum StatutRetrait: string implements HasColor, HasLabel
{
    case EnAttente = 'en_attente';
    case Paye = 'paye';
    case Refuse = 'refuse';

    public function getLabel(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente de validation',
            self::Paye => 'Payé',
            self::Refuse => 'Refusé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EnAttente => 'warning',
            self::Paye => 'success',
            self::Refuse => 'danger',
        };
    }
}
