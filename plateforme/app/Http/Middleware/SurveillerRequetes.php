<?php

namespace App\Http\Middleware;

use App\Services\Surveillance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Observe chaque réponse (une fois envoyée) pour la surveillance : chemins visés par les robots d'attaque,
 * pages inexistantes en série (exploration), limites de requêtes dépassées. Ne ralentit pas le visiteur.
 */
class SurveillerRequetes
{
    public function handle(Request $request, Closure $next): Response
    {
        // Robot qui a insisté sur des chemins d'attaque : refusé pendant le blocage, sans rien exécuter
        if (Surveillance::estBloquee((string) $request->ip())) {
            $request->attributes->set('vivi_robot_bloque', true);

            return response('Accès refusé.', 403);
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->attributes->get('vivi_robot_bloque')) {
            return;
        }
        $statut = $response->getStatusCode();
        if (! in_array($statut, [403, 404, 405, 429], true)) {
            return;
        }
        try {
            app(Surveillance::class)->requete((string) $request->ip(), ltrim($request->path(), '/'), $statut);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
