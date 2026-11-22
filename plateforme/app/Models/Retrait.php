<?php

namespace App\Models;

use App\Enums\StatutRetrait;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Demande de retrait d'un propriétaire. La caissière ou l'administrateur la valide et indique le mode de règlement. */
class Retrait extends Model
{
    use Journalise;

    protected $table = 'retraits';

    /** @var list<string> */
    protected $fillable = [
        'reference',
        'proprietaire_id',
        'montant',
        'compte_moyen',
        'compte_numero',
        'compte_titulaire',
        'statut',
        'mode_reglement',
        'reference_reglement',
        'traite_par',
        'traite_le',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'statut' => StatutRetrait::class,
            'traite_le' => 'datetime',
            'montant' => 'integer',
        ];
    }

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proprietaire_id');
    }

    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }
}
