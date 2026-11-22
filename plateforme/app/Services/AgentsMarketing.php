<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\StatutReservation;
use App\Enums\TypeMouvement;
use App\Models\Logement;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\AgentDesigne;
use App\Support\Telephone;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Agents marketing : ils font la promotion de logements par un lien d'affiliation.
 *
 *  - Le propriétaire choisit ses agents et les logements de chacun (un logement peut avoir plusieurs agents,
 *    un agent peut travailler pour plusieurs propriétaires). Un agent peut aussi être client.
 *  - Le propriétaire fixe, par logement, la part marketing : pourcentage de la location ou montant fixe.
 *    Elle est prise sur sa part, que le client vienne ou non par un agent.
 *  - Client venu par le lien d'un agent du logement : la part est créditée sur le solde de l'agent au démarrage
 *    du séjour (QR Code scanné, ou séjour consommé). Sans agent, elle reste à VIVI.
 *  - L'agent ne voit ni le calendrier ni les clients : seul le gestionnaire (ou le propriétaire) accueille et scanne.
 */
class AgentsMarketing
{
    /** Cookie qui retient, par logement, le code de l'agent dont le lien a été ouvert. */
    public const COOKIE = 'vivi_agent';

    public const COOKIE_JOURS = 30;

    /** Statuts où le séjour a eu lieu : la part de l'agent est acquise. */
    public const SEJOURS_EFFECTIFS = [StatutReservation::EnCours, StatutReservation::Terminee, StatutReservation::Consommee];

    /* ---------- Agents d'un propriétaire ---------- */

    /** Agents de ce propriétaire, avec ses logements qui leur sont confiés. */
    public function agentsDe(User $proprietaire): Collection
    {
        $ids = $proprietaire->logements()->pluck('id');

        return User::whereHas('logementsAgent', fn ($q) => $q->whereIn('logements.id', $ids))
            ->with(['logementsAgent' => fn ($q) => $q->whereIn('logements.id', $ids)->orderBy('titre')])
            ->orderBy('name')->get();
    }

    /**
     * Désigne (ou retrouve) la personne comme agent des logements choisis. Aucune limite de nombre.
     *
     * @param  list<int>  $logements
     *
     * @throws ReservationImpossible message pour le propriétaire
     */
    public function designer(User $proprietaire, string $nom, string $identifiant, array $logements): User
    {
        $identifiant = trim($identifiant);
        $email = filter_var($identifiant, FILTER_VALIDATE_EMAIL) ? mb_strtolower($identifiant) : null;
        $telephone = $email ? null : Telephone::normaliser($identifiant);
        if (! $email && ! $telephone) {
            throw new ReservationImpossible('Indiquez une adresse email ou un numéro béninois à 10 chiffres (01 97 00 00 00).');
        }
        $logements = $this->siens($proprietaire, $logements);
        if ($logements->isEmpty()) {
            throw new ReservationImpossible('Choisissez au moins un logement à promouvoir.');
        }

        $existant = User::where($email ? 'email' : 'telephone', $email ?? $telephone)->first();
        if ($existant?->is($proprietaire)) {
            throw new ReservationImpossible('Vous ne pouvez pas être votre propre agent marketing.');
        }
        if ($existant?->hasAnyRole(Role::equipeVivi())) {
            throw new ReservationImpossible('Ce compte appartient à l’équipe VIVI : il ne peut pas être agent marketing.');
        }

        $agent = DB::transaction(function () use ($existant, $nom, $email, $telephone, $logements) {
            $u = $existant ?? User::create(['name' => trim($nom), 'email' => $email, 'telephone' => $telephone, 'actif' => true]);
            if (! $existant) {
                $u->assignRole(Role::Client->value);   // un agent peut aussi réserver pour lui-même
            }
            $u->assignRole(Role::AgentMarketing->value);
            $u->logementsAgent()->syncWithoutDetaching($logements->modelKeys());
            $this->codeDe($u);

            return $u;
        });

        try {
            $agent->notify(new AgentDesigne($proprietaire, $logements));
        } catch (Throwable $e) {
            report($e);
        }

        return $agent;
    }

