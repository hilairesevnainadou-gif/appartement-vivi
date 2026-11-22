<?php

namespace App\Models;

use App\Enums\Formule;
use App\Enums\StatutAbonnement;
use App\Enums\StatutAvis;
use App\Enums\StatutLogement;
use App\Enums\TypeLogement;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/** Logement meublé proposé à la location courte durée. */
class Logement extends Model
{
    use Journalise;
    use SoftDeletes;

    protected $table = 'logements';

    /** @var list<string> */
    protected $fillable = [
        'proprietaire_id',
        'titre',
        'slug',
        'type',
        'ville',
        'quartier',
        'adresse',
        'latitude',
        'longitude',
        'description',
        'capacite',
        'chambres',
        'unites',
        'lits',
        'salles_de_bain',
        'temps_preparation_minutes',
        'marketing_type',
        'marketing_valeur',
        'statut',
        'en_ligne',
        'mode_gestion',              // App\Enums\ModeGestion : le propriétaire accueille, ou VIVI
        'gerant_vivi_id',
        'gestion_vivi_demandee_le',
        'gestion_type',              // App\Enums\RemunerationGestion : selon le contrat avec le propriétaire (administration)
        'gestion_valeur',
        'gestion_contrat',
        'gestion_contrat_le',
        'motif_refus',
        'valide_le',
        'valide_par',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeLogement::class,
            'statut' => StatutLogement::class,
            'en_ligne' => 'boolean',
            'mode_gestion' => \App\Enums\ModeGestion::class,
            'gestion_vivi_demandee_le' => 'datetime',
            'gestion_type' => \App\Enums\RemunerationGestion::class,
            'gestion_valeur' => 'float',
            'gestion_contrat_le' => 'date',
            'marketing_type' => \App\Enums\TypeMarketing::class,
            'marketing_valeur' => 'float',
            'valide_le' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /** Gérant VIVI désigné par l'administration quand le propriétaire confie la gestion à VIVI (accueil, scan). */
    public function gerantVivi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gerant_vivi_id');
    }

    public function estGereParVivi(): bool
    {
        return $this->mode_gestion === \App\Enums\ModeGestion::Vivi;
    }

    /** Ce que VIVI retient sur une location pour la gestion confiée : 0 si le propriétaire gère, ou sans contrat. */
    public function partGestion(int $location, int $disponible, int $jours = 1): int
    {
        return $this->estGereParVivi() ? ($this->gestion_type?->montant((float) $this->gestion_valeur, $location, $disponible, $jours) ?? 0) : 0;
    }

    /** « 15 % de la location », « 10 000 FCFA par réservation » ou « par jour réservé », ou null tant que le contrat n'est pas fixé. */
    public function libelleGestion(): ?string
    {
        return $this->estGereParVivi() && $this->gestion_type ? $this->gestion_type->libelleValeur((float) $this->gestion_valeur) : null;
    }

    /** Personnes qui accueillent : propriétaire, gestionnaires délégués et, si la gestion est confiée à VIVI, le gérant VIVI. */
    public function equipeAccueil(): \Illuminate\Support\Collection
    {
        $this->loadMissing(['proprietaire', 'gestionnaires', 'gerantVivi']);

        return collect([$this->proprietaire])->merge($this->gestionnaires)
            ->push($this->estGereParVivi() ? $this->gerantVivi : null)
            ->filter()->unique('id')->values();
    }

    /** « logement » ou « salle » (quota de l'abonnement, recherche). */
    public function categorie(): string
    {
        return $this->type?->categorie() ?? 'logement';
    }

    /** Salle de fêtes ou de conférence (et non un logement) : capacité en personnes, ni chambres ni lits. */
    public function estSalle(): bool
    {
        return (bool) $this->type?->estSalle();
    }

    /** « 3 voyageurs » pour un logement, « 150 places » pour une salle (capacité par défaut). */
    public function personnes(?int $n = null): string
    {
        return Reservation::pluriel($n ?? (int) $this->capacite, $this->type?->personne() ?? 'voyageur');
    }

    /**
     * Mot qui désigne l'annonce dans les textes : « logement », « salle », ou « espace » (plein air, espace complet).
     * Avec article : mot('le') → « la salle », mot('ce') → « cet espace », mot('du') → « de la salle ».
     */
    public function mot(string $article = ''): string
    {
        $mot = match (true) {
            ! $this->estSalle() => 'logement',
            in_array($this->type, [TypeLogement::EspacePleinAir, TypeLogement::EspaceComplet], true) => 'espace',
            default => 'salle',
        };
        $formes = [
            'logement' => ['le' => 'le ', 'ce' => 'ce ', 'du' => 'du '],
            'salle' => ['le' => 'la ', 'ce' => 'cette ', 'du' => 'de la '],
            'espace' => ['le' => 'l’', 'ce' => 'cet ', 'du' => 'de l’'],
        ];

        return ($formes[$mot][$article] ?? '').$mot;
    }

    /** Règle de validation du nombre de voyageurs : obligatoire pour un logement, sans objet pour une salle. */
    public function regleVoyageurs(): array
    {
        return [$this->estSalle() ? 'nullable' : 'required', 'integer', 'min:1', 'max:'.TypeLogement::CAPACITE_MAX_SALLE];
    }

