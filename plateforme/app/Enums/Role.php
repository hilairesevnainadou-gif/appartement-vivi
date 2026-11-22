<?php

namespace App\Enums;

/**
 * Rôles de la phase 1 (décisions de démarrage du 10/11/2026).
 * Les rôles sont stockés par Spatie Permission : en ajouter un se fait ici
 * puis via le seeder, sans toucher au reste du code.
 */
enum Role: string
{
    case Client = 'client';
    case Proprietaire = 'proprietaire';
    case Gestionnaire = 'gestionnaire';
    case AgentMarketing = 'agent_marketing';
    case Caissiere = 'caissiere';
    case Administrateur = 'administrateur';
    case Technicien = 'technicien';   // accès à l'administration par sa permission : documentation, alertes, journal

    public function libelle(): string
    {
        return match ($this) {
            self::Technicien => 'Technicien',
            self::Client => 'Client',
            self::Proprietaire => 'Propriétaire',
            self::Gestionnaire => 'Gestionnaire de propriétaire',
            self::AgentMarketing => 'Agent marketing',
            self::Caissiere => 'Caissière',
            self::Administrateur => 'Administrateur',
        };
    }

    /** Rôles autorisés à entrer dans l'administration Filament (/admin). */
    public static function equipeVivi(): array
    {
        return [self::Administrateur->value, self::Caissiere->value];
    }
}
