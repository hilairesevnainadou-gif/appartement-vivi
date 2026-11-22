<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Avis laissé par un client, modéré par VIVI. */
enum StatutAvis: string implements HasColor, HasLabel
{
    case EnAttente = 'en_attente';
    case Publie = 'publie';
    case Masque = 'masque';

    public function getLabel(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente de modération',
            self::Publie => 'Publié',
            self::Masque => 'Masqué',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EnAttente => 'warning',
            self::Publie => 'success',
            self::Masque => 'gray',
        };
    }
}
