<?php

namespace App\Models;

use App\Enums\StatutPaiement;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Paiement reçu par FedaPay, pour une réservation ou un abonnement. */
class Paiement extends Model
{
    use Journalise;

    protected $table = 'paiements';

    /** @var list<string> */
    protected $fillable = [
        'payable_type',
        'payable_id',
        'payeur_id',
        'passerelle',
        'reference_passerelle',
        'moyen',
        'montant',
        'statut',
        'donnees',
        'paye_le',
    ];

    protected function casts(): array
    {
        return [
            'statut' => StatutPaiement::class,
            'donnees' => 'array',
            'paye_le' => 'datetime',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function payeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payeur_id');
    }
}
