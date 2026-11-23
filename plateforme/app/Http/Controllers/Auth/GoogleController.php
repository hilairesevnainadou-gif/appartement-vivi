<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ConnexionGoogle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** « Continuer avec Google » sur la page de connexion : clients seulement (voir App\Services\ConnexionGoogle). */
class GoogleController extends Controller
{
    private const ETAT = 'google.etat';

    public function __construct(private readonly ConnexionGoogle $google) {}

    public function redirection(Request $request): RedirectResponse
    {
        if (! $this->google->actif()) {   // identifiants Google pas encore saisis dans .env
            return redirect()->route('connexion')->withErrors(['identifiant' => 'La connexion avec Google n’est pas encore activée. Utilisez le code reçu par email ou SMS.']);
        }
        $etat = Str::random(40);
        $request->session()->put(self::ETAT, $etat);

        return redirect()->away($this->google->urlAutorisation($etat));
    }

    public function retour(Request $request): RedirectResponse
    {
        abort_unless($this->google->actif(), 404);
        $etat = $request->session()->pull(self::ETAT);
        if ($request->filled('error')) {   // la personne a annulé sur la page Google
            return redirect()->route('connexion')->withErrors(['identifiant' => 'Connexion Google annulée.']);
        }
        // Le retour doit venir de la demande faite depuis ce navigateur
        if (! is_string($etat) || ! hash_equals($etat, (string) $request->query('state')) || ! $request->filled('code')) {
            return redirect()->route('connexion')->withErrors(['identifiant' => 'La connexion Google a expiré. Recommencez.']);
        }

        try {
            $compte = $this->google->compteDuCode((string) $request->query('code'));
            [$utilisateur, $nouveau] = $this->google->connecter($compte['sub'], $compte['email'], $compte['email_verified'], $compte['name'], $request->session()->get('invitation'));
        } catch (ValidationException $e) {
            return redirect()->route('connexion')->withErrors($e->errors());
        }

        $request->session()->forget('invitation');
        Auth::login($utilisateur, remember: true);
        $request->session()->regenerate();

        // Compte créé par Google (toujours un client) : retour à la page d'où il venait, sinon à l'accueil
        if ($nouveau) {
            return redirect()->intended(route('accueil'))->with('statut', 'Bienvenue sur VIVI, '.$utilisateur->prenom().' ! Votre compte est créé.');
        }

        return redirect()->intended($utilisateur->urlEspace());
    }
}
