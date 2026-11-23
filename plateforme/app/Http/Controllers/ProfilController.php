<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CodesConnexion;
use App\Services\Surveillance;
use App\Support\Parametres;
use App\Support\Telephone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * « Mon profil » : nom, email et téléphone. L'email et le téléphone servent à se connecter : un changement
 * n'est enregistré qu'après le code reçu sur la nouvelle adresse ou le nouveau numéro (on vérifie qu'il est à vous).
 */
class ProfilController extends Controller
{
    private const SESSION = 'profil_changement';

    public function __construct(private readonly CodesConnexion $codes) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('espace.profil', [
            'user' => $user,
            'enAttente' => $request->session()->get(self::SESSION),
            'emailActif' => (bool) Parametres::get('notifications_email'),
            'smsActif' => (bool) Parametres::smsActif(),
            'stats' => [
                'reservations' => $user->reservations()->whereNotIn('statut', ['en_attente_paiement', 'annulee'])->count(),
                'membreDepuis' => $user->created_at,
            ],
        ]);
    }

    public function nom(Request $request): RedirectResponse
    {
        $donnees = $request->validate(['nom' => ['required', 'string', 'min:3', 'max:120']], ['nom.required' => 'Indiquez votre nom et votre prénom.']);
        $request->user()->update(['name' => trim($donnees['nom'])]);

        return back()->with('statut', 'Votre nom est enregistré.');
    }

    /** Offres de VIVI acceptées ou refusées (les notifications de réservation restent toujours). */
    public function preferences(Request $request): RedirectResponse
    {
        $offres = $request->boolean('recevoir_offres');
        $request->user()->update(['recevoir_offres' => $offres]);

        return back()->with('statut', $offres ? 'Vous recevrez les offres de VIVI.' : 'Vous ne recevrez plus les offres de VIVI.');
    }

    /** Étape 1 : nouvelle adresse email ou nouveau numéro, un code y est envoyé. */
    public function demander(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'champ' => ['required', 'in:email,telephone'],
            'valeur' => ['required', 'string', 'max:255'],
        ], ['valeur.required' => 'Indiquez la nouvelle valeur.']);
        $user = $request->user();
        $erreur = fn (string $message) => throw ValidationException::withMessages(['valeur_'.$donnees['champ'] => $message]);

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

        $request->session()->put(self::SESSION, ['champ' => $donnees['champ'], 'valeur' => $valeur, 'canal' => $canal]);

        return back()->with('statut', 'Un code vient d’être envoyé '.($canal === 'sms' ? 'par SMS au '.Telephone::lisible($valeur) : 'à '.$valeur).'.')
            ->with('code_dev', app()->environment('local') && config('vivi.connexion.afficher_code_en_local') ? $code : null);
    }

    /** Étape 2 : le code reçu confirme que l'adresse ou le numéro est bien à vous. */
    public function confirmer(Request $request): RedirectResponse
    {
        $attente = $request->session()->get(self::SESSION);
        if (! $attente) {
            return redirect()->route('profil');
        }
        $request->validate(['code' => ['required', 'string']], ['code.required' => 'Saisissez le code reçu.']);

        $resultat = $this->codes->verifier($attente['valeur'], (string) $request->input('code'));
        if ($resultat !== CodesConnexion::RESULTAT_OK) {
            app(Surveillance::class)->codeFaux((string) $request->ip());
            throw ValidationException::withMessages(['code' => match ($resultat) {
                CodesConnexion::RESULTAT_FAUX => 'Ce code ne correspond pas. Vérifiez les 6 chiffres.',
                CodesConnexion::RESULTAT_TROP_ESSAIS => 'Trop d’essais. Demandez un nouveau code.',
                default => 'Ce code a expiré. Demandez un nouveau code.',
            }]);
        }

        $user = $request->user();
        if (User::where($attente['champ'], $attente['valeur'])->whereKeyNot($user->getKey())->exists()) {
            $request->session()->forget(self::SESSION);

            return back()->withErrors(['code' => 'Entre-temps, cette valeur a été prise par un autre compte.']);
        }
        $user->forceFill([
            $attente['champ'] => $attente['valeur'],
            $attente['champ'] === 'email' ? 'email_verified_at' : 'telephone_verified_at' => now(),
        ])->save();
        $request->session()->forget(self::SESSION);

        return redirect()->route('profil')->with('statut', $attente['champ'] === 'email'
            ? 'Adresse email enregistrée : vos confirmations et reçus y seront aussi envoyés. Vous pouvez vous connecter avec.'
            : 'Numéro enregistré : vos SMS y seront envoyés. Vous pouvez vous connecter avec.');
    }

    public function annuler(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION);

        return redirect()->route('profil');
    }
}
