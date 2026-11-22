<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Créneau de demi-journée nommé par le propriétaire (ex. Matin 08:00–13:00). */
class Creneau extends Model
{
    protected $table = 'creneaux';

    /** @var list<string> */
    protected $fillable = [
        'logement_id',
        'nom',
        'heure_debut',
        'heure_fin',
        'ordre',
    ];

    public function logement(): BelongsTo
    {
        return $this->belongsTo(Logement::class);
    }

    /** Le créneau se termine après minuit (ex. 20:00–01:00). */
    public function finitLendemain(): bool
    {
        return $this->heure_fin <= $this->heure_debut;
    }
}
