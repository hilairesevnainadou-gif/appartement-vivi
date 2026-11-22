<?php

namespace App\Models;

use App\Enums\EmplacementPub;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Publicité affichée dans un espace de l'application Android (Administration › Publicités). */
class Publicite extends Model
{
    use Journalise;

    /** @var list<string> */
    protected $fillable = ['titre', 'annonceur', 'annonceur_contact', 'texte', 'bouton', 'lien', 'image', 'emplacement', 'villes', 'debut', 'fin', 'ordre', 'prix', 'actif'];

    protected function casts(): array
    {
        return [
            'emplacement' => EmplacementPub::class,
            'villes' => 'array',
            'debut' => 'date',
            'fin' => 'date',
            'ordre' => 'integer',
            'prix' => 'integer',
            'actif' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // L'ancienne image est effacée quand elle est remplacée ou quand la publicité est supprimée
        static::updated(function (self $p) {
            $avant = $p->getOriginal('image');
            if ($avant && $p->wasChanged('image')) {
                Storage::disk('public')->delete($avant);
            }
        });
        static::deleted(fn (self $p) => $p->image ? Storage::disk('public')->delete($p->image) : null);
    }

    /** Vues et clics, une ligne par jour. */
    public function jours(): HasMany
    {
        return $this->hasMany(PubliciteJour::class);
    }

    /** Règlements reçus de l'annonceur. */
    public function paiements(): HasMany
    {
        return $this->hasMany(PublicitePaiement::class);
    }

    public function montantPaye(): int
    {
        // Somme déjà calculée par la liste (withSum), sinon en base
        return (int) (array_key_exists('paiements_sum_montant', $this->attributes)
            ? $this->attributes['paiements_sum_montant'] : $this->paiements()->sum('montant'));
    }

    public function resteAPayer(): int
    {
        return max(0, (int) $this->prix - $this->montantPaye());
    }

    /** gratuite, a_payer, partiel ou payee. */
    public function etatReglement(): string
    {
        return match (true) {
            (int) $this->prix === 0 => 'gratuite',
            $this->resteAPayer() === 0 => 'payee',
            $this->montantPaye() > 0 => 'partiel',
            default => 'a_payer',
        };
    }

    public function etatReglementEnClair(): string
    {
        return ['gratuite' => 'Gratuite', 'payee' => 'Payée', 'partiel' => 'Partiel', 'a_payer' => 'À payer'][$this->etatReglement()];
    }

    /** Enregistre un règlement ; jamais plus que le reste à payer. */
    public function encaisser(int $montant, string $mode, ?string $reference, string|\DateTimeInterface $le, ?User $par): PublicitePaiement
    {
        return DB::transaction(function () use ($montant, $mode, $reference, $le, $par) {
            $p = self::lockForUpdate()->findOrFail($this->id);
            if ($montant < 1 || $montant > $p->resteAPayer()) {
                throw ValidationException::withMessages(['montant' => 'Le montant dépasse le reste à payer.']);
            }

            return $p->paiements()->create(['montant' => $montant, 'mode' => $mode, 'reference' => $reference, 'paye_le' => $le, 'encaisse_par' => $par?->id]);
        });
    }

    /** Publicités dont le prix n'est pas entièrement réglé. */
    public function scopeAEncaisser(Builder $q): void
    {
        $q->whereRaw('publicites.prix > (select coalesce(sum(montant), 0) from publicite_paiements where publicite_paiements.publicite_id = publicites.id)');
    }

    /** Publicités à afficher aujourd'hui. */
    public function scopeDiffusees(Builder $q): void
    {
        $q->where('actif', true)
            ->where(fn (Builder $q) => $q->whereNull('debut')->orWhereDate('debut', '<=', today()))
            ->where(fn (Builder $q) => $q->whereNull('fin')->orWhereDate('fin', '>=', today()));
    }

    public function estDiffusee(): bool
    {
        return $this->etat() === 'en_ligne';
    }

    /** en_ligne, programmee, terminee ou pause. */
    public function etat(): string
    {
        return match (true) {
            ! $this->actif => 'pause',
            $this->fin !== null && $this->fin->lt(today()) => 'terminee',
            $this->debut !== null && $this->debut->gt(today()) => 'programmee',
            default => 'en_ligne',
        };
    }

    public function etatEnClair(): string
    {
        return ['pause' => 'En pause', 'terminee' => 'Terminée', 'programmee' => 'Programmée', 'en_ligne' => 'En ligne'][$this->etat()];
    }

    /** Sans ville choisie : partout. Une ville choisie n'est montrée que si l'application donne cette ville. */
    public function concerneVille(?string $ville): bool
    {
        return empty($this->villes) || ($ville !== null && in_array(mb_strtolower($ville), array_map('mb_strtolower', $this->villes), true));
    }

    /** Ajoute une vue ou un clic au compteur du jour. */
    public function compter(string $colonne): void
    {
        if (! in_array($colonne, ['vues', 'clics'], true)) {
            return;
        }
        DB::table('publicite_jours')->upsert(
            [['publicite_id' => $this->id, 'jour' => today()->toDateString(), 'vues' => 0, 'clics' => 0, $colonne => 1]],
            ['publicite_id', 'jour'],
            [$colonne => DB::raw("publicite_jours.{$colonne} + 1")],
        );
    }
}
