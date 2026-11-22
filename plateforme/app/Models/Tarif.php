<?php

namespace App\Models;

use App\Enums\Formule;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Prix et horaires d'une formule (heure, demi-journée, journée) pour un logement. */
class Tarif extends Model
{
    use Journalise;

    protected $table = 'tarifs';

    /** @var list<string> */
    protected $fillable = [
        'logement_id',
        'formule',
        'prix',
        'actif',
        'heure_ouverture',
        'heure_fermeture',
        'duree_minimum_heures',
        'heure_arrivee',
        'heure_depart',
    ];

    protected function casts(): array
    {
        return [
            'formule' => Formule::class,
            'actif' => 'boolean',
            'prix' => 'integer',
        ];
    }

    public function logement(): BelongsTo
    {
        return $this->belongsTo(Logement::class);
    }
}
