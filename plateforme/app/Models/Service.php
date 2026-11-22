<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Service à la carte du catalogue VIVI ; son prix est fixé par VIVI. */
class Service extends Model
{
    use Journalise;

    protected $table = 'services';

    /** @var list<string> */
    protected $fillable = [
        'nom',
        'description',
        'prix',
        'unite',
        'icone',
        'actif',
        'ordre',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'prix' => 'integer',
        ];
    }

    public function logements(): BelongsToMany
    {
        return $this->belongsToMany(Logement::class);
    }
}
