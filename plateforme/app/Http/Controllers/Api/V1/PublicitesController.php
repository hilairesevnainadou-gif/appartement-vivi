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

    /** support=site : vue sur le site web (comptée à part pour le rapport de l'annonceur). */
    public function vue(Request $request, Publicite $publicite): Response
    {
        return $this->compter($request, $publicite, 'vues');
    }

    public function clic(Request $request, Publicite $publicite): Response
    {
        return $this->compter($request, $publicite, 'clics');
    }

    /** Une publicité arrêtée entre-temps n'est plus comptée, sans erreur pour l'application. */
    private function compter(Request $request, Publicite $publicite, string $colonne): Response
    {
        if (Publicites::actives() && $publicite->estDiffusee()) {
            $publicite->compter($colonne, $request->input('support') === Publicite::SITE ? Publicite::SITE : Publicite::APPLICATION);
        }

        return response()->noContent();
    }
}
