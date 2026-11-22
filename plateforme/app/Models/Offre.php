<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Offre ou annonce envoyée par VIVI (Administration › Offres) dans l'espace Notifications et en push. */
class Offre extends Model
{
    use Journalise;

    /** Cible => libellé. */
    public const CIBLES = [
        'clients' => 'Tous les clients',
        'agents' => 'Agents marketing',
        'proprietaires' => 'Propriétaires',
        'tous' => 'Tous les utilisateurs du site',
    ];

    /** @var list<string> */
    protected $fillable = ['titre', 'texte', 'lien', 'cible', 'envoyee_par', 'destinataires', 'envoyee_le'];

    protected function casts(): array
    {
        return ['envoyee_le' => 'datetime', 'destinataires' => 'integer'];
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'envoyee_par');
    }
}
