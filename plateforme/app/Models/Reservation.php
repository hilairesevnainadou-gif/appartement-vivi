<?php

namespace App\Models;

use App\Enums\Formule;
use App\Enums\StatutReservation;
use App\Models\Concerns\Journalise;
use App\Support\Parametres;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Réservation courte durée. Montants et taux de commission figés à la réservation. */
class Reservation extends Model
{
    use Journalise;

    /** Champs techniques non suivis par le journal d'activité. */
    protected array $journalExclus = ['bloque_jusqua', 'rappel_envoye_le', 'code_secours', 'bonus_utilises'];

    protected $table = 'reservations';

    /** @var list<string> */
    protected $fillable = [
        'reference',
        'client_id',
        'whatsapp',
        'agent_id',
        'marketing_type',
        'marketing_valeur',
        'montant_marketing',
        'remise_agent',
        'gestion_type',       // rémunération de VIVI pour la gestion confiée, figée à la réservation
        'gestion_valeur',
        'montant_gestion',
        'logement_id',
        'formule',
        'creneau_id',
        'debut',
        'fin',
        'voyageurs',
        'statut',
        'bloque_jusqua',
        'montant_location',
        'montant_services',
        'montant_services_proprietaire',
        'origine',
        'cree_par',
        'note_interne',
        'montant_encaisse',
        'taux_commission',
        'montant_commission',
        'montant_total',
        'services_beneficiaire',
        'code_secours',
        'scanne_le',
        'scanne_par',
        'debut_initial',
        'nombre_reports',
        'deplacee_par',
        'annulee_le',
        'motif_annulation',
        'montant_rembourse',
        'rappel_envoye_le',
        'terminee_le',
        'rembourse_le',
        'rembourse_par',
        'mode_remboursement',
        'reference_remboursement',
        'montant_bonus',
        'bonus_utilises',
        'bonus_rendu',
    ];

    /** @var list<string> */
    protected $hidden = ['code_secours'];

