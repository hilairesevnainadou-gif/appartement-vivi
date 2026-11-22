<?php

namespace App\Http\Controllers\Proprietaire;

use App\Enums\StatutLogement;
use App\Enums\TypeLogement;
use App\Http\Controllers\Controller;
use App\Http\Requests\LogementRequest;
use App\Models\Equipement;
use App\Models\Logement;
use App\Models\Service;
use App\Services\GestionLogements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** « Mes logements » : liste, création, modification, mise en pause et photos. */
class LogementsController extends Controller
{
    public function __construct(private readonly GestionLogements $gestion) {}

    public function index(Request $request): View
    {
        $proprietaire = $request->user();

        return view('pro.logements', [
            'logements' => $proprietaire->logements()->with(['tarifs', 'photos' => fn ($q) => $q->orderBy('ordre')])->latest()->get(),
            'quota' => $this->gestion->quota($proprietaire),
        ]);
    }

    public function create(Request $request): View
    {
        // « Ajouter une résidence » : type Résidence et plusieurs chambres proposés d'emblée
        $residence = $request->query('type') === TypeLogement::Residence->value;
        // « Ajouter une salle » : salle de fêtes proposée, capacité en personnes, ni chambres ni lits
        $salle = $request->query('type') === 'salle';

        return $this->formulaire($request, new Logement([
            'capacite' => $salle ? 100 : 2, 'chambres' => $salle ? 0 : 1, 'lits' => $salle ? 0 : 1, 'salles_de_bain' => 1, 'temps_preparation_minutes' => 60,
            'ville' => 'Cotonou', 'statut' => StatutLogement::Brouillon,
            'type' => $residence ? TypeLogement::Residence : ($salle ? TypeLogement::SalleFetes : null), 'unites' => $residence ? 5 : 1,
        ]));
    }

    public function store(LogementRequest $request): RedirectResponse
    {
        [$logement, $message] = $this->gestion->enregistrer($request->user(), $request->validated());

        return redirect()->route('pro.logements.edit', ['logement' => $logement, 'onglet' => 'photos'])
            ->with('statut', $message.' Ajoutez maintenant vos photos.');
    }

    public function edit(Request $request, Logement $logement): View
    {
        $this->sien($request, $logement);

        return $this->formulaire($request, $logement->load(['tarifs', 'creneaux', 'equipements', 'services', 'photos' => fn ($q) => $q->orderBy('ordre')]));
    }

    public function update(LogementRequest $request, Logement $logement): RedirectResponse
    {
        $this->sien($request, $logement);
        [, $message] = $this->gestion->enregistrer($request->user(), $request->validated(), $logement);

        return redirect()->route('pro.logements.edit', ['logement' => $logement, 'onglet' => $request->input('onglet')])->with('statut', $message);
    }

    /** Pause décidée par le propriétaire : le logement sort de la recherche, les réservations restent. */
    public function enLigne(Request $request, Logement $logement): RedirectResponse
    {
        $this->sien($request, $logement);
        abort_unless($logement->statut === StatutLogement::Publie, 422);
        $logement->update(['en_ligne' => $request->boolean('en_ligne')]);

        return back()->with('statut', $logement->en_ligne
            ? '« '.$logement->titre.' » est de nouveau visible dans la recherche.'
            : '« '.$logement->titre.' » est mis en pause : il n’apparaît plus dans la recherche.');
    }

    public function ajouterPhotos(Request $request, Logement $logement): RedirectResponse
    {
        $this->sien($request, $logement);
        $request->validate([
            'photos' => ['required', 'array', 'max:10'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=800,min_height=600'],
        ], [
            'photos.*.dimensions' => 'Chaque photo doit mesurer au moins 800 × 600 pixels.',
            'photos.*.max' => 'Chaque photo doit peser 5 Mo au plus.',
        ]);
        abort_if($logement->photos()->count() + count($request->file('photos')) > 15, 422, 'Quinze photos au plus par logement.');

        $n = $this->gestion->ajouterPhotos($logement, $request->file('photos'));

        return redirect()->route('pro.logements.edit', ['logement' => $logement, 'onglet' => 'photos'])
            ->with('statut', $n > 1 ? $n.' photos ajoutées.' : 'Photo ajoutée.');
    }

    public function supprimerPhoto(Request $request, Logement $logement, int $photo): RedirectResponse
    {
        $this->sien($request, $logement);
        $this->gestion->supprimerPhoto($logement, $photo);

        return redirect()->route('pro.logements.edit', ['logement' => $logement, 'onglet' => 'photos'])->with('statut', 'Photo supprimée.');
    }

    public function photoPrincipale(Request $request, Logement $logement, int $photo): RedirectResponse
    {
        $this->sien($request, $logement);
        $this->gestion->photoPrincipale($logement, $photo);

        return redirect()->route('pro.logements.edit', ['logement' => $logement, 'onglet' => 'photos'])->with('statut', 'Photo principale changée.');
    }

    private function formulaire(Request $request, Logement $logement): View
    {
        return view('pro.logement', [
            'logement' => $logement->exists ? $logement->loadMissing('gerantVivi') : $logement,
            'equipements' => Equipement::orderBy('ordre')->orderBy('nom')->get(),
            'services' => Service::where('actif', true)->orderBy('ordre')->get(),
            'quota' => $this->gestion->quota($request->user(), $logement->exists ? $logement : null),
            'onglet' => in_array($request->query('onglet'), ['infos', 'tarifs', 'services', 'photos'], true) ? $request->query('onglet') : 'infos',
        ]);
    }

    /** Un propriétaire ne modifie que ses logements. */
    private function sien(Request $request, Logement $logement): void
    {
        abort_unless($logement->proprietaire_id === $request->user()->getKey(), 404);
    }
}