    /** Remplace les logements de ce propriétaire confiés à cet agent (aucun = il n'est plus son agent). */
    public function modifier(User $proprietaire, User $agent, array $logements): void
    {
        $tous = $proprietaire->logements()->pluck('id')->all();
        DB::transaction(function () use ($agent, $tous, $proprietaire, $logements) {
            $agent->logementsAgent()->detach($tous);
            $agent->logementsAgent()->attach($this->siens($proprietaire, $logements)->modelKeys());
            // Plus aucun logement à promouvoir : il n'est plus agent (son solde reste, il peut le retirer)
            if (! $agent->logementsAgent()->exists() && (int) $agent->mouvementsSolde()->sum('montant') <= 0) {
                $agent->removeRole(Role::AgentMarketing->value);
                if ($agent->roles()->doesntExist()) {
                    $agent->assignRole(Role::Client->value);
                }
            }
        });
    }

    /* ---------- Lien d'affiliation ---------- */

    /** Code personnel de l'agent, créé à la première demande : « AG » suivi de 6 caractères sans ambiguïté. */
    public function codeDe(User $agent): string
    {
        if ($agent->code_agent) {
            return $agent->code_agent;
        }
        do {
            $code = 'AG'.collect(range(1, 6))->map(fn () => '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'[random_int(0, 31)])->implode('');
        } while (User::where('code_agent', $code)->exists());
        $agent->forceFill(['code_agent' => $code])->save();

        return $code;
    }

    /** Lien de la fiche du logement à partager par l'agent. */
    public function lien(Logement $logement, User $agent): string
    {
        return route('logement', ['slug' => $logement->slug, 'agent' => $this->codeDe($agent)]);
    }

    /**
     * Agent à retenir pour une réservation : il doit être agent de ce logement, actif, et ne pas être le client
     * lui-même (un agent qui réserve pour lui ne se verse pas de commission : la part revient à VIVI).
     */
    public function agentPour(Logement $logement, ?string $code, User $client): ?User
    {
        $code = mb_strtoupper(trim((string) $code));
        if ($code === '' || ! preg_match('/^AG[A-Z0-9]{6}$/', $code)) {
            return null;
        }
        $agent = User::where('code_agent', $code)->where('actif', true)
            ->whereHas('logementsAgent', fn ($q) => $q->whereKey($logement->getKey()))->first();

        return $agent && ! $agent->is($client) && $agent->hasRole(Role::AgentMarketing->value) ? $agent : null;
    }

    /** Retient le code de l'agent pour ce logement (lien ouvert avant la connexion ou la réservation). */
    public function retenir(Request $request, Logement $logement, string $code): void
    {
        $code = mb_strtoupper(trim($code));
        if (! preg_match('/^AG[A-Z0-9]{6}$/', $code)) {
            return;
        }
        $codes = $this->codesRetenus($request);
        $codes[(string) $logement->getKey()] = $code;   // le dernier lien ouvert l'emporte
        Cookie::queue(self::COOKIE, json_encode(array_slice($codes, -20, null, true)), self::COOKIE_JOURS * 24 * 60);
    }

    public function codeRetenu(Request $request, Logement $logement): ?string
    {
        return $this->codesRetenus($request)[(string) $logement->getKey()] ?? null;
    }

    /** @return array<string, string> */
    private function codesRetenus(Request $request): array
    {
        $codes = json_decode((string) $request->cookie(self::COOKIE), true);

        return is_array($codes) ? array_filter($codes, 'is_string') : [];
    }

    /* ---------- Classement et revenus ---------- */

