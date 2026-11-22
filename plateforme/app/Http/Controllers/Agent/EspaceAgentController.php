<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Services\AgentsMarketing;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** « Espace agent » : liens d'affiliation des logements promus, commissions, solde et retraits. */
class EspaceAgentController extends Controller
{
    public function __invoke(Request $request, AgentsMarketing $agents): View
    {
        $agent = $request->user();

        return view('espace.agent', $agents->espace($agent) + [
            'mouvements' => $agent->mouvementsSolde()->with('reservation:id,reference')->latest('id')->paginate(15, ['*'], 'mouvements'),
            'retraits' => $agent->retraits()->latest('id')->limit(10)->get(),
        ]);
    }
}
