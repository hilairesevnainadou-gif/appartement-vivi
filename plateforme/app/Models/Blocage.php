<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Période pendant laquelle un logement n'est pas réservable. */
class Blocage extends Model
{
    use Journalise;

    protected $table = 'blocages';

    /** @var list<string> */
    protected $fillable = [
        'logement_id',
        'date_debut',
        'date_fin',
        'motif',
        'cree_par',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
        ];
    }

    public function logement(): BelongsTo
    {
        return $this->belongsTo(Logement::class);
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }
}