    /**
     * Classement des agents d'un propriétaire : séjours réalisés, location générée et commissions gagnées sur
     * ses logements, depuis une date ou depuis toujours. Les réservations confirmées à venir sont comptées à part.
     *
     * @return Collection<int, array{agent: User, rang: int, sejours: int, a_venir: int, chiffre: int, gains: int, logements: Collection}>
     */
    public function classement(User $proprietaire, ?CarbonInterface $depuis = null): Collection
    {
        $ids = $proprietaire->logements()->withTrashed()->pluck('id');
        $agents = $this->agentsDe($proprietaire);
        $stats = Reservation::query()->whereIn('logement_id', $ids)->whereNotNull('agent_id')
            ->when($depuis, fn ($q) => $q->where('debut', '>=', $depuis))
            ->selectRaw('agent_id, statut, COUNT(*) as nombre, SUM(montant_location) as chiffre, SUM(montant_marketing) as gains')
            ->groupBy('agent_id', 'statut')->get()->groupBy('agent_id');

        // Les anciens agents qui ont encore des séjours sur la période restent dans le classement
        $anciens = User::whereIn('id', $stats->keys()->diff($agents->modelKeys()))->get()->each->setRelation('logementsAgent', collect());

        $effectifs = array_map(fn ($s) => $s->value, self::SEJOURS_EFFECTIFS);
        $lignes = $agents->concat($anciens)->map(function (User $agent) use ($stats, $effectifs) {
            $s = $stats->get($agent->id, collect());
            $faits = $s->filter(fn ($l) => in_array($l->statut instanceof StatutReservation ? $l->statut->value : $l->statut, $effectifs, true));
            $aVenir = $s->filter(fn ($l) => ($l->statut instanceof StatutReservation ? $l->statut->value : $l->statut) === StatutReservation::Confirmee->value);

            return [
                'agent' => $agent,
                'sejours' => (int) $faits->sum('nombre'),
                'a_venir' => (int) $aVenir->sum('nombre'),
                'chiffre' => (int) $faits->sum('chiffre'),
                'gains' => (int) $faits->sum('gains'),
                'logements' => $agent->logementsAgent,
            ];
        })->sortBy([['gains', 'desc'], ['sejours', 'desc'], [fn ($l) => $l['agent']->name, 'asc']])->values();

        return $lignes->map(fn ($l, $i) => ['rang' => $i + 1] + $l);
    }

    /**
     * Espace de l'agent : logements promus (avec lien, part et résultats), solde et gains du mois.
     *
     * @return array{solde: int, ce_mois: int, total: int, sejours: int, a_venir: int, logements: Collection}
     */
    public function espace(User $agent): array
    {
        $effectifs = self::SEJOURS_EFFECTIFS;
        $parLogement = $agent->reservationsAgent()
            ->selectRaw('logement_id, statut, COUNT(*) as nombre, SUM(montant_marketing) as gains')
            ->groupBy('logement_id', 'statut')->get()->groupBy('logement_id');

        $logements = $agent->logementsAgent()->with(['photos', 'proprietaire:id,name'])->orderBy('titre')->get()
            ->map(function (Logement $l) use ($agent, $parLogement, $effectifs) {
                $s = $parLogement->get($l->id, collect());
                $faits = $s->filter(fn ($r) => in_array($r->statut, $effectifs, true));

                return [
                    'logement' => $l,
                    'lien' => $this->lien($l, $agent),
                    'part' => $l->libelleMarketing(),
                    'visible' => $l->estVisible(),
                    'sejours' => (int) $faits->sum('nombre'),
                    'a_venir' => (int) $s->filter(fn ($r) => $r->statut === StatutReservation::Confirmee)->sum('nombre'),
                    'gains' => (int) $faits->sum('gains'),
                ];
            });

        return [
            'code' => $this->codeDe($agent),
            'solde' => $agent->solde(),
            'ce_mois' => (int) $agent->mouvementsSolde()->where('type', TypeMouvement::CreditMarketing)->where('created_at', '>=', now()->startOfMonth())->sum('montant'),
            'total' => (int) $agent->mouvementsSolde()->where('type', TypeMouvement::CreditMarketing)->sum('montant'),
            'sejours' => $logements->sum('sejours'),
            'a_venir' => $logements->sum('a_venir'),
            'logements' => $logements,
        ];
    }

    /** @return Collection<int, Logement> */
    private function siens(User $proprietaire, array $logements): Collection
    {
        return $proprietaire->logements()->whereIn('id', array_map('intval', $logements))->get();
    }
}
