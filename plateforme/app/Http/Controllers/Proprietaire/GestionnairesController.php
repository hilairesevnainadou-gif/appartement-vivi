<?php

namespace App\Http\Controllers\Proprietaire;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Delegations;
use App\Services\ReservationImpossible;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** « Gestionnaires » : personnes qui accueillent les clients à la place du propriétaire. */
class GestionnairesController extends Controller
{
    public function __construct(private readonly Delegations $delegations) {}

    public function index(Request $request): View
    {
        $p = $request->user();

        return view('pro.gestionnaires', [
            'gestionnaires' => $this->delegations->gestionnairesDe($p),
            'maximum' => $this->delegations->maximum($p),
            'logements' => $p->logements()->orderBy('titre')->get(['id', 'titre']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'min:3', 'max:120'],
            'identifiant' => ['required', 'string', 'max:255'],
            'logements' => ['required', 'array', 'min:1'],
            'logements.*' => ['integer'],
        ], ['logements.required' => 'Choisissez au moins un logement à confier.']);

        try {
            $g = $this->delegations->confier($request->user(), $donnees['nom'], $donnees['identifiant'], $donnees['logements']);
        } catch (ReservationImpossible $e) {
            return back()->withInput()->withErrors(['identifiant' => $e->getMessage()]);
        }

        return back()->with('statut', $g->name.' est votre gestionnaire : un message lui explique comment se connecter.');
    }

    public function update(Request $request, User $gestionnaire): RedirectResponse
    {
        abort_unless($this->delegations->gestionnairesDe($request->user())->contains('id', $gestionnaire->id), 404);
        $logements = $request->validate(['logements' => ['array'], 'logements.*' => ['integer']])['logements'] ?? [];
        $this->delegations->modifier($request->user(), $gestionnaire, $logements);

        return back()->with('statut', $logements === [] ? 'Accès de '.$gestionnaire->name.' retiré.' : 'Logements confiés à '.$gestionnaire->name.' mis à jour.');
    }
}
