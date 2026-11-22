<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Règlement d'un annonceur, saisi par l'équipe VIVI. */
class PublicitePaiement extends Model
{
    use Journalise;

    /** @var list<string> */
    protected $fillable = ['publicite_id', 'montant', 'mode', 'reference', 'paye_le', 'encaisse_par'];

    protected function casts(): array
    {
        return ['montant' => 'integer', 'paye_le' => 'date'];
    }

    public function publicite(): BelongsTo
    {
        return $this->belongsTo(Publicite::class);
    }

    public function caissier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encaisse_par');
    }
}
