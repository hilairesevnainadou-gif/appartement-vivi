<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UtilisateurResource;
use App\Services\Inscriptions;
use App\Support\Telephone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Connexion de l'application mobile, sans mot de passe, comme sur le site :
 *  1. POST /auth/code      identifiant (email ou téléphone) → un code à 6 chiffres est envoyé, on reçoit un « jeton de demande » ;
 *  2. POST /auth/verifier  jeton de demande + code          → jeton d'accès (Bearer) à garder dans le stockage sécurisé du téléphone.
 * Le jeton de demande remplace la session du site ; il ne sert qu'à cette étape et expire avec le code.
 */
class AuthController extends Controller
{
    public function __construct(private readonly Inscriptions $inscriptions) {}

    public function demanderCode(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'mode' => ['required', Rule::in(['connexion', 'inscription'])],
            'identifiant' => ['required', 'string', 'max:255'],
            'pays' => ['nullable', Rule::in(['benin', 'international'])],   // « benin » : numéro ou email ; « international » : email seulement
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

        $jeton = Str::random(48);
        Cache::put($this->cle($jeton), [
            'identifiant' => $identifiant,
            'canal' => $canal,
            'mode' => $donnees['mode'],
            'nom' => $donnees['nom'] ?? null,
            'role' => $donnees['role'] ?? null,
            'invitation' => $invitation ?: null,
        ], now()->addMinutes((int) config('vivi.connexion.code_validite_minutes') + 5));

        return response()->json([
            'demande' => $jeton,
            'canal' => $canal,
            'destination' => $canal === 'sms' ? Telephone::masque($identifiant) : $identifiant,
            'renvoi_secondes' => (int) config('vivi.connexion.renvoi_secondes'),
            'validite_minutes' => (int) config('vivi.connexion.code_validite_minutes'),
            'code_dev' => $this->inscriptions->codeAAfficher($code),   // local seulement, null ailleurs
        ], 201);
    }

    public function renvoyer(Request $request): JsonResponse
    {
        $attente = $this->attente($request);
        $code = $this->inscriptions->envoyer($attente['identifiant'], $attente['canal'], $request->ip(), champ: 'code');

        return response()->json([
            'message' => 'Un nouveau code vient de vous être envoyé.',
            'code_dev' => $this->inscriptions->codeAAfficher($code),
        ]);
    }

    public function verifier(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
            'appareil' => ['nullable', 'string', 'max:100'],
        ], ['code.required' => 'Saisissez le code reçu.']);
        $attente = $this->attente($request);

        $this->inscriptions->verifierCode($attente['identifiant'], (string) $request->input('code'), $request->ip());
        Cache::forget($this->cle((string) $request->input('demande')));

        $utilisateur = $this->inscriptions->finaliser($attente);
        if (! $utilisateur) {
            throw ValidationException::withMessages(['identifiant' => 'Ce compte n’est plus accessible. Contactez VIVI.']);
        }

        $nom = trim((string) $request->input('appareil')) ?: 'Application mobile';
        $jeton = $utilisateur->createToken($nom, ['*'], now()->addDays((int) config('vivi.api.jeton_validite_jours')));

        return response()->json([
            'jeton' => $jeton->plainTextToken,
            'type' => 'Bearer',
            'expire_le' => $jeton->accessToken->expires_at?->format(DATE_ATOM),
            'nouveau_compte' => $attente['mode'] === 'inscription',
            'utilisateur' => new UtilisateurResource($utilisateur),
        ]);
    }

    /**
     * POST /auth/google { id_token, appareil?, invitation? } : connexion ou création d'un compte CLIENT avec Google.
     * L'application obtient id_token avec Google Sign-In (Credential Manager), en demandant l'identifiant client Web.
     */
    public function google(Request $request, \App\Services\ConnexionGoogle $google): JsonResponse
    {
        abort_unless($google->actif(), 404, 'La connexion avec Google n’est pas disponible.');
        $donnees = $request->validate([
            'id_token' => ['required', 'string', 'max:4096'],
            'appareil' => ['nullable', 'string', 'max:100'],
            'invitation' => ['nullable', 'string', 'max:20'],
        ]);

        $compte = $google->verifierJeton($donnees['id_token']);
        [$utilisateur, $nouveau] = $google->connecter($compte['sub'], $compte['email'], $compte['email_verified'], $compte['name'], $donnees['invitation'] ?? null);

        $jeton = $utilisateur->createToken(trim((string) ($donnees['appareil'] ?? '')) ?: 'Application mobile', ['*'], now()->addDays((int) config('vivi.api.jeton_validite_jours')));

        return response()->json([
            'jeton' => $jeton->plainTextToken,
            'type' => 'Bearer',
            'expire_le' => $jeton->accessToken->expires_at?->format(DATE_ATOM),
            'nouveau_compte' => $nouveau,
            'utilisateur' => new UtilisateurResource($utilisateur),
        ]);
    }

    public function moi(Request $request): UtilisateurResource
    {
        return new UtilisateurResource($request->user());
    }

    /** Déconnexion de cet appareil seulement (le jeton utilisé est supprimé). */
    public function deconnexion(Request $request): JsonResponse
    {
        // {jeton_push} facultatif : ce téléphone ne reçoit plus les notifications de ce compte
        if (is_string($request->input('jeton_push')) && $request->input('jeton_push') !== '') {
            $request->user()->appareils()->where('jeton', $request->input('jeton_push'))->delete();
        }
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Vous êtes déconnecté.']);
    }

    /** Déconnexion de tous les appareils (téléphone perdu, par exemple). */
    public function deconnexionPartout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();
        $request->user()->appareils()->delete();

        return response()->json(['message' => 'Vous êtes déconnecté de tous vos appareils.']);
    }

    /** @return array{identifiant: string, canal: string, mode: string, nom: ?string, role: ?string, invitation: ?string} */
    private function attente(Request $request): array
    {
        $jeton = (string) $request->validate(['demande' => ['required', 'string', 'max:100']])['demande'];

        return Cache::get($this->cle($jeton))
            ?? throw ValidationException::withMessages(['demande' => 'Cette demande a expiré. Saisissez de nouveau votre email ou votre téléphone.']);
    }

    private function cle(string $jeton): string
    {
        return 'api-connexion:'.hash('sha256', $jeton);
    }
}
