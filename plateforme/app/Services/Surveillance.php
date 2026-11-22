<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\AlerteSysteme;
use App\Models\User;
use App\Notifications\AlerteSystemeNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * Surveillance du système : détecte les pannes et les attaques, crée une alerte, prévient l'équipe.
 *
 *  - Les incidents identiques (même type, même source) sont regroupés dans une alerte ouverte : compteur.
 *  - Avertissement et critique : notification aux administrateurs (cloche de l'administration, email ;
 *    SMS en plus si critique), au plus une fois toutes les 30 min pour la même alerte.
 *  - Si la base de données elle-même est en panne : journal Laravel et email de secours.
 */
class Surveillance
{
    public const TYPES = [
        'erreurs_serveur' => 'Erreurs en série',
        'connexion_admin' => 'Tentatives de connexion répétées (administration)',
        'codes_faux' => 'Codes de connexion faux en série',
        'exploration' => 'Exploration suspecte du site',
        'chemin_attaque' => 'Tentative d’accès à un fichier sensible',
        'limite' => 'Trop de requêtes',
        'webhook' => 'Webhook de paiement à signature invalide',
        'paiements' => 'Échecs de paiement en série',
        'taches' => 'Tâches en échec',
        'planificateur' => 'Tâches planifiées arrêtées',
        'disque' => 'Espace disque faible',
        'base' => 'Base de données injoignable',
    ];

    /** Chemins visés par les robots d'attaque : un seul accès suffit pour alerter. */
    private const CHEMINS_ATTAQUE = '#(^|/)(\.env|\.git|wp-login\.php|wp-admin|xmlrpc\.php|phpmyadmin|pma|adminer|config\.php|vendor/phpunit|cgi-bin|\.aws|shell\.php|etc/passwd)#i';

    /* ---------------------------------------------------------------------
     | Signalement
     * --------------------------------------------------------------------- */

    public function signaler(string $type, string $niveau, string $message, ?string $source = null, array $details = []): ?AlerteSysteme
    {
        try {
            $alerte = AlerteSysteme::ouvertes()->where('type', $type)->where('source', $source)
                ->where('derniere_le', '>=', now()->subDay())->latest('id')->first();

            if ($alerte) {
                $alerte->forceFill([
                    'occurrences' => $alerte->occurrences + 1, 'derniere_le' => now(), 'message' => $message,
                    // Un incident qui s'aggrave fait monter le niveau, jamais baisser
                    'niveau' => $niveau === AlerteSysteme::CRITIQUE ? $niveau : $alerte->niveau,
                    'details' => array_merge($alerte->details ?? [], $details),
                ])->save();
            } else {
                $alerte = AlerteSysteme::create([
                    'type' => $type, 'niveau' => $niveau, 'titre' => self::TYPES[$type] ?? Str::headline($type),
                    'message' => $message, 'source' => $source, 'details' => $details ?: null, 'derniere_le' => now(),
                ]);
            }
            activity('surveillance')->performedOn($alerte)->event($type)->withProperties(['niveau' => $niveau, 'source' => $source])->log($alerte->titre);
            $this->prevenir($alerte);

            return $alerte;
        } catch (Throwable $e) {
            // La base est peut-être elle-même en panne : on garde une trace et on prévient par email directement
            $this->secours($type, $message, $e);

            return null;
        }
    }

    /** Compte un événement sur une fenêtre glissante et dit si le seuil (config vivi.surveillance) est atteint. */
    public function compter(string $regle, string $source): bool
    {
        [$seuil, $secondes] = config('vivi.surveillance.'.$regle, [PHP_INT_MAX, 60]);
        $cle = 'surveillance:'.$regle.':'.sha1($source);
        RateLimiter::hit($cle, $secondes);
        $nombre = RateLimiter::attempts($cle);

        // Une alerte au passage du seuil, puis une à chaque fois que le nombre double (évite d'inonder l'équipe)
        return $nombre === $seuil || ($nombre > $seuil && ($nombre & ($nombre - 1)) === 0);
    }

