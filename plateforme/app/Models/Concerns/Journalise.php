<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Journal d'activité (spatie/laravel-activitylog) : création, modification et suppression du modèle,
 * avec l'auteur et seulement les champs qui changent. Consultable dans Administration › Journal d'activité.
 *
 * Dans le modèle : use Journalise; et, si besoin, protected array $journalExclus = ['champ_technique'];
 */
trait Journalise
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('donnees')
            ->logFillable()
            ->logExcept(array_merge(['password', 'remember_token', 'updated_at'], $this->journalExclus ?? []))
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $evenement) => match ($evenement) {
                'created' => 'Création',
                'updated' => 'Modification',
                'deleted' => 'Suppression',
                'restored' => 'Restauration',
                default => ucfirst($evenement),
            });
    }
}
