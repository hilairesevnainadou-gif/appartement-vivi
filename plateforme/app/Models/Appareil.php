<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Téléphone connecté à l'application : son jeton Firebase reçoit les notifications push. */
class Appareil extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'jeton', 'plateforme', 'nom', 'vu_le'];

    protected function casts(): array
    {
        return ['vu_le' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