    /* ---------------------------------------------------------------------
     | Détecteurs (appelés par l'application)
     * --------------------------------------------------------------------- */

    /** Erreur 500 : alerte critique si elles s'enchaînent. */
    public function erreur(Throwable $e): void
    {
        if ($this->compter('erreurs_serveur', 'site')) {
            [$seuil, $secondes] = config('vivi.surveillance.erreurs_serveur');
            $this->signaler('erreurs_serveur', AlerteSysteme::CRITIQUE,
                "Au moins {$seuil} erreurs en ".intdiv($secondes, 60).' min. Dernière : '.class_basename($e).' : '.Str::limit($e->getMessage(), 180),
                'site', ['derniere_erreur' => class_basename($e), 'fichier' => basename($e->getFile()).':'.$e->getLine(), 'adresse' => request()?->fullUrl()]);
        }
    }

    /** Réponse envoyée à un visiteur : chemins d'attaque, exploration (404 en série), limites dépassées (429). */
    public function requete(string $ip, string $chemin, int $statut): void
    {
        if (preg_match(self::CHEMINS_ATTAQUE, $chemin)) {
            $this->signaler('chemin_attaque', AlerteSysteme::AVERTISSEMENT,
                "Accès à « /{$chemin} » : ce fichier n’existe pas sur VIVI, mais les robots d’attaque le cherchent.", $ip, ['chemin' => '/'.$chemin]);

            return;
        }
        if ($statut === 404 && $this->compter('pages_introuvables', $ip)) {
            $this->signaler('exploration', AlerteSysteme::AVERTISSEMENT,
                'De nombreuses pages inexistantes demandées depuis la même adresse : le site est probablement exploré par un robot.', $ip, ['dernier_chemin' => '/'.$chemin]);
        }
        if ($statut === 429 && $this->compter('limites', $ip)) {
            $this->signaler('limite', AlerteSysteme::AVERTISSEMENT,
                'Cette adresse dépasse sans cesse les limites (connexion, codes, scanner) : tentative d’abus probable.', $ip, ['dernier_chemin' => '/'.$chemin]);
        }
    }

    public function connexionAdminRatee(string $ip, string $identifiant): void
    {
        if ($this->compter('connexions_admin', $ip)) {
            $this->signaler('connexion_admin', AlerteSysteme::CRITIQUE,
                'Mots de passe faux en série sur la connexion de l’administration (dernier identifiant essayé : '.($identifiant ?: '—').').', $ip);
        }
    }

    public function codeFaux(string $ip): void
    {
        if ($this->compter('codes_faux', $ip)) {
            $this->signaler('codes_faux', AlerteSysteme::AVERTISSEMENT,
                'Codes de connexion faux en série depuis la même adresse : quelqu’un essaie peut-être de deviner un code.', $ip);
        }
    }

    public function webhookInvalide(string $ip): void
    {
        $this->signaler('webhook', AlerteSysteme::CRITIQUE,
            'Un appel au webhook de paiement a une signature invalide : il ne vient pas de FedaPay. Aucun paiement n’a été validé.', $ip);
    }

    public function paiementEchoue(): void
    {
        if ($this->compter('paiements_echoues', 'paiements')) {
            $this->signaler('paiements', AlerteSysteme::AVERTISSEMENT,
                'Beaucoup de paiements échouent en ce moment : vérifiez FedaPay (clés, service, Mobile Money).', 'paiements');
        }
    }

