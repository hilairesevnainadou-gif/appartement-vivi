<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\StatutAbonnement;
use App\Enums\StatutLogement;
use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Enums\StatutRetrait;
use App\Models\CodeConnexion;
use App\Models\Logement;
use App\Models\Paiement;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\CompteSupprime;
use App\Support\Equipe;
use App\Support\Montant;
use App\Support\Parametres;
use App\Support\Permissions;
use App\Support\Prevenir;
use App\Support\Telephone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Suppression du compte par son titulaire (site et application), confirmée par un code.
 *
 * La ligne du compte reste, vidée de toute donnée personnelle : les réservations, paiements et retraits sont des
 * pièces comptables. La suppression est refusée tant qu'un séjour, un paiement ou de l'argent est en cours.
 */
class SuppressionCompte
{
    public const NOM = 'Compte supprimé';

    /** Sous ce montant, le solde ne peut pas être retiré : il n'empêche pas la suppression. */
    public const RETRAIT_MINIMUM = 1000;

    public function __construct(
        private readonly CodesConnexion $codes,
        private readonly Inscriptions $inscriptions,
        private readonly Delegations $delegations,
        private readonly AgentsMarketing $agents,
    ) {}

    /**
     * Ce qui empêche la suppression maintenant, avec ce que la personne doit faire.
     *
     * @return list<array{code: string, message: string}>
     */
    public function obstacles(User $u): array
    {
        if ($u->hasAnyRole(Role::equipeVivi()) || $u->can(Permissions::ACCES_ADMINISTRATION) || Logement::where('gerant_vivi_id', $u->getKey())->exists()) {
            return [['code' => 'equipe', 'message' => 'Ce compte appartient à l’équipe VIVI : demandez sa suppression à un administrateur.']];
        }

        $siennes = fn () => Reservation::where('client_id', $u->getKey());
        $solde = $u->solde();
        $cas = [
            ['reservation', $siennes()->whereIn('statut', [StatutReservation::Confirmee, StatutReservation::EnCours])->exists(),
                'Vous avez une réservation à venir ou en cours. Annulez-la, ou attendez la fin du séjour.'],
            ['paiement', $siennes()->where('statut', StatutReservation::EnAttentePaiement)->where('bloque_jusqua', '>', now())->exists()
                || Paiement::where('payeur_id', $u->getKey())->where('statut', StatutPaiement::EnAttente)->where('created_at', '>', now()->subHour())->exists(),
                'Un paiement est en cours. Réessayez dans une heure.'],
            ['remboursement', $siennes()->where('statut', StatutReservation::Annulee)->where('montant_rembourse', '>', 0)->whereNull('rembourse_le')->exists(),
                'Un remboursement doit encore vous être versé.'],
            ['accueil', Reservation::whereIn('logement_id', $this->idsLogements($u))->occupantes()->exists(),
                'Des clients ont réservé chez vous. Attendez la fin des séjours, ou contactez VIVI.'],
            ['retrait', $u->retraits()->where('statut', StatutRetrait::EnAttente)->exists(), 'Un retrait est en attente de paiement.'],
            ['solde', $solde >= self::RETRAIT_MINIMUM, 'Votre solde est de '.Montant::fcfa($solde).' : retirez-le d’abord.'],
            ['contact', $this->destination($u) === null, 'Nous ne pouvons pas vous envoyer de code de confirmation : contactez le service client.'],
        ];

        return array_values(array_map(
            fn (array $c) => ['code' => $c[0], 'message' => $c[2]],
            array_filter($cas, fn (array $c) => $c[1]),
        ));
    }

    /**
     * Ce que la personne perd, selon son compte.
     *
     * @return list<string>
     */
    public function pertes(User $u): array
    {
        $bonus = (int) $u->bonus()->utilisables()->sum('restant');
        $solde = $u->solde();

        return array_values(array_filter([
            'Votre nom, votre téléphone, votre email, votre compte Google relié et vos notifications sont effacés.',
            'Vous n’avez plus accès à vos réservations passées ni à vos reçus. Téléchargez-les avant si besoin.',
            $bonus > 0 ? 'Votre bonus de '.Montant::fcfa($bonus).' est perdu.' : null,
            $solde > 0 && $solde < self::RETRAIT_MINIMUM ? 'Votre solde de '.Montant::fcfa($solde).', sous le minimum de retrait, est perdu.' : null,
            $this->idsLogements($u) ? 'Vos logements et vos salles sont retirés du site, leurs photos effacées, et vos gestionnaires et agents n’y ont plus accès.' : null,
            $u->abonnements()->where('statut', StatutAbonnement::Actif)->whereDate('fin', '>=', today())->exists() ? 'Votre abonnement s’arrête, sans remboursement.' : null,
            $this->commissionsAVenir($u)->exists() ? 'Vos commissions d’agent sur les séjours pas encore commencés sont perdues.' : null,
        ]));
    }

