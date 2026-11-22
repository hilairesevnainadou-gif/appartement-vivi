<?php

namespace App\Http\Controllers\Api\V1\Pro;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Proprietaire\AgentsController as AgentsWeb;
use App\Models\User;
use App\Services\AgentsMarketing;
use App\Services\ReservationImpossible;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Agents marketing du propriétaire : classement, désignation, logements promus par chacun. */
class AgentsController extends Controller
{
    public function __construct(private readonly AgentsMarketing $agents) {}

    /** GET /pro/agents?periode=mois|annee|tout (mois par défaut) */
    public function index(Request $request): JsonResponse
    {
        $periode = $request->validate(['periode' => ['nullable', Rule::in(array_keys(AgentsWeb::PERIODES))]])['periode'] ?? 'mois';
        $p = $request->user();

        return response()->json(['data' => [
            'periode' => $periode,
            'classement' => $this->agents->classement($p, AgentsWeb::depuis($periode))->map(fn (array $l) => [
                'rang' => $l['rang'],
                'agent' => ['id' => $l['agent']->id, 'nom' => $l['agent']->name, 'telephone' => $l['agent']->telephone, 'email' => $l['agent']->email],
                'sejours' => $l['sejours'],
                'a_venir' => $l['a_venir'],
                'chiffre_location' => $l['chiffre'],
                'gains' => $l['gains'],
                'logements' => $l['logements']->map(fn ($g) => ['id' => $g->id, 'titre' => $g->titre])->values(),
            ])->values(),
            'logements' => $p->logements()->orderBy('titre')->get(['id', 'titre', 'marketing_type', 'marketing_valeur'])->map(fn ($l) => [
                'id' => $l->id, 'titre' => $l->titre,
                'part' => $l->marketing_type ? ['type' => $l->marketing_type->value, 'valeur' => (float) $l->marketing_valeur, 'libelle' => $l->libelleMarketing()] : null,
            ]),
        ]]);
    }

    /** POST /pro/agents {nom, identifiant, logements[]} */
    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'min:3', 'max:120'],
            'identifiant' => ['required', 'string', 'max:255'],
            'logements' => ['required', 'array', 'min:1'],
            'logements.*' => ['integer'],
        ], ['logements.required' => 'Choisissez au moins un logement à promouvoir.']);

        try {
            $agent = $this->agents->designer($request->user(), $donnees['nom'], $donnees['identifiant'], $donnees['logements']);
        } catch (ReservationImpossible $e) {
            throw ValidationException::withMessages(['identifiant' => $e->getMessage()]);
        }

        return response()->json([
            'message' => $agent->name.' est votre agent marketing : un message lui explique comment obtenir ses liens.',
            'data' => ['id' => $agent->id, 'nom' => $agent->name],
        ], 201);
    }

    /** PUT /pro/agents/{agent} {logements[]} : liste vide = l'agent ne promeut plus vos logements. */
    public function update(Request $request, User $agent): JsonResponse
    {
        abort_unless($this->agents->agentsDe($request->user())->contains('id', $agent->id), 404);
        $logements = $request->validate(['logements' => ['present', 'array'], 'logements.*' => ['integer']])['logements'];
        $this->agents->modifier($request->user(), $agent, $logements);

        return response()->json(['message' => $logements === [] ? $agent->name.' ne fait plus la promotion de vos logements.' : 'Logements promus par '.$agent->name.' mis à jour.']);
    }
}
