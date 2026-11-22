<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Cycle de vie d'un logement : le propriétaire le soumet, VIVI le valide. */
enum StatutLogement: string implements HasColor, HasLabel
{
    case Brouillon = 'brouillon';
    case EnValidation = 'en_validation';
    case Publie = 'publie';
    case Refuse = 'refuse';
    case Suspendu = 'suspendu';

    public function getLabel(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::EnValidation => 'En attente de validation',
            self::Publie => 'Publié',
            self::Refuse => 'Refusé',
            self::Suspendu => 'Suspendu',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Brouillon => 'gray',
            self::EnValidation => 'warning',
            self::Publie => 'success',
            self::Refuse => 'danger',
            self::Suspendu => 'danger',
        };
    }
}
