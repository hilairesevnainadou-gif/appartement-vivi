<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // role:proprietaire|gestionnaire sur une route = réservée à ces rôles
        $middleware->alias(['role' => RoleMiddleware::class]);

        // Visiteur non connecté qui ouvre une page protégée → page de connexion
        $middleware->redirectGuestsTo(fn () => route('connexion'));
        // Personne déjà connectée qui ouvre la page de connexion → son espace
        $middleware->redirectUsersTo(fn () => route('espace'));

        // FedaPay appelle le webhook sans jeton CSRF : il est protégé par sa signature (X-FEDAPAY-SIGNATURE)
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        // Surveillance : chemins d'attaque, pages inexistantes en série, limites dépassées
        $middleware->append(\App\Http\Middleware\SurveillerRequetes::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API mobile : toujours du JSON, même sans en-tête Accept, avec des messages en français
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (Throwable $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            $message = match (true) {
                $e instanceof \Illuminate\Auth\AuthenticationException => [401, 'Connectez-vous pour continuer.'],
                $e instanceof \Spatie\Permission\Exceptions\UnauthorizedException => [403, 'Cet espace n’est pas accessible avec votre compte.'],
                $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException,
                $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException => [404, 'Introuvable.'],
                $e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException => [429, 'Trop de demandes. Patientez un instant puis réessayez.'],
                default => null,
            };

            return $message ? response()->json(['message' => $message[1]], $message[0], $e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException ? $e->getHeaders() : []) : null;
        });

        // Surveillance : les erreurs 500 en série déclenchent une alerte critique
        $exceptions->report(function (Throwable $e) {
            $http = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
            if (! $http || $e->getStatusCode() >= 500) {
                try {
                    app(\App\Services\Surveillance::class)->erreur($e);
                } catch (Throwable) {
                    // la surveillance ne doit jamais empêcher le rapport d'erreur normal
                }
            }
        });
    })->create();