    protected function casts(): array
    {
        return [
            'formule' => Formule::class,
            'statut' => StatutReservation::class,
            'debut' => 'datetime',
            'fin' => 'datetime',
            'bloque_jusqua' => 'datetime',
            'scanne_le' => 'datetime',
            'debut_initial' => 'datetime',
            'annulee_le' => 'datetime',
            'rappel_envoye_le' => 'datetime',
            'terminee_le' => 'datetime',
            'rembourse_le' => 'datetime',
            'taux_commission' => 'decimal:2',
            'marketing_type' => \App\Enums\TypeMarketing::class,
            'marketing_valeur' => 'float',
            'montant_marketing' => 'integer',
            'remise_agent' => 'integer',
            'gestion_type' => \App\Enums\RemunerationGestion::class,
            'gestion_valeur' => 'float',
            'montant_gestion' => 'integer',
            'montant_bonus' => 'integer',
            'bonus_utilises' => 'array',
            'bonus_rendu' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function logement(): BelongsTo
    {
        return $this->belongsTo(Logement::class)->withTrashed();
    }

    /** Agent marketing dont le lien d'affiliation a amené le client (null : la part marketing revient à VIVI). */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function creneau(): BelongsTo
    {
        return $this->belongsTo(Creneau::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(ReservationService::class);
    }

    public function paiements(): MorphMany
    {
        return $this->morphMany(Paiement::class, 'payable');
    }

    public function avis(): HasOne
    {
        return $this->hasOne(Avis::class);
    }

    public function scannePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanne_par');
    }

    public function deplaceePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deplacee_par');
    }

    /* ---------- Références et QR Code ---------- */

    /** Référence lisible et unique : VIVI-XXXXXX, sans lettres ambiguës (0/O, 1/I). */
    public static function nouvelleReference(): string
    {
        do {
            $reference = 'VIVI-'.collect(range(1, 6))->map(fn () => '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'[random_int(0, 31)])->implode('');
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Contenu du QR Code présenté à l'arrivée : la référence et une signature calculée avec la clé de
     * l'application. Impossible à fabriquer sans cette clé ; vérifié par le scanner du propriétaire.
     */
    public function jetonQr(): string
    {
        return 'VIVI1.'.$this->reference.'.'.self::signature($this->reference, (string) $this->code_secours);
    }

    /** Référence contenue dans un jeton valide, ou null. */
    public static function referenceDuJeton(string $jeton): ?string
    {
        if (! preg_match('/^VIVI1\.(VIVI-[A-Z0-9]{6})\.([a-f0-9]{20})$/', trim($jeton), $m)) {
            return null;
        }
        $code = self::where('reference', $m[1])->value('code_secours');

        return $code !== null && hash_equals(self::signature($m[1], (string) $code), $m[2]) ? $m[1] : null;
    }

    private static function signature(string $reference, string $code): string
    {
        return substr(hash_hmac('sha256', $reference.'|'.$code, (string) config('app.key')), 0, 20);
    }

    /* ---------- Affichage (espaces client et propriétaire) ---------- */

    /** Durée courte sous l'heure d'arrivée : « 3 h », « Matin », « 2 jours ». */
    public function dureeCourte(): string
    {
        return match ($this->formule) {
            Formule::Heure => max(1, (int) round($this->debut->diffInMinutes($this->fin) / 60)).' h',
            Formule::DemiJournee => $this->creneau?->nom ?? 'Demi-journée',
            Formule::Journee => self::pluriel($this->nombreJours(), 'jour'),
        };
    }

    /** Créneau en toutes lettres, comme dans la maquette. */
    public function creneauEnClair(): string
    {
        $de = $this->debut->format('H:i');
        $a = $this->fin->format('H:i');

        return match ($this->formule) {
            Formule::Heure => "De {$de} à {$a} (".$this->dureeCourte().')',
            Formule::DemiJournee => ($this->creneau?->nom ?? 'Demi-journée').", de {$de} à {$a}",
            Formule::Journee => self::journeeEnClair($this->debut, $this->fin, (bool) $this->loadMissing('logement')->logement?->estSalle()),
        };
    }

    /** Horaires d'une formule « journée » : arrivée et départ pour un logement ; début et fin, le même jour, pour une salle. */
    public static function journeeEnClair(\Carbon\CarbonInterface $debut, \Carbon\CarbonInterface $fin, bool $salle): string
    {
        [$de, $a] = [$debut->format('H:i'), $fin->format('H:i')];
        if (! $salle) {
            return "Arrivée à partir de {$de}, départ avant {$a} le ".$fin->translatedFormat('D j M');
        }
        if ($debut->diffInMinutes($fin) <= 1440) {
            return "Journée, de {$de} à {$a}".($fin->isSameDay($debut) ? '' : ' (après minuit)');
        }

        return 'Du '.$debut->translatedFormat('D j M')." à {$de} au ".$fin->translatedFormat('D j M')." à {$a}";
    }

    /** Libellé court de la formule : « heure », « demi-journée », « journée ». */
    public function formuleCourte(): string
    {
        return match ($this->formule) {
            Formule::Heure => 'heure',
            Formule::DemiJournee => 'demi-journée',
            Formule::Journee => 'journée',
        };
    }

    /**
     * Ce qui revient au propriétaire quand le séjour démarre : la location moins la part marketing (versée à
     * l'agent, ou à VIVI sans agent) et la rémunération de VIVI si la gestion lui est confiée, plus les services
     * qu'il assure lui-même.
     */
    public function creditProprietaire(): int
    {
        return (int) $this->montant_location - (int) $this->montant_marketing - (int) $this->montant_gestion + match ($this->services_beneficiaire) {
            'proprietaire' => (int) $this->montant_services,
            'mixte' => (int) $this->montant_services_proprietaire,   // seulement les services qu'il assure lui-même
            default => 0,                                             // VIVI
        };
    }

    /**
     * Où va la part marketing : à l'agent du lien d'affiliation ; déduite du prix si l'agent réserve pour
     * lui-même un logement qu'il promeut ; sinon à VIVI. Formules SQL pour les totaux de l'administration.
     */
    public const SQL_MARKETING_VIVI = 'CASE WHEN agent_id IS NULL THEN montant_marketing - remise_agent ELSE 0 END';   // remise ≤ part : jamais négatif

    public const SQL_MARKETING_AGENTS = 'CASE WHEN agent_id IS NOT NULL THEN montant_marketing ELSE 0 END';

    public function partMarketingVivi(): int
    {
        return $this->agent_id ? 0 : max(0, (int) $this->montant_marketing - (int) $this->remise_agent);
    }

    /** Part marketing selon la règle du logement figée à la réservation, pour une location donnée. */
    public function calculerPartMarketing(int $location): int
    {
        return $this->marketing_type?->montant((float) $this->marketing_valeur, $location) ?? 0;
    }

    /** Rémunération de gestion selon la règle figée à la réservation, pour une location et une part marketing données. */
    public function calculerPartGestion(int $location, int $partMarketing): int
    {
        return $this->gestion_type?->montant((float) $this->gestion_valeur, $location, $location - $partMarketing,
            $this->formule === Formule::Journee ? $this->nombreJours() : 1) ?? 0;
    }

    /** Montant réglé par le client via FedaPay : le total moins la part payée avec son bonus. */
    public function montantPaye(): int
    {
        return (int) $this->montant_total - (int) $this->montant_bonus;
    }

    /** « heure, 3 h », « demi-journée, matin », « journée, 2 jours » ; sans répéter « demi-journée » deux fois. */
    public function formuleEtDuree(): string
    {
        $duree = mb_strtolower($this->dureeCourte());

        return $duree === $this->formuleCourte() ? $duree : $this->formuleCourte().', '.$duree;
    }

    public function nombreJours(): int
    {
        // Salle : une journée finit le jour même (ou après minuit), soit 24 h au plus par jour réservé
        if ($this->formule === Formule::Journee && $this->loadMissing('logement')->logement?->estSalle()) {
            return max(1, (int) ceil($this->debut->diffInMinutes($this->fin) / 1440));
        }

        return max(1, (int) $this->debut->copy()->startOfDay()->diffInDays($this->fin->copy()->startOfDay()));
    }

    /** Le client peut encore reporter : réservation confirmée et arrivée dans plus de 3 h (paramétrable). */
    public function reportPossible(): bool
    {
        return $this->statut === StatutReservation::Confirmee && ! $this->estManuelle()
            && now()->diffInMinutes($this->debut, false) >= Parametres::get('report_delai_minimum_heures') * 60;
    }

    public const ORIGINE_MANUELLE = 'manuelle';

    /** Réservation hors plateforme : ce que le client doit encore régler sur place. */
    public function resteAPayer(): int
    {
        return $this->estManuelle() ? max(0, (int) $this->montant_total - (int) $this->montant_encaisse) : 0;
    }

    /** Saisie par le propriétaire ou le gestionnaire pour un client venu hors de la plateforme : ni paiement ni commission VIVI. */
    public function estManuelle(): bool
    {
        return $this->origine === self::ORIGINE_MANUELLE;
    }

    public static function pluriel(int $n, string $mot): string
    {
        return $n.' '.$mot.($n > 1 ? 's' : '');
    }

    /**
     * « 3 voyageurs » pour un logement. Null pour une salle : elle se réserve entière, sans nombre de personnes
     * (on regarde la salle seulement si le logement est déjà chargé).
     */
    public function personnes(): ?string
    {
        if ($this->relationLoaded('logement') && $this->logement?->estSalle()) {
            return null;
        }

        return self::pluriel((int) $this->voyageurs, 'voyageur');
    }

    /** « · 3 voyageurs » à ajouter après un texte, ou rien pour une salle. */
    public function suffixePersonnes(): string
    {
        return ($p = $this->personnes()) ? ' · '.$p : '';
    }

    /**
     * Réservations qui occupent le logement : confirmées, en cours, ou en attente
     * de paiement dont le créneau est encore retenu. Conditions groupées pour se
     * combiner sans risque avec d'autres filtres (logement, dates).
     */
    public function scopeOccupantes(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereIn('statut', [StatutReservation::Confirmee, StatutReservation::EnCours])
            ->orWhere(fn (Builder $q) => $q
                ->where('statut', StatutReservation::EnAttentePaiement)
                ->where('bloque_jusqua', '>', now())));
    }
}
