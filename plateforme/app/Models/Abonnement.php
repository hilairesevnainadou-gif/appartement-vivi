<?php

namespace App\Models;

use App\Enums\StatutAbonnement;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Abonnement mensuel d'un propriétaire. */
class Abonnement extends Model
{
    use Journalise;

    protected $table = 'abonnements';

    /** @var list<string> */
    protected $fillable = [
        'proprietaire_id',
        'formule_abonnement_id',
        'debut',
        'fin',
        'prix_paye',
        'statut',
        'essai',
        'montant_remise',
        'libelle_promo',
    ];

    protected function casts(): array
    {
        return [
            'debut' => 'date',
            'fin' => 'date',
            'statut' => StatutAbonnement::class,
            'prix_paye' => 'integer',
            'essai' => 'boolean',
            'montant_remise' => 'integer',
        ];
    }

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proprietaire_id');
    }

    public function formule(): BelongsTo
    {
        return $this->belongsTo(FormuleAbonnement::class, 'formule_abonnement_id');
    }

    public function paiements(): MorphMany
    {
        return $this->morphMany(Paiement::class, 'payable');
    }
}
