<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Controllers\Api\V1\Pro\FinancesController;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Format;
use App\Services\AgentsMarketing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Espace de l'agent marketing dans l'application : logements promus avec leur lien d'affiliation, commissions,
 * solde et retraits (POST /agent/retraits, même fonctionnement que celui du propriétaire).
 */
class EspaceController extends Controller
{
    /** GET /agent */
    public function __invoke(Request $request, AgentsMarketing $agents): JsonResponse
    {
        $agent = $request->user();
        $espace = $agents->espace($agent);
        $mouvements = $agent->mouvementsSolde()->with('reservation:id,reference')->latest('id')->paginate(20);

        return response()->json([
            'data' => [
                'code_agent' => $espace['code'],
                'solde' => $espace['solde'],
                'ce_mois' => $espace['ce_mois'],
                'total_gagne' => $espace['total'],
                'sejours' => $espace['sejours'],
                'a_venir' => $espace['a_venir'],
                'retrait_minimum' => 1000,
                'logements' => $espace['logements']->map(fn (array $l) => [
                    'id' => $l['logement']->id,
                    'slug' => $l['logement']->slug,
                    'titre' => $l['logement']->titre,
                    'quartier' => $l['logement']->quartier,
                    'ville' => $l['logement']->ville,
                    'photo' => $l['logement']->urlPhoto(),
                    'proprietaire' => $l['logement']->proprietaire?->name,
                    'en_ligne' => $l['visible'],
                    'lien' => $l['lien'],
                    'part' => $l['logement']->marketing_type ? [
                        'type' => $l['logement']->marketing_type->value,
                        'valeur' => (float) $l['logement']->marketing_valeur,
                        'libelle' => $l['part'],
                    ] : null,
                    'sejours' => $l['sejours'],
                    'a_venir' => $l['a_venir'],
                    'gains' => $l['gains'],
                ])->values(),
                'mouvements' => $mouvements->getCollection()->map(fn ($m) => [
                    'id' => $m->id, 'type' => Format::enum($m->type), 'montant' => (int) $m->montant, 'libelle' => $m->libelle,
                    'reservation' => $m->reservation?->reference, 'le' => Format::date($m->created_at),
                ]),
                'retraits' => $agent->retraits()->latest('id')->limit(20)->get()->map(fn ($r) => FinancesController::retrait($r)),
            ],
            'meta' => ['page' => $mouvements->currentPage(), 'derniere_page' => $mouvements->lastPage(), 'total' => $mouvements->total()],
        ]);
    }
}
