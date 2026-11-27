<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\StatutAbonnement;
use App\Models\Concerns\Journalise;
use App\Support\Permissions;
use App\Support\Telephone;
use Database\Factories\UserFactory;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use Journalise;

    /** Champs techniques non suivis par le journal d'activité. */
    protected array $journalExclus = ['code_parrainage', 'code_agent', 'telephone_verified_at', 'email_verified_at'];

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'telephone',
        'email',
        'password',
        'actif',
        'recevoir_offres',
        'whatsapp',
        'modele_economique',
        'commission_retrait_taux',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'telephone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
            'recevoir_offres' => 'boolean',
            'parrainage_recompense_le' => 'datetime',
            'retrait_modifie_le' => 'datetime',
            'commission_retrait_taux' => 'float',   // taux personnalisé (App\Services\ModeleEconomique)
            'supprime_le' => 'datetime',   // compte supprimé par son titulaire (App\Services\SuppressionCompte)
        ];
    }

    /** Notifications de l'espace « Notifications » du site (celles de l'administration ont le format « filament »). */
    public function notificationsSite(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->notifications()->where('data->format', 'vivi');
    }

    /** Téléphones connectés à l'application (jetons Firebase des notifications push). */
    public function appareils(): HasMany
    {
        return $this->hasMany(Appareil::class);
    }

    /* ---------- Parrainage (App\Services\Parrainage) ---------- */

    /** Personne qui a invité ce compte avec son code. */
    public function parrain(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parrain_id');
    }

    /** Comptes créés avec le code de cette personne. */
    public function filleuls(): HasMany
    {
        return $this->hasMany(self::class, 'parrain_id');
    }

    public function bonus(): HasMany
    {
        return $this->hasMany(Bonus::class);
    }

    /** Seule l'équipe VIVI (administrateurs, caissières) accède à /admin. */
    public function canAccessPanel(Panel $panel): bool
    {
        // Équipe VIVI, ou tout rôle créé par l'administrateur avec la permission d'accès
        return $this->actif && ($this->hasAnyRole(Role::equipeVivi()) || $this->can(Permissions::ACCES_ADMINISTRATION));
    }

    /** Numéro utilisé par le canal SMS des notifications (format international). */
    public function routeNotificationForSms(): ?string
    {
        return $this->telephone ? Telephone::international($this->telephone) : null;
    }

    /** « Géraud Kiki » → « GK », pour les pastilles de profil. */
    public function initiales(): string
    {
        return collect(preg_split('/\s+/', trim((string) $this->name)))->filter()->take(2)
            ->map(fn (string $mot) => mb_strtoupper(mb_substr($mot, 0, 1)))->implode('') ?: 'V';
    }

    public function prenom(): string
    {
        if ($this->name === \App\Services\SuppressionCompte::NOM) {
            return 'Ancien client';
        }

        return Str::before(trim((string) $this->name), ' ') ?: (string) $this->name;
    }

    /** Rôle principal affiché sous le nom. */
    public function libelleRole(): string
    {
        foreach ([Role::Administrateur, Role::Caissiere, Role::Proprietaire, Role::Gestionnaire, Role::AgentMarketing] as $role) {
            if ($this->hasRole($role->value)) {
                return $role->libelle();
            }
        }

        return Role::Client->libelle();
    }

    public function estProprietaireOuGestionnaire(): bool
    {
        return $this->hasAnyRole([Role::Proprietaire->value, Role::Gestionnaire->value]);
    }

    /** Espace où envoyer la personne après sa connexion. */
    public function urlEspace(): string
    {
        return match (true) {
            $this->canAccessPanel(Filament::getPanel('admin')) => '/admin',
            $this->hasAnyRole([Role::Proprietaire->value, Role::Gestionnaire->value]) => route('espace.proprietaire'),
            $this->hasRole(Role::AgentMarketing->value) => route('agent'),
            default => route('espace.client'),
        };
    }

    /* ---------- Propriétaire ---------- */

    public function logements(): HasMany
    {
        return $this->hasMany(Logement::class, 'proprietaire_id');
    }

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class, 'proprietaire_id');
    }

    /**
     * Abonnement de la période en cours. Les conditions sont dans ofMany() : sinon Laravel prend d'abord la
     * date de fin la plus tardive parmi tous les abonnements (même résiliés) et ne trouve plus rien.
     * Un renouvellement déjà payé, qui commence plus tard, n'est pas « l'abonnement actif ».
     */
    public function abonnementActif(): HasOne
    {
        return $this->hasOne(Abonnement::class, 'proprietaire_id')->ofMany(['fin' => 'max'], fn ($q) => $q
            ->where('statut', StatutAbonnement::Actif)
            ->whereDate('debut', '<=', today())
            ->whereDate('fin', '>=', today()));
    }

    public function mouvementsSolde(): HasMany
    {
        return $this->hasMany(MouvementSolde::class, 'proprietaire_id');
    }

    public function retraits(): HasMany
    {
        return $this->hasMany(Retrait::class, 'proprietaire_id');
    }

    /** Solde disponible : somme des crédits (séjours démarrés) et des débits (retraits payés). */
    public function solde(): int
    {
        return (int) $this->mouvementsSolde()->sum('montant');
    }

    /* ---------- Gestionnaire ---------- */

    public function logementsDelegues(): BelongsToMany
    {
        return $this->belongsToMany(Logement::class, 'delegations', 'gestionnaire_id', 'logement_id')->withTimestamps();
    }

    /* ---------- Agent marketing ---------- */

    /** Logements dont cette personne fait la promotion (lien d'affiliation). */
    public function logementsAgent(): BelongsToMany
    {
        return $this->belongsToMany(Logement::class, 'logement_agent', 'agent_id', 'logement_id')->withTimestamps();
    }

    /** Réservations faites par le lien d'affiliation de cet agent. */
    public function reservationsAgent(): HasMany
    {
        return $this->hasMany(Reservation::class, 'agent_id');
    }

    public function estAgent(): bool
    {
        return $this->hasRole(Role::AgentMarketing->value);
    }

    /* ---------- Client ---------- */

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'client_id');
    }
}
