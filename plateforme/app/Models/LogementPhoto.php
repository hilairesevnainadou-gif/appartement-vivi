<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Photo d'un logement ; ordre 0 = photo principale. */
class LogementPhoto extends Model
{
    protected $table = 'logement_photos';

    /** @var list<string> */
    protected $fillable = [
        'logement_id',
        'chemin',
        'legende',
        'ordre',
    ];

    public function logement(): BelongsTo
    {
        return $this->belongsTo(Logement::class);
    }
}
