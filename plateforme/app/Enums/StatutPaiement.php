<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** État d'un paiement FedaPay. */
enum StatutPaiement: string implements HasColor, HasLabel
{
    case EnAttente = 'en_attente';
    case Reussi = 'reussi';
    case Echoue = 'echoue';
    case Rembourse = 'rembourse';

    public function getLabel(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::Reussi => 'Réussi',
            self::Echoue => 'Échoué',
            self::Rembourse => 'Remboursé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EnAttente => 'gray',
            self::Reussi => 'success',
            self::Echoue => 'danger',
            self::Rembourse => 'warning',
        };
    }
}