    /**
     * Où envoyer le code : d'abord ce qui est vérifié, l'email avant le SMS.
     *
     * @return array{identifiant: string, canal: string, lisible: string}|null
     */
    public function destination(User $u): ?array
    {
        $email = $u->email && Parametres::get('notifications_email')
            ? ['identifiant' => $u->email, 'canal' => 'mail', 'lisible' => $u->email] : null;
        $sms = $u->telephone && Parametres::get('notifications_sms')
            ? ['identifiant' => $u->telephone, 'canal' => 'sms', 'lisible' => Telephone::masque($u->telephone)] : null;

        return match (true) {
            $email && $u->email_verified_at !== null => $email,
            $sms && $u->telephone_verified_at !== null => $sms,
            default => $email ?? $sms,
        };
    }

    /**
     * Étape 1 : envoie le code de confirmation.
     *
     * @return array{destination: array{identifiant: string, canal: string, lisible: string}, code: string}
     *
     * @throws ValidationException sur « compte »
     */
    public function envoyerCode(User $u, ?string $ip): array
    {
        $this->refuserSiObstacle($u);
        $destination = $this->destination($u);
        try {
            $code = $this->codes->envoyer($destination['identifiant'], $destination['canal'], $ip, 'suppression');
        } catch (Throwable $e) {
            if ($e::class === RuntimeException::class) {   // un code est parti il y a moins d'une minute
                $this->erreur($e->getMessage());
            }
            Log::error('Code de suppression de compte non envoyé : '.$e->getMessage());
            $this->erreur('Le code n’a pas pu être envoyé. Réessayez dans un instant.');
        }
        Cache::put($this->cle($u), $destination, now()->addMinutes((int) config('vivi.connexion.code_validite_minutes') + 5));

        return ['destination' => $destination, 'code' => $code];
    }

    /** Destination du code attendu pour ce compte, ou null. */
    public function enAttente(User $u): ?array
    {
        return Cache::get($this->cle($u));
    }

    public function abandonner(User $u): void
    {
        Cache::forget($this->cle($u));
    }

    /**
     * Étape 2 : vérifie le code puis supprime le compte.
     *
     * @throws ValidationException sur « code » ou « compte »
     */
    public function confirmer(User $u, string $code, ?string $ip): void
    {
        $attente = $this->enAttente($u) ?? throw ValidationException::withMessages(['code' => 'Cette demande a expiré : demandez un nouveau code.']);
        $this->inscriptions->verifierCode($attente['identifiant'], $code, $ip);
        $this->supprimer($u);
        $this->abandonner($u);

        Prevenir::sansBloquer(fn () => Notification::route(
            $attente['canal'] === 'sms' ? 'sms' : 'mail',
            $attente['canal'] === 'sms' ? Telephone::international($attente['identifiant']) : $attente['identifiant'],
        )->notify(new CompteSupprime($attente['canal'])));
    }

