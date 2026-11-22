<?php

namespace App\Http\Controllers\Proprietaire;

use App\Http\Controllers\Controller;
use App\Services\TableauDeBord;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Tableau de bord du propriétaire (ou du gestionnaire, sur les logements qui lui sont confiés). */
class TableauDeBordController extends Controller
{
    public function __invoke(Request $request, TableauDeBord $tableau): View
    {
        return view('espace.proprietaire', ['utilisateur' => $request->user()] + $tableau->donnees($request->user()));
    }
}
