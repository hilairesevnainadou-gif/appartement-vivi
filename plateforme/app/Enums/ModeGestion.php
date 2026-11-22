<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Qui accueille les clients d'un logement ou d'une salle :
 *  - le propriétaire (lui-même ou ses gestionnaires délégués) ;
 *  - VIVI : le propriétaire nous confie la gestion, l'administration désigne un gérant VIVI qui accueille et scanne.
 */
enum ModeGestion: string implements HasColor, HasLabel
{
    case Proprietaire = 'proprietaire';
    case Vivi = 'vivi';

    public function getLabel(): string
    {
        return match ($this) {
            self::Proprietaire => 'Géré par le propriétaire',
            self::Vivi => 'Géré par VIVI',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Proprietaire => 'gray',
            self::Vivi => 'primary',
        };
    }

    /** Texte du choix dans la fiche du propriétaire. */
    public function choix(): string
    {
        return match ($this) {
            self::Proprietaire => 'Je gère moi-même',
            self::Vivi => 'Je confie la gestion à VIVI',
        };
    }

    public function aide(): string
    {
        return match ($this) {
            self::Proprietaire => 'Vous (ou vos gestionnaires) accueillez les clients et scannez leur QR Code.',
            self::Vivi => 'Un gérant VIVI accueille vos clients, scanne les QR Codes et veille à l’état des lieux. Vous suivez tout depuis votre espace.',
        };
    }
}
