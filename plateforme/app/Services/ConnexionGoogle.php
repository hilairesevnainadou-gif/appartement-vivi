<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\Bienvenue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Connexion et création de compte « avec Google », réservées aux clients.
 *
 *  - Site : OAuth 2.0 de Google (redirection vers Google, puis retour sur /connexion/google/retour avec un code
 *    échangé contre l'ID token). Laravel Socialite n'est pas utilisé : il dépend d'une version de firebase/php-jwt
 *    signalée par un avis de sécurité (refusée par Composer le 21/11/2026).
 *  - Application Android : Google Sign-In fournit un « ID token » que l'application envoie à POST /api/v1/auth/google ;
 *    il est vérifié auprès de Google (signature, destinataire = nos identifiants client, email vérifié).
 *  - Compte trouvé par son identifiant Google, sinon par son email : il est relié à Google.
 *  - Un compte propriétaire, gestionnaire ou de l'équipe VIVI ne peut pas utiliser Google : il garde le code par
 *    email ou SMS (accès à l'argent et aux logements). Un agent marketing, qui est aussi client, le peut.
 */
class ConnexionGoogle
{
    /** Rôles qui peuvent se connecter avec Google. */
    public const ROLES_AUTORISES = [Role::Client, Role::AgentMarketing];

    public function __construct(private readonly Parrainage $parrainage) {}

    public function actif(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    /**
     * Compte client correspondant au compte Google (créé au besoin).
     *
     * @return array{0: User, 1: bool} le compte et « nouveau compte »
     *
     * @throws ValidationException message pour la personne, sur « identifiant »
     */
    public function connecter(string $googleId, ?string $email, bool $emailVerifie, ?string $nom, ?string $invitation = null): array
    {
        $email = $email ? mb_strtolower(trim($email)) : null;
        if ($googleId === '' || ! $email || ! $emailVerifie) {
            $this->erreur('Votre compte Google n’a pas d’adresse email vérifiée. Utilisez la connexion par code.');
        }

        $existant = User::where('google_id', $googleId)->first() ?? User::where('email', $email)->first();
        if ($existant) {
            if (! $existant->actif) {
                $this->erreur('Ce compte est désactivé. Contactez VIVI pour le réactiver.');
            }
            $autorises = array_map(fn (Role $r) => $r->value, self::ROLES_AUTORISES);
            if ($existant->getRoleNames()->diff($autorises)->isNotEmpty()) {
                $this->erreur('La connexion avec Google est réservée aux clients. Pour votre espace professionnel, connectez-vous avec le code reçu par email ou par SMS.');
            }
            if ($existant->google_id && $existant->google_id !== $googleId) {
                $this->erreur('Ce compte VIVI est déjà relié à un autre compte Google. Connectez-vous avec le code reçu par email.');
            }
            $existant->forceFill(['google_id' => $googleId, 'email_verified_at' => $existant->email_verified_at ?? now()])->save();
            if ($existant->roles()->doesntExist()) {
                $existant->assignRole(Role::Client->value);
            }

            return [$existant, false];
        }

        $utilisateur = DB::transaction(function () use ($googleId, $email, $nom, $invitation) {
            $u = User::create(['name' => trim((string) $nom) ?: strtok($email, '@'), 'email' => $email, 'actif' => true]);
            $u->forceFill(['google_id' => $googleId, 'email_verified_at' => now()])->save();
            $u->assignRole(Role::Client->value);
            if ($this->parrainage->actif() && $invitation && $this->parrainage->parrainDuCode($invitation)) {
                $this->parrainage->rattacher($u, $invitation);
            }

            return $u;
        });

        try {
            $utilisateur->notify(new Bienvenue(Role::Client));
        } catch (Throwable $e) {
            Log::warning('Message de bienvenue non envoyé à l’utilisateur '.$utilisateur->id.' : '.$e->getMessage());
        }

        return [$utilisateur, true];
    }

    /* ---------- Site : OAuth 2.0 « code d'autorisation » (sans bibliothèque externe) ---------- */

    /** Adresse de la page Google ; $etat est un jeton aléatoire gardé en session (protection CSRF). */
    public function urlAutorisation(string $etat): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->urlRetour(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $etat,
            'prompt' => 'select_account',
        ]);
    }

    public function urlRetour(): string
    {
        return config('services.google.redirect') ?: route('connexion.google.retour');
    }

    /**
     * Échange le code reçu au retour contre l'ID token du compte, puis le vérifie.
     *
     * @return array{sub: string, email: ?string, email_verified: bool, name: ?string}
     */
    public function compteDuCode(string $code): array
    {
        try {
            $reponse = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => $this->urlRetour(),
                'grant_type' => 'authorization_code',
            ]);
        } catch (Throwable $e) {
            Log::warning('Échange du code Google impossible : '.$e->getMessage());
            $this->erreur('Google ne répond pas pour le moment. Réessayez dans un instant.');
        }
        if (! $reponse->successful() || ! is_string($reponse->json('id_token'))) {
            Log::warning('Code Google refusé : '.$reponse->body());
            $this->erreur('La connexion Google a échoué. Réessayez, ou utilisez le code par email ou SMS.');
        }

        return $this->verifierJeton($reponse->json('id_token'), 'identifiant');
    }

    /**
     * Vérifie un ID token Google envoyé par l'application Android, auprès de Google.
     *
     * @return array{sub: string, email: ?string, email_verified: bool, name: ?string}
     *
     * @throws ValidationException jeton refusé
     */
    public function verifierJeton(string $idToken, string $champ = 'id_token'): array
    {
        try {
            $reponse = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);
        } catch (Throwable $e) {
            Log::warning('Vérification Google impossible : '.$e->getMessage());
            $this->erreur('Google ne répond pas pour le moment. Réessayez dans un instant.', $champ);
        }
        $infos = $reponse->json() ?? [];
        $destinataires = array_filter(array_merge([config('services.google.client_id')], (array) config('services.google.clients_android', [])));

        $valide = $reponse->successful()
            && in_array($infos['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)
            && in_array($infos['aud'] ?? '', $destinataires, true)
            && (int) ($infos['exp'] ?? 0) > time();
        if (! $valide) {
            $this->erreur('La connexion Google a échoué. Réessayez.', $champ);
        }

        return [
            'sub' => (string) $infos['sub'],
            'email' => $infos['email'] ?? null,
            'email_verified' => filter_var($infos['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'name' => $infos['name'] ?? null,
        ];
    }

    private function erreur(string $message, string $champ = 'identifiant'): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }
}
