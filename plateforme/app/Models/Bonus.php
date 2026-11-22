<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bonus en FCFA d'un utilisateur, déductible d'une réservation jusqu'à sa date d'expiration.
 * « restant » diminue quand le bonus est utilisé, et remonte si la réservation est abandonnée ou annulée.
 */
class Bonus extends Model
{
    use Journalise;

    protected $table = 'bonus';

    public const MOTIF_PARRAINAGE = 'parrainage';   // le parrain : un filleul a fait son premier séjour

    public const MOTIF_BIENVENUE = 'bienvenue';     // le filleul : compte créé avec un code d'invitation

    public const MOTIF_GESTE = 'geste';             // ajouté par l'équipe VIVI

    /** @var list<string> */
    protected $fillable = ['user_id', 'motif', 'libelle', 'montant', 'restant', 'expire_le', 'filleul_id', 'cree_par'];

    protected function casts(): array
    {
        return ['montant' => 'integer', 'restant' => 'integer', 'expire_le' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function filleul(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filleul_id');
    }

    /** Bonus encore utilisables aujourd'hui. */
    public function scopeUtilisables(Builder $query): void
    {
        $query->where('restant', '>', 0)->whereDate('expire_le', '>=', today());
    }

    public static function libelleMotif(string $motif): string
    {
        return match ($motif) {
            self::MOTIF_PARRAINAGE => 'Parrainage',
            self::MOTIF_BIENVENUE => 'Bienvenue',
            default => 'Geste VIVI',
        };
    }
}
