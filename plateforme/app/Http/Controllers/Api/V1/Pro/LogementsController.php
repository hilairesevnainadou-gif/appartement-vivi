<?php

namespace App\Http\Controllers\Api\V1\Pro;

use App\Enums\StatutLogement;
use App\Http\Controllers\Controller;
use App\Http\Requests\LogementRequest;
use App\Http\Resources\Api\LogementProResource;
use App\Models\Logement;
use App\Services\GestionLogements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * « Mes logements » du propriétaire : liste, fiche, création, modification, pause et photos.
 * Le gestionnaire n'y a pas accès (il accueille, il ne modifie pas). Validation : App\Http\Requests\LogementRequest.
 */
class LogementsController extends Controller
{
    private const RELATIONS = ['tarifs', 'creneaux', 'equipements', 'services', 'photos', 'gerantVivi'];

    public function __construct(private readonly GestionLogements $gestion) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $p = $request->user();

        return LogementProResource::collection($p->logements()->with(['tarifs', 'photos', 'gerantVivi'])->latest()->get())
            ->additional(['quota' => $this->gestion->quota($p)]);
    }

    public function show(Request $request, Logement $logement): LogementProResource
    {
        $this->sien($request, $logement);

        return (new LogementProResource($logement->load(self::RELATIONS)))
            ->additional(['quota' => $this->gestion->quota($request->user(), $logement)]);
    }

    /** Nouveau logement : envoyé à la vérification VIVI si l'abonnement le permet, sinon brouillon. */
    public function store(LogementRequest $request): JsonResponse
    {
        [$logement, $message] = $this->gestion->enregistrer($request->user(), $request->validated());

        return (new LogementProResource($logement->fresh(self::RELATIONS)))->additional(['message' => $message])->response()->setStatusCode(201);
    }

    public function update(LogementRequest $request, Logement $logement): LogementProResource
    {
        $this->sien($request, $logement);
        [, $message] = $this->gestion->enregistrer($request->user(), $request->validated(), $logement);

        return (new LogementProResource($logement->fresh(self::RELATIONS)))->additional(['message' => $message]);
    }

    /** PATCH /pro/logements/{id}/en-ligne {en_ligne: bool} : pause (sort de la recherche, les réservations restent). */
    public function enLigne(Request $request, Logement $logement): LogementProResource
    {
        $this->sien($request, $logement);
        if ($logement->statut !== StatutLogement::Publie) {
            throw ValidationException::withMessages(['en_ligne' => 'Seul un logement publié peut être mis en pause ou remis en ligne.']);
        }
        $logement->update(['en_ligne' => $request->boolean('en_ligne')]);

        return (new LogementProResource($logement->load(['tarifs', 'photos'])))->additional(['message' => $logement->en_ligne
            ? '« '.$logement->titre.' » est de nouveau visible dans la recherche.'
            : '« '.$logement->titre.' » est mis en pause : il n’apparaît plus dans la recherche.']);
    }

    /** POST /pro/logements/{id}/photos (multipart, photos[]) : 800 × 600 minimum, 5 Mo, 15 photos au plus par logement. */
    public function ajouterPhotos(Request $request, Logement $logement): LogementProResource
    {
        $this->sien($request, $logement);
        $request->validate([
            'photos' => ['required', 'array', 'max:10'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=800,min_height=600'],
        ], [
            'photos.*.dimensions' => 'Chaque photo doit mesurer au moins 800 × 600 pixels.',
            'photos.*.max' => 'Chaque photo doit peser 5 Mo au plus.',
        ]);
        if ($logement->photos()->count() + count($request->file('photos')) > 15) {
            throw ValidationException::withMessages(['photos' => 'Quinze photos au plus par logement.']);
        }
        $n = $this->gestion->ajouterPhotos($logement, $request->file('photos'));

        return (new LogementProResource($logement->fresh(self::RELATIONS)))->additional(['message' => $n > 1 ? $n.' photos ajoutées.' : 'Photo ajoutée.']);
    }

    public function supprimerPhoto(Request $request, Logement $logement, int $photo): LogementProResource
    {
        $this->sien($request, $logement);
        $this->gestion->supprimerPhoto($logement, $photo);

        return (new LogementProResource($logement->fresh(self::RELATIONS)))->additional(['message' => 'Photo supprimée.']);
    }

    public function photoPrincipale(Request $request, Logement $logement, int $photo): LogementProResource
    {
        $this->sien($request, $logement);
        $this->gestion->photoPrincipale($logement, $photo);

        return (new LogementProResource($logement->fresh(self::RELATIONS)))->additional(['message' => 'Photo principale changée.']);
    }

    private function sien(Request $request, Logement $logement): void
    {
        abort_unless($logement->proprietaire_id === $request->user()->getKey(), 404);
    }
}
