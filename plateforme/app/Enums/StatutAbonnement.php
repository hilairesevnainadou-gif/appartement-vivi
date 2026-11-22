<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Abonnement d'un propriétaire. « En attente » : souscription créée, paiement pas encore reçu. */
enum StatutAbonnement: string implements HasColor, HasLabel
{
    case EnAttente = 'en_attente';
    case Actif = 'actif';
    case Expire = 'expire';
    case Resilie = 'resilie';

    public function getLabel(): string
    {
        return match ($this) {
            self::EnAttente => 'Paiement en attente',
            self::Actif => 'Actif',
            self::Expire => 'Expiré',
            self::Resilie => 'Remplacé ou résilié',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EnAttente => 'gray',
            self::Actif => 'success',
            self::Expire => 'warning',
            self::Resilie => 'gray',
        };
    }
}
