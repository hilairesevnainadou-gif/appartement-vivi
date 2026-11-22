<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Ligne du solde d'un propriétaire. */
enum TypeMouvement: string implements HasColor, HasLabel
{
    case CreditSejour = 'credit_sejour';
    case CreditAnnulation = 'credit_annulation';
    case CreditMarketing = 'credit_marketing';
    case DebitRetrait = 'debit_retrait';
    case Ajustement = 'ajustement';

    public function getLabel(): string
    {
        return match ($this) {
            self::CreditSejour => 'Séjour démarré',
            self::CreditAnnulation => 'Annulation tardive (montant retenu)',
            self::CreditMarketing => 'Commission d’agent marketing',
            self::DebitRetrait => 'Retrait',
            self::Ajustement => 'Ajustement',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::CreditSejour, self::CreditAnnulation, self::CreditMarketing => 'success',
            self::DebitRetrait => 'danger',
            self::Ajustement => 'gray',
        };
    }
}