    /** Vide le compte de toute donnée personnelle et ferme tous ses accès. */
    private function supprimer(User $compte): void
    {
        $photos = [];
        $pro = DB::transaction(function () use ($compte, &$photos) {
            $u = User::whereKey($compte->getKey())->lockForUpdate()->firstOrFail();
            $this->refuserSiObstacle($u);
            $pro = $u->estProprietaireOuGestionnaire() || $u->estAgent();
            [$nom, $identifiants] = [(string) $u->name, array_values(array_filter([$u->email, $u->telephone]))];

            activity()->withoutLogs(function () use ($u, &$photos) {
                $photos = $this->fermerEspacePro($u);

                $u->reservations()->update(['whatsapp' => null]);
                $u->reservations()->where('statut', StatutReservation::EnAttentePaiement)
                    ->update(['statut' => StatutReservation::Annulee, 'annulee_le' => now(), 'motif_annulation' => self::NOM]);
                $u->bonus()->update(['restant' => 0]);
                $u->notifications()->delete();
                $u->appareils()->delete();
                $u->tokens()->delete();
                $u->syncRoles([]);
                $u->forceFill([
                    'name' => self::NOM, 'email' => null, 'email_verified_at' => null, 'telephone' => null, 'telephone_verified_at' => null,
                    'whatsapp' => null, 'google_id' => null, 'password' => null, 'remember_token' => null, 'code_parrainage' => null,
                    'code_agent' => null, 'parrain_id' => null, 'retrait_moyen' => null, 'retrait_numero' => null, 'retrait_titulaire' => null,
                    'retrait_modifie_le' => null, 'recevoir_offres' => false, 'actif' => false, 'supprime_le' => now(),
                ])->save();
            });

            CodeConnexion::whereIn('identifiant', $identifiants)->delete();
            DB::table('password_reset_tokens')->whereIn('email', $identifiants)->delete();
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $u->getKey())->delete();
            }
            $this->nettoyerJournal($u, $nom);
            activity('administration')->performedOn($u)->event('suppression_compte')->log('Compte supprimé par son titulaire');

            return $pro;
        });

        Storage::disk('public')->delete($photos);
        if ($pro) {
            Equipe::prevenir('users.voir', 'Compte professionnel supprimé',
                'Le compte n° '.$compte->getKey().' (propriétaire, gestionnaire ou agent) a été supprimé par son titulaire. Ses fiches sont retirées du site.',
                null, 'heroicon-o-user-minus', 'warning');
        }
    }

    /**
     * Fiches retirées du site, accès délégués retirés, abonnement arrêté.
     *
     * @return list<string> photos à effacer du disque, après la transaction
     */
    private function fermerEspacePro(User $u): array
    {
        $photos = [];
        $ids = $this->idsLogements($u);
        User::whereHas('logementsDelegues', fn ($q) => $q->whereIn('logements.id', $ids))->get()->each(fn (User $g) => $this->delegations->modifier($u, $g, []));
        User::whereHas('logementsAgent', fn ($q) => $q->whereIn('logements.id', $ids))->get()->each(fn (User $a) => $this->agents->modifier($u, $a, []));

        foreach (Logement::withTrashed()->whereIn('id', $ids)->with('photos')->get() as $logement) {
            foreach ($logement->photos as $photo) {
                // Les photos de démonstration (dossier partagé) restent sur le disque
                if (str_starts_with($photo->chemin, 'logements/'.$logement->getKey().'/')) {
                    $photos[] = $photo->chemin;
                }
                $photo->delete();
            }
            $logement->update([
                'statut' => StatutLogement::Suspendu, 'en_ligne' => false, 'adresse' => null, 'latitude' => null, 'longitude' => null,
                'description' => null, 'gerant_vivi_id' => null,
            ]);
        }
        $u->abonnements()->where('statut', StatutAbonnement::Actif)->update(['statut' => StatutAbonnement::Resilie]);

        // Accès reçus d'autres propriétaires ; commissions pas encore gagnées : la part marketing revient à VIVI
        $u->logementsDelegues()->detach();
        $u->logementsAgent()->detach();
        $this->commissionsAVenir($u)->update(['agent_id' => null]);

        return $photos;
    }

    /** Le journal d'activité garde les faits, sans les anciennes valeurs personnelles. */
    private function nettoyerJournal(User $u, string $nom): void
    {
        $journal = fn (string $type, array $ids) => DB::table('activity_log')->where('subject_type', $type)->whereIn('subject_id', $ids);

        $journal('user', [$u->getKey()])->update(['properties' => '[]']);
        foreach ($journal('user', [$u->getKey()])->get(['id', 'description']) as $ligne) {
            if ($nom !== '' && str_contains($ligne->description, $nom)) {
                DB::table('activity_log')->where('id', $ligne->id)->update(['description' => str_replace($nom, self::NOM, $ligne->description)]);
            }
        }
        $journal('logement', $this->idsLogements($u))->update(['properties' => '[]']);
        $journal('reservation', $u->reservations()->pluck('id')->all())->where('properties', 'like', '%whatsapp%')->update(['properties' => '[]']);
    }

    private function commissionsAVenir(User $u): Builder
    {
        return Reservation::where('agent_id', $u->getKey())->whereIn('statut', [StatutReservation::Confirmee, StatutReservation::EnAttentePaiement]);
    }

    /** @return list<int> y compris les fiches déjà mises à la corbeille */
    private function idsLogements(User $u): array
    {
        return Logement::withTrashed()->where('proprietaire_id', $u->getKey())->pluck('id')->all();
    }

    private function refuserSiObstacle(User $u): void
    {
        if ($obstacles = $this->obstacles($u)) {
            throw ValidationException::withMessages(['compte' => array_column($obstacles, 'message')]);
        }
    }

    private function cle(User $u): string
    {
        return 'suppression-compte:'.$u->getKey();
    }

    private function erreur(string $message): never
    {
        throw ValidationException::withMessages(['compte' => $message]);
    }
}
