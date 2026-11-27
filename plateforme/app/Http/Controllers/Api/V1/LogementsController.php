<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Enums\StatutAvis;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Format;
use App\Http\Resources\Api\LogementResource;
use App\Models\Logement;
use App\Services\Recherche;
use App\Services\SimulationSejour;
use App\Support\Geo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/** Recherche, fiche et disponibilités d'un logement (accessible sans connexion). */
class LogementsController extends Controller
{
    private const PAR_PAGE = 20;

    /**
     * GET /logements?q=&ville=&date=AAAA-MM-JJ&voyageurs=&formule=heure|demi_journee|journee&budget=
     *   &categorie=logement|salle&type[]=&equipement[]=&service[]=&tri=pertinence|prix|note|distance&lat=&lng=&page=
     */
    public function index(Request $request, Recherche $recherche): AnonymousResourceCollection
    {
        $c = $recherche->criteres($request->query());
        ['logements' => $logements, 'hors_rayon' => $horsRayon] = $recherche->executer($c);

        $page = LengthAwarePaginator::resolveCurrentPage();
        $resultats = new LengthAwarePaginator($logements->forPage($page, self::PAR_PAGE)->values(), $logements->count(), self::PAR_PAGE, $page, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);

        return LogementResource::collection($resultats)->additional(['criteres' => [
            'q' => $c['q'],
            'ville' => $c['ville'],
            'date' => Format::jour($c['date']),
            'voyageurs' => $c['voyageurs'],
            'formule' => $c['formule']?->value,
            'categorie' => $c['categorie'],
            'budget' => $c['budget'],
            'tri' => $c['tri'],
            'position' => $c['position'] ? ['latitude' => $c['position'][0], 'longitude' => $c['position'][1]] : null,
            'hors_rayon' => $horsRayon,   // aucun logement dans le rayon : tous sont montrés, du plus proche au plus loin
            'rayon_km' => Geo::RAYON_KM,
        ]]);
    }

    public function show(Request $request, string $slug): LogementResource
    {
        $logement = $this->trouver($request, $slug)->load([
            'tarifs', 'creneaux', 'equipements' => fn ($q) => $q->orderBy('ordre'),
            'services' => fn ($q) => $q->where('actif', true)->orderBy('ordre'),
            'photos' => fn ($q) => $q->orderBy('ordre'),
        ]);

        return (new LogementResource($logement))->additional(['apercu' => ! $logement->estVisible()]);
    }

    /**
     * Bloc « Réserver » : formules, heures ou créneaux libres et prix détaillé, recalculés par le serveur.
     * GET /logements/{slug}/disponibilites?date=&formule=&heure=HH:MM&duree=&creneau_id=&jours=&voyageurs=
     * Les choix incohérents sont corrigés : renvoyer à chaque changement les valeurs reçues.
     */
    public function disponibilites(Request $request, string $slug, SimulationSejour $simulation): JsonResponse
    {
        return response()->json(['data' => $simulation->etat($this->trouver($request, $slug), $request->query())]);
    }

    public function avis(Request $request, string $slug): JsonResponse
    {
        $avis = $this->trouver($request, $slug)->avis()->with('client:id,name')
            ->where('statut', StatutAvis::Publie)->latest()->paginate(20);

        return response()->json([
            'data' => $avis->getCollection()->map(fn ($a) => [
                'note' => (int) $a->note,
                'commentaire' => $a->commentaire,
                'auteur' => $a->client?->prenom(),
                'le' => Format::date($a->created_at),
            ]),
            'meta' => ['page' => $avis->currentPage(), 'derniere_page' => $avis->lastPage(), 'total' => $avis->total()],
        ]);
    }

    /** Comme sur le site : seuls les logements visibles, sauf aperçu pour le propriétaire, son gestionnaire et l'équipe VIVI. */
    private function trouver(Request $request, string $slug): Logement
    {
        $logement = Logement::query()->avecNote()->where('slug', $slug)->firstOrFail();
        if ($logement->estVisible()) {
            return $logement;
        }
        $u = $request->user('sanctum');
        abort_unless($u && (
            $u->getKey() === $logement->proprietaire_id
            || $u->hasAnyRole(Role::equipeVivi())
            || $logement->gestionnaires()->whereKey($u->getKey())->exists()
        ), 404);

        return $logement;
    }
}
