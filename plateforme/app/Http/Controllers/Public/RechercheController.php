<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Equipement;
use App\Models\Service;
use App\Services\Recherche;
use App\Support\Geo;
use App\Support\Parametres;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/** Page « Logements » : recherche par ville, date et voyageurs, filtres, tri (logique dans App\Services\Recherche). */
class RechercheController extends Controller
{
    private const PAR_PAGE = 12;

    public function __invoke(Request $request, Recherche $recherche): View
    {
        $c = $recherche->criteres($request->query());
        ['logements' => $logements, 'hors_rayon' => $horsRayon] = $recherche->executer($c);

        $page = LengthAwarePaginator::resolveCurrentPage();
        $resultats = new LengthAwarePaginator(
            $logements->forPage($page, self::PAR_PAGE)->values(),
            $logements->count(),
            self::PAR_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('pages.recherche', [
            'resultats' => $resultats,
            'criteres' => collect($c)->only(['ville', 'voyageurs', 'formule', 'budget', 'categorie', 'types', 'equipements', 'services', 'tri', 'date'])->all(),
            'position' => $c['position'],
            'horsRayon' => $horsRayon,
            'rayonKm' => Geo::RAYON_KM,
            'villes' => Parametres::get('villes'),
            'listeEquipements' => Equipement::orderBy('ordre')->orderBy('nom')->get(),
            'listeServices' => Service::where('actif', true)->orderBy('ordre')->get(),
            'nombreFiltres' => ($c['formule'] ? 1 : 0) + ($c['budget'] > 0 ? 1 : 0) + count($c['types']) + count($c['equipements']) + count($c['services']),
        ]);
    }
}