    /** Ligne des cartes : « 2 voyageurs · 1 chambre · 1 lit », ou « Salle de fêtes · 300 places ». */
    public function resume(): string
    {
        return $this->estSalle()
            ? $this->type->getLabel().' · '.$this->personnes()
            : $this->personnes().' · '.Reservation::pluriel((int) $this->chambres, 'chambre').' · '.Reservation::pluriel((int) $this->lits, 'lit');
    }

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proprietaire_id');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function equipements(): BelongsToMany
    {
        return $this->belongsToMany(Equipement::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(LogementPhoto::class)->orderBy('ordre');
    }

    public function tarifs(): HasMany
    {
        return $this->hasMany(Tarif::class);
    }

    public function creneaux(): HasMany
    {
        return $this->hasMany(Creneau::class)->orderBy('ordre');
    }

    public function blocages(): HasMany
    {
        return $this->hasMany(Blocage::class);
    }

    /**
     * Centre de la zone affichée aux clients avant le paiement : décalé d'environ 150 m dans une direction
     * propre à chaque logement (toujours la même), pour que le cercle de 400 m contienne le logement sans le désigner.
     *
     * @return array{0: float, 1: float}|null
     */
    public function positionApprochee(): ?array
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }
        $angle = deg2rad(((int) $this->getKey() * 137) % 360);
        $decalage = 0.00135;   // ≈ 150 m

        return [round((float) $this->latitude + $decalage * sin($angle), 5), round((float) $this->longitude + $decalage * cos($angle), 5)];
    }

    /** Adresse complète, envoyée au client après le paiement. */
    public function adresseComplete(): string
    {
        return collect([$this->adresse, $this->quartier, $this->ville])->filter()->unique()->implode(', ');
    }

    /** Lien d'itinéraire (Google Maps) jusqu'au logement : position exacte si elle est connue, sinon l'adresse. */
    public function lienItineraire(): string
    {
        $destination = $this->latitude !== null && $this->longitude !== null
            ? $this->latitude.','.$this->longitude
            : $this->adresseComplete().', Bénin';

        return 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($destination);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)->withPivot('beneficiaire');
    }

    public function gestionnaires(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'delegations', 'logement_id', 'gestionnaire_id')->withTimestamps();
    }

    /** Agents marketing qui font la promotion de ce logement (choisis par le propriétaire). */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'logement_agent', 'logement_id', 'agent_id')->withTimestamps();
    }

    /** Part de la location qui revient à l'agent marketing (à VIVI sans agent) : 0 si le propriétaire n'en a pas fixé. */
    public function partMarketing(int $location): int
    {
        return $this->marketing_type?->montant((float) $this->marketing_valeur, $location) ?? 0;
    }

    /** « 10 % de la location », ou null si aucune part n'est prévue. */
    public function libelleMarketing(): ?string
    {
        return $this->marketing_type && $this->marketing_valeur > 0 ? $this->marketing_type->libelleValeur((float) $this->marketing_valeur) : null;
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function avis(): HasMany
    {
        return $this->hasMany(Avis::class);
    }

    /** Logements visibles par les clients : publiés et non mis en pause. */
    /** Logements visibles par les clients : publiés, en ligne, et dont le propriétaire a un abonnement en cours. */
    public function scopeVisibles(Builder $query): Builder
    {
        return $query->where('statut', StatutLogement::Publie)->where('en_ligne', true)
            ->whereHas('proprietaire.abonnements', fn (Builder $a) => $a->where('statut', StatutAbonnement::Actif)
                ->whereDate('debut', '<=', today())->whereDate('fin', '>=', today()));
    }

    public function estVisible(): bool
    {
        return $this->statut === StatutLogement::Publie && $this->en_ligne && ! $this->trashed()
            && $this->proprietaire()->first()?->abonnements()->where('statut', StatutAbonnement::Actif)
                ->whereDate('debut', '<=', today())->whereDate('fin', '>=', today())->exists();
    }

    public function tarif(Formule $formule): ?Tarif
    {
        return $this->tarifs->first(fn (Tarif $t) => $t->formule === $formule && $t->actif);
    }

    /* ---------- Affichage côté client ---------- */

    /** Note moyenne et nombre d'avis publiés (colonnes note_moyenne et nombre_avis). */
    public function scopeAvecNote(Builder $query): Builder
    {
        $publies = fn (Builder $q) => $q->where('statut', StatutAvis::Publie);

        return $query->withAvg(['avis as note_moyenne' => $publies], 'note')
            ->withCount(['avis as nombre_avis' => $publies]);
    }

    /** @return list<Formule> formules proposées, dans l'ordre heure, demi-journée, journée */
    public function formulesProposees(): array
    {
        return array_values(array_filter(Formule::cases(), fn (Formule $f) => $this->tarif($f) !== null));
    }

    /** Formule affichée par défaut (« à partir de ») : la première proposée. */
    public function formuleParDefaut(): ?Formule
    {
        return $this->formulesProposees()[0] ?? null;
    }

    public function urlPhoto(int $rang = 0): ?string
    {
        $photo = $this->photos->sortBy('ordre')->values()->get($rang);

        return $photo ? Storage::disk('public')->url($photo->chemin) : null;
    }

    /** « 4,8 » ou null si aucun avis publié. */
    public function noteAffichee(): ?string
    {
        return $this->nombre_avis ? number_format((float) $this->note_moyenne, 1, ',', '') : null;
    }
}
