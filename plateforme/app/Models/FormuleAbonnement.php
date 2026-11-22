<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Formule d'abonnement proposée aux propriétaires, paramétrée par l'administrateur. */
class FormuleAbonnement extends Model
{
    use Journalise;

    protected $table = 'formules_abonnement';

    /** @var list<string> */
    protected $fillable = [
        'nom',
        'prix_mensuel',
        'logements_max',
        'salles_max',          // salles de fêtes, de conférence… (quota séparé des logements)
        'gestionnaires_max',
        'avantages',
        'actif',
        'ordre',
    ];

    protected function casts(): array
    {
        return [
            'avantages' => 'array',
            'actif' => 'boolean',
            'prix_mensuel' => 'integer',
            'logements_max' => 'integer',
            'salles_max' => 'integer',
        ];
    }

    /** Quota de la catégorie : « logement » ou « salle ». */
    public function maxPour(string $categorie): int
    {
        return $categorie === 'salle' ? (int) $this->salles_max : (int) $this->logements_max;
    }

    /** « 2 logements et 1 salle » */
    public function quotaEnClair(): string
    {
        return Reservation::pluriel((int) $this->logements_max, 'logement').' et '.Reservation::pluriel((int) $this->salles_max, 'salle');
    }

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class);
    }
}
