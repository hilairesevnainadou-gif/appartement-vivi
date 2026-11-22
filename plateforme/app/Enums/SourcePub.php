<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Ce qui s'affiche dans un espace publicitaire de l'application. */
enum SourcePub: string implements HasLabel
{
    case ViviPuisAdmob = 'vivi_puis_admob';
    case Vivi = 'vivi';
    case Admob = 'admob';
    case Aucune = 'aucune';

    public function getLabel(): string
    {
        return match ($this) {
            self::ViviPuisAdmob => 'Publicité VIVI, sinon AdMob',
            self::Vivi => 'Publicité VIVI seulement',
            self::Admob => 'AdMob seulement',
            self::Aucune => 'Rien',
        };
    }

    public function vivi(): bool
    {
        return in_array($this, [self::ViviPuisAdmob, self::Vivi], true);
    }

    public function admob(): bool
    {
        return in_array($this, [self::ViviPuisAdmob, self::Admob], true);
    }
}
