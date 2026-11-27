<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Logement;
use App\Models\User;
use App\Notifications\GestionConfiee;
use App\Support\Telephone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Gestionnaires délégués : un propriétaire confie certains de ses logements à une personne qui accueille les
 * clients (scanner, calendrier). Nombre limité par la formule d'abonnement (gestionnaires_max).
 */
class Delegations
{
    /** Gestionnaires de ce propriétaire, avec les logements qui leur sont confiés. */
    public function gestionnairesDe(User $proprietaire): Collection
    {
        $ids = $proprietaire->logements()->pluck('id');

        return User::whereHas('logementsDelegues', fn ($q) => $q->whereIn('logements.id', $ids))
            ->with(['logementsDelegues' => fn ($q) => $q->whereIn('logements.id', $ids)->orderBy('titre')])
            ->orderBy('name')->get();
    }

    public function maximum(User $proprietaire): int
    {
        if ($quotas = app(ModeleEconomique::class)->quotas($proprietaire)) {
            return $quotas['gestionnaires'];   // sur commission : quota des Paramètres
        }

        return (int) ($proprietaire->abonnementActif()->with('formule')->first()?->formule->gestionnaires_max ?? 0);
    }

    /**
     * Invite (ou retrouve) la personne et lui confie les logements choisis.
     *
     * @param  list<int>  $logements
     *
     * @throws ReservationImpossible message pour le propriétaire
     */
    public function confier(User $proprietaire, string $nom, string $identifiant, array $logements): User
    {
        $identifiant = trim($identifiant);
        $email = filter_var($identifiant, FILTER_VALIDATE_EMAIL) ? mb_strtolower($identifiant) : null;
        $telephone = $email ? null : Telephone::normaliser($identifiant);
        if (! $email && ! $telephone) {
            throw new ReservationImpossible('Indiquez une adresse email ou un numéro béninois à 10 chiffres (01 97 00 00 00).');
        }
        $logements = $this->siens($proprietaire, $logements);
        if ($logements->isEmpty()) {
            throw new ReservationImpossible('Choisissez au moins un logement à confier.');
        }

        $existant = User::where($email ? 'email' : 'telephone', $email ?? $telephone)->first();
        if ($existant?->hasAnyRole([Role::Proprietaire->value, Role::Administrateur->value, Role::Caissiere->value])) {
            throw new ReservationImpossible('Ce compte est celui d’un propriétaire ou de l’équipe VIVI : il ne peut pas être gestionnaire.');
        }
        $dejaGestionnaire = $existant && $this->gestionnairesDe($proprietaire)->contains('id', $existant->id);
        if (! $dejaGestionnaire && $this->maximum($proprietaire) < ModeleEconomique::ILLIMITE_GESTIONNAIRES
            && $this->gestionnairesDe($proprietaire)->count() >= $this->maximum($proprietaire)) {
            $max = $this->maximum($proprietaire);
            throw new ReservationImpossible(app(ModeleEconomique::class)->surCommission($proprietaire)
                ? ($max === 0 ? 'Votre compte n’inclut pas de gestionnaire délégué.' : 'Votre compte inclut '.$max.' gestionnaire(s) au plus.')
                : ($max === 0 ? 'Votre formule n’inclut pas de gestionnaire délégué. Passez à une formule supérieure.' : 'Votre formule inclut '.$max.' gestionnaire(s) au plus.'));
        }

        $gestionnaire = DB::transaction(function () use ($existant, $nom, $email, $telephone, $logements) {
            $u = $existant ?? User::create(['name' => trim($nom), 'email' => $email, 'telephone' => $telephone, 'actif' => true]);
            $u->assignRole(Role::Gestionnaire->value);
            $u->logementsDelegues()->syncWithoutDetaching($logements->modelKeys());

            return $u;
        });

        try {
            $gestionnaire->notify(new GestionConfiee($proprietaire, $logements));
        } catch (Throwable $e) {
            report($e);
        }

        return $gestionnaire;
    }

    /** Remplace les logements de ce propriétaire confiés à ce gestionnaire (aucun = accès retiré). */
    public function modifier(User $proprietaire, User $gestionnaire, array $logements): void
    {
        $tous = $proprietaire->logements()->pluck('id')->all();
        DB::transaction(function () use ($gestionnaire, $tous, $proprietaire, $logements) {
            $gestionnaire->logementsDelegues()->detach($tous);
            $gestionnaire->logementsDelegues()->attach($this->siens($proprietaire, $logements)->modelKeys());
            // Plus aucun logement confié par personne : il n'est plus gestionnaire
            if (! $gestionnaire->logementsDelegues()->exists()) {
                $gestionnaire->removeRole(Role::Gestionnaire->value);
                if ($gestionnaire->roles()->doesntExist()) {
                    $gestionnaire->assignRole(Role::Client->value);
                }
            }
        });
    }

    /** @return Collection<int, Logement> */
    private function siens(User $proprietaire, array $logements): Collection
    {
        return $proprietaire->logements()->whereIn('id', array_map('intval', $logements))->get();
    }
}
