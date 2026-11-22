<?php

namespace App\Services;

use App\Models\Appareil;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Notifications push vers l'application Android, par Firebase Cloud Messaging (API HTTP v1).
 *
 * Réglage (.env) : FIREBASE_PROJECT_ID et FIREBASE_CREDENTIALS (chemin du fichier JSON du compte de service,
 * Console Firebase › Paramètres du projet › Comptes de service › Générer une nouvelle clé privée).
 * Sans ce réglage, rien n'est envoyé : les notifications restent dans l'espace Notifications (l'application
 * les lit avec GET /api/v1/notifications).
 *
 * Le jeton d'accès Google est signé ici (JWT RS256 avec openssl) : pas de dépendance supplémentaire.
 * Un téléphone dont le jeton n'est plus valable (application désinstallée) est retiré automatiquement.
 */
class NotificationsPush
{
    private const PORTEE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const CACHE_JETON = 'firebase.jeton-acces';

    public function actif(): bool
    {
        return filled(config('services.firebase.projet')) && $this->compteService() !== null;
    }

    /**
     * Envoie le message à tous les téléphones de la personne. Ne lève jamais d'erreur : un push raté ne doit
     * pas empêcher une réservation ou un paiement.
     *
     * @param  array<string, scalar|null>  $donnees  lues par l'application (écran à ouvrir, référence…)
     * @return int nombre de téléphones atteints
     */
    public function envoyer(User $user, string $titre, string $texte, array $donnees = []): int
    {
        if (! $this->actif()) {
            return 0;
        }
        $atteints = 0;
        foreach ($user->appareils()->get() as $appareil) {
            try {
                $atteints += $this->envoyerA($appareil, $titre, $texte, $donnees) ? 1 : 0;
            } catch (Throwable $e) {
                Log::warning('Notification push non envoyée', ['appareil' => $appareil->id, 'erreur' => $e->getMessage()]);
            }
        }

        return $atteints;
    }

    private function envoyerA(Appareil $appareil, string $titre, string $texte, array $donnees): bool
    {
        // FCM n'accepte que des chaînes dans « data »
        $donnees = array_map(fn ($v) => (string) $v, array_filter($donnees, fn ($v) => $v !== null && $v !== ''));

        $reponse = Http::withToken($this->jetonAcces())->acceptJson()->timeout(10)
            ->post('https://fcm.googleapis.com/v1/projects/'.config('services.firebase.projet').'/messages:send', ['message' => [
                'token' => $appareil->jeton,
                'notification' => ['title' => $titre, 'body' => $texte],
                'data' => (object) $donnees,
                'android' => ['priority' => 'high', 'notification' => ['channel_id' => 'vivi_alertes', 'sound' => 'default']],
            ]]);

        if ($reponse->successful()) {
            return true;
        }
        // Jeton périmé (application désinstallée, données effacées) : le téléphone est oublié
        $code = collect($reponse->json('error.details', []))->pluck('errorCode')->filter()->first();
        if ($reponse->status() === 404 || in_array($code, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
            $appareil->delete();

            return false;
        }
        if ($reponse->status() === 401) {
            Cache::forget(self::CACHE_JETON);
        }
        throw new RuntimeException('Firebase a répondu '.$reponse->status().' : '.mb_substr($reponse->body(), 0, 300));
    }

    /** Jeton OAuth du compte de service, gardé 50 minutes (il est valable une heure). */
    private function jetonAcces(): string
    {
        return Cache::remember(self::CACHE_JETON, now()->addMinutes(50), function () {
            $compte = $this->compteService();
            $urlJeton = $compte['token_uri'] ?? 'https://oauth2.googleapis.com/token';
            $maintenant = time();
            $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $entete = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $contenu = $b64(json_encode([
                'iss' => $compte['client_email'],
                'scope' => self::PORTEE,
                'aud' => $urlJeton,
                'iat' => $maintenant,
                'exp' => $maintenant + 3600,
            ]));
            if (! openssl_sign($entete.'.'.$contenu, $signature, $compte['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Clé privée Firebase illisible.');
            }

            $reponse = Http::asForm()->timeout(10)->post($urlJeton, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $entete.'.'.$contenu.'.'.$b64($signature),
            ]);
            if (! $reponse->successful() || ! $reponse->json('access_token')) {
                throw new RuntimeException('Jeton Firebase refusé : '.$reponse->status());
            }

            return (string) $reponse->json('access_token');
        });
    }

    /** @return array{client_email: string, private_key: string, token_uri?: string}|null */
    private function compteService(): ?array
    {
        $chemin = (string) config('services.firebase.compte_service');
        if ($chemin === '') {
            return null;
        }
        $absolu = str_starts_with($chemin, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $chemin);
        $chemin = $absolu ? $chemin : base_path($chemin);
        if (! is_file($chemin)) {
            return null;
        }
        $compte = json_decode((string) file_get_contents($chemin), true);

        return is_array($compte) && isset($compte['client_email'], $compte['private_key']) ? $compte : null;
    }
}
