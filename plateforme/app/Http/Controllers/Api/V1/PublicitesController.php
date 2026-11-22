<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Publicite;
use App\Support\Publicites;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Espaces publicitaires de l'application (accessibles sans connexion) : contenu, vues et clics. */
class PublicitesController extends Controller
{
    /** GET /publicites?ville= */
    public function index(Request $request): JsonResponse
    {
        $ville = $request->query('ville');

        return response()->json(['data' => Publicites::pourApplication(is_string($ville) && trim($ville) !== '' ? trim($ville) : null)]);
    }

    public function vue(Publicite $publicite): Response
    {
        return $this->compter($publicite, 'vues');
    }

    public function clic(Publicite $publicite): Response
    {
        return $this->compter($publicite, 'clics');
    }

    /** Une publicité arrêtée entre-temps n'est plus comptée, sans erreur pour l'application. */
    private function compter(Publicite $publicite, string $colonne): Response
    {
        if (Publicites::actives() && $publicite->estDiffusee()) {
            $publicite->compter($colonne);
        }

        return response()->noContent();
    }
}
