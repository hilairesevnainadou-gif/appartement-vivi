<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Alerte de surveillance : incident technique ou de sécurité, à traiter par l'équipe VIVI. */
class AlerteSysteme extends Model
{
    protected $table = 'alertes_systeme';

    public const INFO = 'info';

    public const AVERTISSEMENT = 'avertissement';

    public const CRITIQUE = 'critique';

    /** @var list<string> */
    protected $fillable = ['type', 'niveau', 'titre', 'message', 'source', 'details', 'occurrences', 'derniere_le', 'notifiee_le', 'resolue_le', 'resolue_par'];

    protected function casts(): array
    {
        return ['details' => 'array', 'derniere_le' => 'datetime', 'notifiee_le' => 'datetime', 'resolue_le' => 'datetime', 'occurrences' => 'integer'];
    }

    public function resoluePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolue_par');
    }

    public function scopeOuvertes(Builder $query): void
    {
        $query->whereNull('resolue_le');
    }

    public static function libelleNiveau(string $niveau): string
    {
        return match ($niveau) {
            self::CRITIQUE => 'Critique',
            self::AVERTISSEMENT => 'Avertissement',
            default => 'Information',
        };
    }
}
