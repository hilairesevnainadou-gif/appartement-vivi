<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Équipement d'un logement (climatisation, Wi-Fi…). */
class Equipement extends Model
{
    use Journalise;

    protected $table = 'equipements';

    /** @var list<string> */
    protected $fillable = [
        'nom',
        'icone',
        'ordre',
    ];

    public function logements(): BelongsToMany
    {
        return $this->belongsToMany(Logement::class);
    }
}
