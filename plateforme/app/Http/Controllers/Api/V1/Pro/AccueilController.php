<?php

namespace App\Http\Controllers\Api\V1\Pro;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Format;
use App\Http\Resources\Api\ReservationProResource;
use App\Models\Reservation;
use App\Services\ArriveeRefusee;
use App\Services\Arrivees;
use App\Services\TableauDeBord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Tableau de bord et accueil des clients (scanner du QR Code ou code de secours),
 * pour le propriétaire et le gestionnaire.
 */
class AccueilController extends Controller
{
    public function __construct(private readonly Arrivees $arrivees) {}

    public function tableauDeBord(Request $request, TableauDeBord $tableau): JsonResponse
    {
        $d = $tableau->donnees($request->user());

        return response()->json(['data' => [
            'est_proprietaire' => $d['estProprietaire'],
            'arrivees_du_jour' => ReservationProResource::collection($d['arrivees'])->resolve($request),
            'prochaines' => ReservationProResource::collection($d['prochaines'])->resolve($request),
            'revenus' => [
                'mois' => $d['revenusMois'],
                'mois_precedent' => $d['revenusMoisPrecedent'],
                'sept_jours' => $d['revenus7j']->map(fn ($j) => ['jour' => Format::jour($j['jour']), 'montant' => $j['montant']])->values(),
            ],
            'occupation' => ['mois' => $d['occupation'], 'mois_precedent' => $d['occupationPrecedente']],
            'logements_publies' => $d['nombrePublies'],
            // Propriétaire seulement (null pour un gestionnaire)
            'solde' => $d['solde'],
            'retraits' => $d['estProprietaire'] ? $d['retraits']->map(fn ($r) => FinancesController::retrait($r))->values() : null,
            'abonnement' => $d['abonnement'] ? [
                'formule' => $d['abonnement']->formule->nom,
                'fin' => Format::jour($d['abonnement']->fin),
                'essai' => (bool) $d['abonnement']->essai,
            ] : null,
        ]]);
    }

    /** Clients attendus aujourd'hui (confirmés, pas encore arrivés). */
    public function attendus(Request $request): JsonResponse
    {
        return response()->json(['data' => ReservationProResource::collection($this->arrivees->attendus($request->user()))->resolve($request)]);
    }

    /**
     * POST /pro/scanner/verifier {saisie} : contenu du QR Code (VIVI1....) ou code de secours à 6 chiffres.
     * Seule la réservation vérifiée à l'instant peut ensuite être démarrée (10 minutes).
     */
    public function verifier(Request $request): JsonResponse
    {
        $saisie = (string) $request->validate(['saisie' => ['required', 'string', 'max:120']])['saisie'];

        try {
            $r = $this->arrivees->verifier($request->user(), $saisie);
        } catch (ArriveeRefusee $e) {
            Cache::forget($this->cle($request));

            return response()->json(['valide' => false, 'titre' => $e->titre, 'message' => $e->getMessage()], 422);
        }
        Cache::put($this->cle($request), $r->reference, now()->addMinutes(10));

        return response()->json(['valide' => true, 'data' => (new ReservationProResource($r))->resolve($request) + [
            'services_texte' => $r->services->map(fn ($s) => $s->nom.($s->quantite > 1 ? ' × '.$s->quantite : ''))->implode(', ') ?: 'Aucun service',
        ]]);
    }

    /** POST /pro/scanner/demarrer {reference} : démarre le séjour et crédite le solde du propriétaire. */
    public function demarrer(Request $request): JsonResponse
    {
        $reference = (string) $request->validate(['reference' => ['required', 'string']])['reference'];
        abort_unless(Cache::pull($this->cle($request)) === $reference, 409, 'Vérifiez de nouveau le code du client.');

        try {
            $r = $this->arrivees->demarrer($request->user(), Reservation::where('reference', $reference)->firstOrFail());
        } catch (ArriveeRefusee $e) {
            return response()->json(['valide' => false, 'titre' => $e->titre, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Séjour démarré à '.$r->scanne_le->format('H:i').'.',
            'data' => [
                'reference' => $r->reference,
                'heure' => Format::date($r->scanne_le),
                'client' => $r->client->name,
                'logement' => $r->logement->titre,
                'credit' => $r->estManuelle() ? null : $r->creditProprietaire(),   // hors plateforme : rien n'est crédité
            ],
        ]);
    }

    private function cle(Request $request): string
    {
        return 'api-scanner:'.$request->user()->getKey();
    }
}
