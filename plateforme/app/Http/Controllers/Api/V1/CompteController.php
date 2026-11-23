<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Format;
use App\Http\Resources\Api\UtilisateurResource;
use App\Models\User;
use App\Services\CodesConnexion;
use App\Services\Inscriptions;
use App\Services\Parrainage;
use App\Support\Montant;
use App\Support\NotificationsApp;
use App\Support\Parametres;
use App\Support\Telephone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Compte de la personne connectée : profil (nom, email, téléphone confirmés par code),
 * notifications et parrainage. Commun aux clients, propriétaires et gestionnaires.
 */
class CompteController extends Controller
{
    public function __construct(private readonly CodesConnexion $codes, private readonly Inscriptions $inscriptions) {}

    /* ---------- Profil ---------- */

    public function profil(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => (new UtilisateurResource($user))->resolve($request),
            'stats' => ['reservations' => $user->reservations()->whereNotIn('statut', ['en_attente_paiement', 'annulee'])->count()],
        ]);
    }

    public function nom(Request $request): UtilisateurResource
    {
        $donnees = $request->validate(['nom' => ['required', 'string', 'min:3', 'max:120']], ['nom.required' => 'Indiquez votre nom et votre prénom.']);
        $request->user()->update(['name' => trim($donnees['nom'])]);

        return new UtilisateurResource($request->user());
    }

    /** Étape 1 : nouvel email ou nouveau numéro {champ: email|telephone, valeur} ; un code y est envoyé. */
    public function demanderChangement(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'champ' => ['required', 'in:email,telephone'],
            'valeur' => ['required', 'string', 'max:255'],
        ], ['valeur.required' => 'Indiquez la nouvelle valeur.']);
        $user = $request->user();
        $erreur = fn (string $message) => throw ValidationException::withMessages(['valeur' => $message]);

        if ($donnees['champ'] === 'email') {
            $valeur = mb_strtolower(trim($donnees['valeur']));
            if (! filter_var($valeur, FILTER_VALIDATE_EMAIL)) {
                $erreur('Cette adresse email n’est pas valide.');
            }
            if (! Parametres::get('notifications_email')) {
                $erreur('L’envoi d’emails est désactivé pour le moment.');
            }
            $canal = 'mail';
        } else {
            $valeur = Telephone::normaliser($donnees['valeur']);
            if (! $valeur) {
                $erreur('Numéro béninois à 10 chiffres, par exemple 01 97 00 00 00.');
            }
            if (! Parametres::smsActif()) {
                $erreur('L’envoi de SMS est désactivé pour le moment.');
            }
            $canal = 'sms';
        }
        if ($valeur === $user->{$donnees['champ']}) {
            $erreur('C’est déjà votre '.($donnees['champ'] === 'email' ? 'adresse email' : 'numéro').'.');
        }
        if (User::where($donnees['champ'], $valeur)->whereKeyNot($user->getKey())->exists()) {
            $erreur('Cette '.($donnees['champ'] === 'email' ? 'adresse' : 'ligne').' est déjà utilisée par un autre compte VIVI.');
        }

        try {
            $code = $this->codes->envoyer($valeur, $canal, $request->ip());
        } catch (RuntimeException $e) {
            $erreur($e->getMessage());
        } catch (Throwable $e) {
            Log::error('Code de changement de profil non envoyé : '.$e->getMessage());
            $erreur('Le code n’a pas pu être envoyé. Réessayez dans un instant.');
        }

        Cache::put($this->cleChangement($user), ['champ' => $donnees['champ'], 'valeur' => $valeur],
            now()->addMinutes((int) config('vivi.connexion.code_validite_minutes') + 5));

        return response()->json([
            'message' => 'Un code vient d’être envoyé '.($canal === 'sms' ? 'par SMS au '.Telephone::lisible($valeur) : 'à '.$valeur).'.',
            'code_dev' => $this->inscriptions->codeAAfficher($code),
        ]);
    }

    /** Étape 2 : {code} reçu sur la nouvelle adresse ou le nouveau numéro. */
    public function confirmerChangement(Request $request): UtilisateurResource
    {
        $user = $request->user();
        $attente = Cache::get($this->cleChangement($user))
            ?? throw ValidationException::withMessages(['code' => 'Aucun changement en attente : recommencez.']);
        $request->validate(['code' => ['required', 'string']], ['code.required' => 'Saisissez le code reçu.']);

        $this->inscriptions->verifierCode($attente['valeur'], (string) $request->input('code'), $request->ip());
        Cache::forget($this->cleChangement($user));

        if (User::where($attente['champ'], $attente['valeur'])->whereKeyNot($user->getKey())->exists()) {
            throw ValidationException::withMessages(['code' => 'Entre-temps, cette valeur a été prise par un autre compte.']);
        }
        $user->forceFill([
            $attente['champ'] => $attente['valeur'],
            $attente['champ'] === 'email' ? 'email_verified_at' : 'telephone_verified_at' => now(),
        ])->save();

        return new UtilisateurResource($user);
    }

    /* ---------- Notifications ---------- */

    /**
     * Espace Notifications de l'application. Filtres : ?categorie=reservation|paiement|offre|logement|compte,
     * ?non_lues=1, ?depuis=2026-11-21T10:00:00Z (seulement les plus récentes : pour l'interrogation régulière).
     */
    public function notifications(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'categorie' => ['nullable', 'in:'.implode(',', NotificationsApp::CATEGORIES)],
            'non_lues' => ['nullable', 'boolean'],
            'depuis' => ['nullable', 'date'],
        ]);
        $user = $request->user();
        $page = $user->notificationsSite()
            ->when($filtres['categorie'] ?? null, fn ($q, $c) => NotificationsApp::filtrerCategorie($q, $c))
            ->when($request->boolean('non_lues'), fn ($q) => $q->whereNull('read_at'))
            ->when($filtres['depuis'] ?? null, fn ($q, $d) => $q->where('created_at', '>', \Illuminate\Support\Carbon::parse($d)->timezone(config('app.timezone'))))
            ->latest()->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($n) => NotificationsApp::versApi($n)),
            'meta' => [
                'non_lues' => $user->notificationsSite()->whereNull('read_at')->count(),
                'page' => $page->currentPage(), 'derniere_page' => $page->lastPage(), 'total' => $page->total(),
            ],
        ]);
    }

    /** Pastille de l'icône cloche : nombre de non lues, par catégorie, et la plus récente (léger, à appeler souvent). */
    public function compteurNotifications(Request $request): JsonResponse
    {
        $nonLues = $request->user()->notificationsSite()->whereNull('read_at')->latest()->get(['id', 'type', 'data', 'read_at', 'created_at']);
        $parCategorie = array_fill_keys(NotificationsApp::CATEGORIES, 0);
        foreach ($nonLues as $n) {
            $parCategorie[NotificationsApp::categorie($n)]++;
        }
        $derniere = $request->user()->notificationsSite()->latest()->first();

        return response()->json(['data' => [
            'non_lues' => $nonLues->count(),
            'par_categorie' => $parCategorie,
            'derniere' => $derniere ? NotificationsApp::versApi($derniere) : null,
        ]]);
    }

    public function notification(Request $request, string $id): JsonResponse
    {
        return response()->json(['data' => NotificationsApp::versApi($request->user()->notificationsSite()->findOrFail($id))]);
    }

    public function lireNotification(Request $request, string $id): JsonResponse
    {
        $request->user()->notificationsSite()->findOrFail($id)->markAsRead();

        return response()->json(['message' => 'Notification lue.']);
    }

    public function supprimerNotification(Request $request, string $id): JsonResponse
    {
        $request->user()->notificationsSite()->findOrFail($id)->delete();

        return response()->json(['message' => 'Notification supprimée.']);
    }

    /* ---------- Téléphones (notifications push) ---------- */

    /**
     * Enregistre le jeton Firebase du téléphone {jeton, plateforme?, nom?}. À appeler après la connexion et à chaque
     * nouveau jeton (onNewToken). Un jeton déjà connu passe au compte connecté (téléphone partagé, changement de compte).
     */
    public function enregistrerAppareil(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'jeton' => ['required', 'string', 'min:20', 'max:255'],
            'plateforme' => ['nullable', 'in:android,ios,web'],
            'nom' => ['nullable', 'string', 'max:120'],
        ], ['jeton.required' => 'Le jeton Firebase du téléphone est manquant.']);

        $appareil = \App\Models\Appareil::updateOrCreate(['jeton' => $donnees['jeton']], [
            'user_id' => $request->user()->getKey(),
            'plateforme' => $donnees['plateforme'] ?? 'android',
            'nom' => $donnees['nom'] ?? null,
            'vu_le' => now(),
        ]);

        return response()->json([
            'message' => 'Téléphone enregistré pour les notifications.',
            'data' => ['id' => $appareil->id, 'push_actif' => app(\App\Services\NotificationsPush::class)->actif()],
        ], $appareil->wasRecentlyCreated ? 201 : 200);
    }

    /** Oublie le téléphone {jeton} : plus de push (à la déconnexion, ou si la personne coupe les notifications). */
    public function retirerAppareil(Request $request): JsonResponse
    {
        $jeton = (string) $request->validate(['jeton' => ['required', 'string', 'max:255']])['jeton'];
        $request->user()->appareils()->where('jeton', $jeton)->delete();

        return response()->json(['message' => 'Ce téléphone ne recevra plus de notifications.']);
    }

    /* ---------- Préférences ---------- */

    /** {recevoir_offres: bool} : les offres de VIVI. Les notifications de réservation et de paiement restent toujours. */
    public function preferences(Request $request): UtilisateurResource
    {
        $donnees = $request->validate(['recevoir_offres' => ['required', 'boolean']]);
        $request->user()->update(['recevoir_offres' => (bool) $donnees['recevoir_offres']]);

        return new UtilisateurResource($request->user());
    }

    public function toutLire(Request $request): JsonResponse
    {
        $request->user()->notificationsSite()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'Toutes les notifications sont marquées comme lues.']);
    }

    /* ---------- Parrainage ---------- */

    public function parrainage(Request $request, Parrainage $parrainage): JsonResponse
    {
        $user = $request->user();
        $actif = $parrainage->actif();
        $code = $actif ? $parrainage->codeDe($user) : $user->code_parrainage;
        $lien = $code ? route('connexion', ['invitation' => $code]) : null;
        $bonusFilleul = (int) Parametres::get('parrainage_bonus_filleul');
        $prochaine = $user->bonus()->utilisables()->orderBy('expire_le')->first();

        return response()->json(['data' => [
            'actif' => $actif,
            'code' => $code,
            'lien' => $lien,
            'message_partage' => $code ? 'Je réserve mes appartements meublés sur VIVI, à l’heure ou à la journée. Inscris-toi avec mon code '.$code
                .($bonusFilleul > 0 ? ' et reçois '.Montant::fcfa($bonusFilleul).' de bonus' : '').' : '.$lien : null,
            'bonus_parrain' => (int) Parametres::get('parrainage_bonus_parrain'),
            'bonus_filleul' => $bonusFilleul,
            'plafond_pourcentage' => (int) Parametres::get('bonus_plafond_pourcentage'),
            'disponible' => $parrainage->disponible($user),
            'prochaine_expiration' => $prochaine ? ['montant' => (int) $prochaine->restant, 'le' => Format::jour($prochaine->expire_le)] : null,
            'historique' => $user->bonus()->latest()->limit(30)->get()->map(fn ($b) => [
                'libelle' => $b->libelle, 'motif' => $b->motif, 'montant' => (int) $b->montant, 'restant' => (int) $b->restant,
                'expire_le' => Format::jour($b->expire_le), 'le' => Format::date($b->created_at),
            ]),
            'filleuls' => $user->filleuls()->latest()->limit(50)->get(['id', 'name', 'created_at', 'parrainage_recompense_le'])->map(fn ($f) => [
                'prenom' => $f->prenom(), 'inscrit_le' => Format::date($f->created_at), 'recompense' => $f->parrainage_recompense_le !== null,
            ]),
        ]]);
    }

    private function cleChangement(User $user): string
    {
        return 'api-profil-changement:'.$user->getKey();
    }
}
