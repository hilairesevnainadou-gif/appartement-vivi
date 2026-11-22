<?php

namespace App\Models;

use App\Enums\Formule;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Prix et horaires d'une formule (heure, demi-journée, journée) pour un logement. */
class Tarif extends Model
{
    use Journalise;

    protected $table = 'tarifs';

    /** @var list<string> */
    protected $fillable = [
        'logement_id',
        'formule',
        'prix',
        'actif',
        'heure_ouverture',
        'heure_fermeture',
        'duree_minimum_heures',
        'heure_arrivee',
        'heure_depart',
    ];

    protected function casts(): array
    {
        return [
            'formule' => Formule::class,
            'actif' => 'boolean',
            'prix' => 'integer',
        ];
    }

    public function logement(): BelongsTo
    {
        return $this->belongsTo(Logement::class);
    }

    /** À l'heure, ouverture égale à fermeture = ouvert 24 h/24 : on arrive à toute heure et on peut passer minuit. */
    public function est24h(): bool
    {
        return $this->formule === Formule::Heure && $this->heure_ouverture
            && substr($this->heure_ouverture, 0, 5) === substr((string) $this->heure_fermeture, 0, 5);
    }

    /** Horaires de la formule « heure » : « Ouvert 24 h/24 » ou « De 08:00 à 22:00 ». */
    public function horairesEnClair(): string
    {
        if ($this->est24h()) {
            return 'Ouvert 24 h/24';
        }
        [$de, $a] = [substr((string) $this->heure_ouverture, 0, 5), substr((string) $this->heure_fermeture, 0, 5)];

        return "De {$de} à {$a}".($a <= $de && $a !== '00:00' ? ' (après minuit)' : '');
    }
}
