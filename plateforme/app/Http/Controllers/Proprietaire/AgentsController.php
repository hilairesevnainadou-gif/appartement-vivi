<?php

namespace App\Http\Controllers\Proprietaire;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AgentsMarketing;
use App\Services\ReservationImpossible;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** « Agents marketing » : classement des agents, logements promus par chacun, part marketing de chaque logement. */
class AgentsController extends Controller
{
    public const PERIODES = ['mois' => 'Ce mois', 'annee' => 'Cette année', 'tout' => 'Depuis le début'];

    public function __construct(private readonly AgentsMarketing $agents) {}

    public function index(Request $request): View
    {
        $p = $request->user();
        $periode = isset(self::PERIODES[$request->query('periode')]) ? $request->query('periode') : 'mois';

        return view('pro.agents', [
            'classement' => $this->agents->classement($p, self::depuis($periode)),
            'periode' => $periode,
            'periodes' => self::PERIODES,
            'logements' => $p->logements()->orderBy('titre')->get(['id', 'titre', 'marketing_type', 'marketing_valeur']),
        ]);
    }

    public function store(Request $request): RedirectResponse
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
            return back()->withInput()->withErrors(['identifiant' => $e->getMessage()]);
        }

        return back()->with('statut', $agent->name.' est votre agent marketing : un message lui explique comment obtenir ses liens.');
    }

    public function update(Request $request, User $agent): RedirectResponse
    {
        abort_unless($this->agents->agentsDe($request->user())->contains('id', $agent->id), 404);
        $logements = $request->validate(['logements' => ['array'], 'logements.*' => ['integer']])['logements'] ?? [];
        $this->agents->modifier($request->user(), $agent, $logements);

        return back()->with('statut', $logements === [] ? $agent->name.' ne fait plus la promotion de vos logements.' : 'Logements promus par '.$agent->name.' mis à jour.');
    }

    public static function depuis(string $periode): ?\Carbon\CarbonInterface
    {
        return match ($periode) {
            'mois' => now()->startOfMonth(),
            'annee' => now()->startOfYear(),
            default => null,
        };
    }
}
