<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login;
use Illuminate\Contracts\Support\Htmlable;

/** Connexion à l'administration : réservée à l'équipe VIVI (administrateurs, caissières). */
class Connexion extends Login
{
    public function getHeading(): string|Htmlable|null
    {
        return filled($this->userUndertakingMultiFactorAuthentication) ? parent::getHeading() : 'Administration';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return filled($this->userUndertakingMultiFactorAuthentication)
            ? parent::getSubheading()
            : 'Espace réservé à l’équipe VIVI : administrateurs et caissières.';
    }
}
