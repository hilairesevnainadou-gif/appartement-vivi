<?php

namespace App\Models;

use App\Enums\StatutAvis;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Avis d'un client sur un séjour terminé, modéré par VIVI. */
class Avis extends Model
{
    use Journalise;

    protected $table = 'avis';

    /** @var list<string> */
    protected $fillable = [
        'reservation_id',
        'client_id',
        'logement_id',
        'note',
        'commentaire',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'statut' => StatutAvis::class,
            'note' => 'integer',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function logement(): BelongsTo
    {
        return $this->belongsTo(Logement::class)->withTrashed();
    }
}
