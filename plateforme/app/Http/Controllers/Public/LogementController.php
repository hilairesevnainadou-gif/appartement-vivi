<?php

namespace App\Http\Controllers\Public;

use App\Enums\Role;
use App\Enums\StatutAvis;
use App\Http\Controllers\Controller;
use App\Models\Logement;
use App\Services\SimulationSejour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Fiche d'un logement et calcul en direct du bloc « Réserver ». */
class LogementController extends Controller
{
    public function show(Request $request, string $slug, SimulationSejour $simulation): View
    {
        $logement = $this->trouver($request, $slug);
        // Lien d'affiliation d'un agent marketing (?agent=AG…) : retenu pour la réservation, même après la connexion
        if (is_string($request->query('agent'))) {
            app(\App\Services\AgentsMarketing::class)->retenir($request, $logement, $request->query('agent'));
        }
        $logement->load([
            'tarifs', 'creneaux', 'equipements' => fn ($q) => $q->orderBy('ordre'),
            'services' => fn ($q) => $q->where('actif', true)->orderBy('ordre'),
            'photos' => fn ($q) => $q->orderBy('ordre'),
        ]);

        $avis = $logement->avis()->with('client', 'reservation')
            ->where('statut', StatutAvis::Publie)->latest()->limit(4)->get();

        return view('pages.logement', [
            'logement' => $logement,
            'etat' => $simulation->etat($logement, $request->query()),
            'avis' => $avis,
            'apercu' => ! $logement->estVisible(),
        ]);
    }

    /** Appelé par le bloc « Réserver » à chaque changement : disponibilités et prix recalculés par le serveur. */
    public function disponibilites(Request $request, string $slug, SimulationSejour $simulation): JsonResponse
    {
        $logement = $this->trouver($request, $slug);

        return response()->json($simulation->etat($logement, $request->query()));
    }

    /**
     * Les clients ne voient que les logements publiés et en ligne. Le propriétaire, son gestionnaire
     * et l'équipe VIVI peuvent prévisualiser un logement qui ne l'est pas encore.
     */
    private function trouver(Request $request, string $slug): Logement
    {
        $logement = Logement::query()->avecNote()->where('slug', $slug)->firstOrFail();
        if ($logement->estVisible()) {
            return $logement;
        }
        $u = $request->user();
        abort_unless($u && (
            $u->getKey() === $logement->proprietaire_id
            || $u->hasAnyRole(Role::equipeVivi())
            || $logement->gestionnaires()->whereKey($u->getKey())->exists()
        ), 404);

        return $logement;
    }
}