    /**
     * Contrôle régulier (commande vivi:surveiller, toutes les 5 min) : base, tâches en échec, disque.
     *
     * @return list<string> problèmes constatés
     */
    public function controler(): array
    {
        Cache::put('vivi:pulsation', now()->toIso8601String(), now()->addDay());   // preuve que les tâches planifiées tournent
        $problemes = [];

        try {
            DB::select('select 1');
        } catch (Throwable $e) {
            $this->secours('base', 'La base de données ne répond pas.', $e);

            return ['base'];
        }

        $echecs = DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count();
        if ($echecs > 0) {
            $problemes[] = 'taches';
            $this->signaler('taches', $echecs >= 10 ? AlerteSysteme::CRITIQUE : AlerteSysteme::AVERTISSEMENT,
                "{$echecs} tâche(s) en arrière-plan ont échoué dans la dernière heure (envoi de SMS, d’emails…).", 'file', ['echecs' => $echecs]);
        }

        $libre = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());
        if ($libre && $total && $libre / $total * 100 < (int) config('vivi.surveillance.disque_minimum_pourcent')) {
            $problemes[] = 'disque';
            $this->signaler('disque', AlerteSysteme::CRITIQUE,
                'Il reste '.round($libre / $total * 100, 1).' % d’espace disque : photos, journaux et sauvegardes risquent de ne plus s’enregistrer.', 'serveur');
        }

        return $problemes;
    }

    /** Les tâches planifiées tournent-elles ? (vérifié à l'ouverture de l'administration, en production) */
    public function verifierPlanificateur(): void
    {
        if (! config('vivi.surveillance.verifier_planificateur')) {
            return;
        }
        $derniere = Cache::get('vivi:pulsation');
        if (! $derniere || now()->diffInMinutes($derniere, true) > 15) {
            $this->signaler('planificateur', AlerteSysteme::CRITIQUE,
                'Les tâches planifiées ne tournent plus depuis '.($derniere ? 'le '.\Illuminate\Support\Carbon::parse($derniere)->format('d/m H:i') : 'longtemps')
                .' : créneaux non libérés, rappels et clôtures de séjour en attente. Vérifiez le cron « schedule:run ».', 'serveur');
        }
    }

    /* ---------------------------------------------------------------------
     | Outils
     * --------------------------------------------------------------------- */

    private function prevenir(AlerteSysteme $alerte): void
    {
        if ($alerte->niveau === AlerteSysteme::INFO) {
            return;
        }
        $delai = (int) config('vivi.surveillance.renotifier_minutes', 30);
        if ($alerte->notifiee_le && $alerte->notifiee_le->gt(now()->subMinutes($delai))) {
            return;
        }
        $destinataires = User::where('actif', true)->role(Role::Administrateur->value)->get();
        if ($destinataires->isEmpty()) {
            return;
        }
        $alerte->forceFill(['notifiee_le' => now()])->save();
        $envoyer = function () use ($destinataires, $alerte) {
            try {
                Notification::send($destinataires, new AlerteSystemeNotification($alerte));
            } catch (Throwable $e) {
                Log::error('Alerte '.$alerte->id.' non envoyée : '.$e->getMessage());
            }
        };
        // Sur le site : envoi après la réponse, pour ne jamais ralentir le visiteur (ni l'attaquant…)
        app()->runningInConsole() ? $envoyer() : \Illuminate\Support\defer($envoyer);
    }

    private function secours(string $type, string $message, Throwable $cause): void
    {
        Log::critical('[VIVI surveillance] '.(self::TYPES[$type] ?? $type).' : '.$message, ['cause' => $cause->getMessage()]);
        $email = config('vivi.surveillance.email_secours');
        if (! $email || Cache::has('surveillance:secours:'.$type)) {
            return;
        }
        try {
            Cache::put('surveillance:secours:'.$type, true, now()->addMinutes(30));
            Mail::raw("Alerte VIVI : {$message}\n\nCause : ".$cause->getMessage()."\nHeure : ".now()->format('d/m/Y H:i'),
                fn ($m) => $m->to($email)->subject('[VIVI] Alerte critique : '.(self::TYPES[$type] ?? $type)));
        } catch (Throwable) {
            // Rien de plus à faire : l'incident est dans le journal Laravel
        }
    }
}
