<?php

namespace App\Models;

use App\Enums\TypeMouvement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mouvement du solde d'un propriétaire : crédit au démarrage d'un séjour, débit à chaque retrait payé. */
class MouvementSolde extends Model
{
    protected $table = 'mouvements_solde';

    /** @var list<string> */
    protected $fillable = [
        'proprietaire_id',
        'type',
        'montant',
        'reservation_id',
        'retrait_id',
        'libelle',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeMouvement::class,
            'montant' => 'integer',
        ];
    }

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proprietaire_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function retrait(): BelongsTo
    {
        return $this->belongsTo(Retrait::class);
    }
}
