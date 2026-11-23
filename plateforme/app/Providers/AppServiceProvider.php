<?php

namespace App\Providers;

use App\Models\Abonnement;
use App\Models\Reservation;
use App\Models\User;
use App\Paiement\FedaPayPasserelle;
use App\Paiement\Passerelle;
use App\Paiement\SimulationPasserelle;
use App\Services\Disponibilites;
use App\Sms\JournalSms;
use App\Sms\Sms;
use App\Sms\SmsTo;
use Filament\Pages\BasePage;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Pilote SMS choisi dans config/vivi.php (VIVI_SMS_PILOTE)
        $this->app->bind(Sms::class, fn () => match (config('vivi.sms.pilote')) {
            'smsto' => new SmsTo(
                (string) config('vivi.sms.smsto.cle'),
                (string) config('vivi.sms.smsto.expediteur'),
                (string) config('vivi.sms.smsto.url'),
            ),
            default => new JournalSms,
        });

        // Passerelle de paiement choisie dans config/vivi.php (VIVI_PAIEMENT_PILOTE).
        // La simulation est refusée en production : sans clés FedaPay, aucun paiement n'est possible.
        $this->app->bind(Passerelle::class, function () {
            if (config('vivi.paiement.pilote') === 'fedapay') {
                return new FedaPayPasserelle(
                    (string) config('vivi.paiement.fedapay.cle_secrete'),
                    (string) config('vivi.paiement.fedapay.environnement'),
                );
            }
            abort_if(app()->isProduction(), 503, 'Paiement non configuré (VIVI_PAIEMENT_PILOTE=fedapay attendu en production).');

            return new SimulationPasserelle;
        });

        // Un seul moteur de disponibilités par requête : son cache sert à toutes les vérifications
        $this->app->scoped(Disponibilites::class);
    }

    public function boot(): void
    {
        // Développement : le site tourne sur http://localhost:8049 (APP_URL) et l'application Android
        // l'appelle par l'IP du PC sur le Wi-Fi. Les liens (photos, paiement…) suivent alors l'adresse appelée.
        if ($this->app->isLocal() && ! $this->app->runningInConsole()) {
            $racine = request()->getSchemeAndHttpHost();
            if ($racine !== rtrim((string) config('app.url'), '/')) {
                config(['app.url' => $racine, 'filesystems.disks.public.url' => $racine.'/storage']);
                \Illuminate\Support\Facades\URL::forceRootUrl($racine);
            }
        }

        // Site appelé en HTTPS alors que APP_URL est resté en « http:// » : les liens et les photos suivent en HTTPS.
        // Sans cela l'application Android, qui refuse le trafic en clair, n'affiche aucune photo.
        if (! $this->app->runningInConsole() && request()->isSecure()) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
            config(['filesystems.disks.public.url' => preg_replace('#^http://#i', 'https://', (string) config('filesystems.disks.public.url'))]);
        }

        // L'administrateur a toutes les permissions : il ne peut pas se retirer lui-même l'accès
        \Illuminate\Support\Facades\Gate::before(fn ($user) => $user->hasRole(\App\Enums\Role::Administrateur->value) ? true : null);

        // Journal d'activité : connexions et déconnexions
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, fn ($e) => activity('connexion')
            ->causedBy($e->user)->performedOn($e->user)->withProperties(['ip' => request()->ip(), 'navigateur' => mb_substr((string) request()->userAgent(), 0, 180)])
            ->event('connexion')->log('Connexion'));
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Logout::class, fn ($e) => $e->user ? activity('connexion')
            ->causedBy($e->user)->performedOn($e->user)->event('deconnexion')->log('Déconnexion') : null);
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Failed::class, function ($e) {
            activity('connexion')
                ->withProperties(['identifiant' => (string) ($e->credentials['email'] ?? ''), 'ip' => request()->ip()])
                ->event('echec')->log('Échec de connexion à l’administration');
            // Surveillance : mots de passe faux en série depuis une même adresse
            app(\App\Services\Surveillance::class)->connexionAdminRatee((string) request()->ip(), (string) ($e->credentials['email'] ?? ''));
        });

        // Tous les tableaux de l'administration : une fiche par ligne sur mobile, 25 lignes par page
        Table::configureUsing(fn (Table $table) => $table
            ->stackedOnMobile()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25));

        // Formulaires : boutons « Enregistrer » et « Annuler » toujours visibles en bas de l'écran
        BasePage::stickyFormActions();

        // Pagination du site public (Bootstrap, comme la maquette)
        Paginator::useBootstrapFive();

        // Noms courts et stables pour les paiements (colonne paiements.payable_type)
        Relation::enforceMorphMap([
            'reservation' => Reservation::class,
            'abonnement' => Abonnement::class,
            'user' => User::class,
            // Éléments suivis par le journal d'activité (sujet d'une entrée)
            'logement' => \App\Models\Logement::class,
            'retrait' => \App\Models\Retrait::class,
            'formule_abonnement' => \App\Models\FormuleAbonnement::class,
            'service' => \App\Models\Service::class,
            'equipement' => \App\Models\Equipement::class,
            'bonus' => \App\Models\Bonus::class,
            'parametre' => \App\Models\Parametre::class,
            'avis' => \App\Models\Avis::class,
            'paiement' => \App\Models\Paiement::class,
            'blocage' => \App\Models\Blocage::class,
            'tarif' => \App\Models\Tarif::class,
            'role' => \Spatie\Permission\Models\Role::class,
            'alerte' => \App\Models\AlerteSysteme::class,
            'offre' => \App\Models\Offre::class,
            'publicite' => \App\Models\Publicite::class,
            'publicite_paiement' => \App\Models\PublicitePaiement::class,
        ]);

        // En développement : signale les relations chargées une par une (requêtes N+1)
        Model::preventLazyLoading(! $this->app->isProduction());

        // Connexion par code : limite les envois (coût des SMS) et les essais de code
        RateLimiter::for('envoi-code', fn (Request $request) => [
            Limit::perHour(10)->by('ip:'.$request->ip()),
            Limit::perHour(5)->by('id:'.(mb_strtolower((string) $request->input('identifiant')) ?: ($request->user()?->getKey() ?? $request->ip()))),
        ]);
        RateLimiter::for('verification-code', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // API de l'application mobile : par compte connecté, sinon par adresse
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->getKey() ? 'u:'.$request->user()->getKey() : 'ip:'.$request->ip()));
    }
}
