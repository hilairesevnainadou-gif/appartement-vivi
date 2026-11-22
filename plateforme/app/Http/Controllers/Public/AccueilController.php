<?php

namespace App\Http\Controllers\Public;

use App\Enums\TypeLogement;
use App\Http\Controllers\Controller;
use App\Models\Logement;
use App\Models\Service;
use App\Support\Parametres;
use Illuminate\View\View;

class AccueilController extends Controller
{
    /** Villes citées dans le bandeau de l'accueil. */
    private const VILLES_BANDEAU = 3;

    public function __invoke(): View
    {
        $visibles = Logement::query()->visibles()->whereHas('tarifs', fn ($q) => $q->where('actif', true));

        $typesSalles = array_map(fn (TypeLogement $t) => $t->value, TypeLogement::salles());

        // À la une : logements les mieux notés, puis les plus récents (les salles ont leur propre bloc)
        $aLaUne = (clone $visibles)->avecNote()
            ->whereNotIn('type', $typesSalles)
            ->with(['tarifs', 'photos' => fn ($q) => $q->orderBy('ordre')])
            ->orderByDesc('note_moyenne')->orderByDesc('nombre_avis')->latest()
            ->limit(6)->get();

        // Salles de fêtes et de conférence : bloc à part, s'il y en a
        $salles = (clone $visibles)->avecNote()
            ->whereIn('type', $typesSalles)
            ->with(['tarifs', 'photos' => fn ($q) => $q->orderBy('ordre')])
            ->orderByDesc('note_moyenne')->latest()
            ->limit(3)->get();

        // Villes ouvertes, avec leur nombre de logements et de salles
        $compter = fn (bool $salles) => (clone $visibles)->{$salles ? 'whereIn' : 'whereNotIn'}('type', $typesSalles)
            ->selectRaw('ville, count(*) as nombre')->groupBy('ville')->pluck('nombre', 'ville');
        [$logementsParVille, $sallesParVille] = [$compter(false), $compter(true)];
        $villes = collect(Parametres::get('villes'))->mapWithKeys(fn ($v) => [$v => [
            'logements' => (int) ($logementsParVille[$v] ?? 0),
            'salles' => (int) ($sallesParVille[$v] ?? 0),
        ]]);

        // Bandeau : seulement les villes les plus fournies ; les autres sont dans la liste « Où ? »
        $villesBandeau = $villes->map(fn (array $n) => array_sum($n))->sortDesc()->keys()->take(self::VILLES_BANDEAU);

        return view('pages.accueil', [
            'villes' => Parametres::get('villes'),
            'villesBandeau' => $villesBandeau,
            'autresVilles' => max(0, $villes->count() - $villesBandeau->count()),
            'aLaUne' => $aLaUne,
            'salles' => $salles,
            'services' => Service::where('actif', true)->orderBy('ordre')->get(),
            'villesAvecNombre' => $villes->filter(fn (array $n) => array_sum($n) > 0),
        ]);
    }
}
