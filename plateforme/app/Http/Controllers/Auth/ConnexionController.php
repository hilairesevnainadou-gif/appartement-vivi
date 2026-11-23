<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Services\Inscriptions;
use App\Services\Parrainage;
use App\Support\Parametres;
use App\Support\Telephone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Connexion et création de compte sans mot de passe, pour les clients,
 * propriétaires et gestionnaires : un code à 6 chiffres est envoyé par
 * email (gratuit) ou par SMS, selon l'identifiant saisi.
 *
 * L'équipe VIVI (administrateur, caissière) peut aussi l'utiliser avec son email ;
 * elle garde la connexion par mot de passe sur /admin.
 * La logique est partagée avec l'application mobile (App\Services\Inscriptions).
 */
class ConnexionController extends Controller
{
    private const SESSION = 'connexion';

    public function __construct(private readonly Inscriptions $inscriptions, private readonly Parrainage $parrainage) {}

    public function formulaire(Request $request): View
    {
        // ?suite=/logements/... : après la connexion, on revient sur cette page (adresse interne uniquement)
        $suite = (string) $request->query('suite');
        if (str_starts_with($suite, '/') && ! str_starts_with($suite, '//')) {
            $request->session()->put('url.intended', url($suite));
        }

        // ?invitation=CODE : lien partagé par un parrain, gardé pour l'inscription
        if ($request->filled('invitation')) {
            $request->session()->put('invitation', mb_substr((string) $request->query('invitation'), 0, 20));
        }

        return view('auth.connexion', [
            'mode' => $request->query('mode') === 'inscription' || $request->filled('invitation') ? 'inscription' : 'connexion',
            'invitation' => $request->session()->get('invitation'),
            'parrainageActif' => $this->parrainage->actif(),
            'bonusBienvenue' => (int) Parametres::get('parrainage_bonus_filleul'),
            'role' => $request->query('role') === 'proprietaire' ? 'proprietaire' : 'client',
            'smsActif' => Parametres::smsActif(),
            'emailActif' => Parametres::get('notifications_email'),
            'googleActif' => app(\App\Services\ConnexionGoogle::class)->actif(),
        ]);
    }

    public function envoyerCode(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'mode' => ['required', Rule::in(['connexion', 'inscription'])],
            'identifiant' => ['required', 'string', 'max:255'],
            'pays' => ['nullable', Rule::in(['benin', 'international'])],   // onglet « Au Bénin » (numéro ou email) ou « International » (email)
            'nom' => ['exclude_unless:mode,inscription', 'required', 'string', 'min:3', 'max:120'],
            'role' => ['exclude_unless:mode,inscription', 'required', Rule::in([Role::Client->value, Role::Proprietaire->value])],
            'invitation' => ['exclude_unless:mode,inscription', 'nullable', 'string', 'max:20'],
        ], [
            'nom.required' => 'Indiquez votre nom et votre prénom.',
            'identifiant.required' => 'Indiquez votre adresse email ou votre numéro de téléphone.',
        ]);

        [$identifiant, $canal] = $this->inscriptions->analyserIdentifiant($donnees['identifiant'], $donnees['pays'] ?? null);
        $this->inscriptions->verifierCanalActif($canal);
        $invitation = $this->inscriptions->controlerDemande($donnees['mode'], $identifiant, $canal, $donnees['invitation'] ?? null);

        $code = $this->inscriptions->envoyer($identifiant, $canal, $request->ip());

        $request->session()->put(self::SESSION, [
            'identifiant' => $identifiant,
            'canal' => $canal,
            'mode' => $donnees['mode'],
            'nom' => $donnees['nom'] ?? null,
            'role' => $donnees['role'] ?? null,
            'invitation' => $invitation ?: null,
        ]);

        return redirect()->route('connexion.code')->with('code_dev', $this->inscriptions->codeAAfficher($code));
    }

    public function formulaireCode(Request $request): View|RedirectResponse
    {
        $attente = $request->session()->get(self::SESSION);
        if (! $attente) {
            return redirect()->route('connexion');
        }

        return view('auth.code', [
            'destination' => $attente['canal'] === 'sms'
                ? 'au '.Telephone::masque($attente['identifiant'])
                : 'à '.$attente['identifiant'],
            'canal' => $attente['canal'],
            'renvoiSecondes' => (int) config('vivi.connexion.renvoi_secondes'),
        ]);
    }

    public function renvoyer(Request $request): RedirectResponse
    {
        $attente = $request->session()->get(self::SESSION);
        if (! $attente) {
            return redirect()->route('connexion');
        }

        $code = $this->inscriptions->envoyer($attente['identifiant'], $attente['canal'], $request->ip(), champ: 'code');

        return back()->with('statut', 'Un nouveau code vient de vous être envoyé.')->with('code_dev', $this->inscriptions->codeAAfficher($code));
    }

    public function verifierCode(Request $request): RedirectResponse
    {
        $attente = $request->session()->get(self::SESSION);
        if (! $attente) {
            return redirect()->route('connexion');
        }

        $request->validate(['code' => ['required', 'string']], ['code.required' => 'Saisissez le code reçu.']);
        $this->inscriptions->verifierCode($attente['identifiant'], (string) $request->input('code'), $request->ip());

        $utilisateur = $this->inscriptions->finaliser($attente);
        $request->session()->forget([self::SESSION, 'invitation']);

        if (! $utilisateur) {
            return redirect()->route('connexion')->withErrors(['identifiant' => 'Ce compte n’est plus accessible. Contactez VIVI.']);
        }

        Auth::login($utilisateur, remember: true);
        $request->session()->regenerate();

        // Nouveau compte : on revient sur la page d'où il venait (fiche, réservation) ;
        // sinon un client va à l'accueil chercher un lieu plutôt que sur une liste de réservations vide
        if ($attente['mode'] === 'inscription') {
            $bienvenue = 'Bienvenue sur VIVI, '.$utilisateur->prenom().' ! Votre compte est créé.';
            $parDefaut = $utilisateur->hasRole(Role::Client->value) && ! $utilisateur->hasAnyRole([Role::Proprietaire->value, Role::Gestionnaire->value])
                ? route('accueil') : $utilisateur->urlEspace();

            return redirect()->intended($parDefaut)->with('statut', $bienvenue);
        }

        return redirect()->intended($utilisateur->urlEspace());
    }

    public function deconnexion(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('accueil')->with('statut', 'Vous êtes déconnecté.');
    }
}
