<?php

namespace App\Filament\Pages;

use App\Enums\StatutLogement;
use App\Enums\StatutReservation;
use App\Filament\Resources\Logements\LogementResource;
use App\Models\Logement;
use App\Support\Parametres;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Carte des logements et des salles inscrits sur VIVI (onglets Tout, Logements, Salles) : où ils sont, leur statut, et la progression mois par mois
 * sur le territoire (curseur et lecture animée).
 */
class CarteLogements extends Page
{
    /** Onglets : catégorie => [libellé, mot au pluriel, « inscrits » accordé]. */
    public const CATEGORIES = [
        'tout' => ['Tout', 'logements et salles', 'inscrits'],
        'logement' => ['Logements', 'logements', 'inscrits'],
        'salle' => ['Salles et espaces', 'salles', 'inscrites'],
    ];

    /** Onglet affiché (?categorie=). */
    public string $categorie = 'tout';

    protected static ?string $title = 'Carte et progression';

    protected static ?string $navigationLabel = 'Carte et progression';

    protected static ?string $slug = 'carte';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Logements';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.carte';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('carte.voir');
    }

    public function mount(): void
    {
        $demande = request()->query('categorie');
        $this->categorie = is_string($demande) && isset(self::CATEGORIES[$demande]) ? $demande : 'tout';
    }

    public function getSubheading(): ?string
    {
        return 'Logements et salles inscrits sur VIVI, par statut, et leur progression sur le territoire mois après mois.';
    }

    protected function getViewData(): array
    {
        $statuts = [
            StatutLogement::Publie->value => ['Publié', '#1e7d4f'],
            StatutLogement::EnValidation->value => ['En vérification', '#ee9b2b'],
            StatutLogement::Brouillon->value => ['Brouillon', '#8a9a9b'],
            StatutLogement::Refuse->value => ['À corriger', '#b83a2b'],
        ];

        $logements = Logement::query()->with('proprietaire:id,name')
            ->when($this->categorie !== 'tout', fn ($q) => $q->whereIn('type', array_map(fn (\App\Enums\TypeLogement $t) => $t->value, \App\Enums\TypeLogement::deCategorie($this->categorie))))
            ->withCount(['reservations as sejours' => fn ($q) => $q->whereIn('statut', [StatutReservation::Confirmee, StatutReservation::EnCours, StatutReservation::Terminee, StatutReservation::Consommee])])
            ->orderBy('created_at')->get();

        $points = $logements->filter(fn (Logement $l) => $l->latitude !== null && $l->longitude !== null)->map(fn (Logement $l) => [
            'id' => $l->id,
            'titre' => $l->titre,
            'salle' => $l->estSalle(),
            'type' => $l->type?->getLabel(),   // salle de fêtes, de conférence… ou logement
            'lieu' => $l->quartier.', '.$l->ville,
            'proprietaire' => $l->proprietaire?->name,
            'statut' => $l->statut->value,
            'libelle' => $statuts[$l->statut->value][0] ?? $l->statut->value,
            'couleur' => $statuts[$l->statut->value][1] ?? '#5f7072',
            'en_ligne' => (bool) $l->en_ligne,
            'unites' => (int) $l->unites,
            'sejours' => (int) $l->sejours,
            'lat' => (float) $l->latitude,
            'lng' => (float) $l->longitude,
            'mois' => $l->created_at->format('Y-m'),
            'url' => LogementResource::urlFiche($l),
            'capacite' => $l->personnes(),
        ])->values();

        // Mois depuis la première inscription (au moins 6), pour le curseur de progression
        $debut = CarbonImmutable::instance($logements->first()?->created_at ?? now())->startOfMonth();
        $debut = $debut->min(now()->startOfMonth()->subMonths(5));
        $mois = [];
        for ($m = $debut; $m->lte(now()->startOfMonth()); $m = $m->addMonth()) {
            $cle = $m->format('Y-m');
            $mois[] = [
                'cle' => $cle,
                'libelle' => ucfirst($m->translatedFormat('M Y')),
                'nouveaux' => $logements->filter(fn ($l) => $l->created_at->format('Y-m') === $cle)->count(),
                'total' => $logements->filter(fn ($l) => $l->created_at->format('Y-m') <= $cle)->count(),
            ];
        }

        // Couverture du territoire : par ville ouverte, puis les autres
        $villes = collect(Parametres::get('villes'))->merge($logements->pluck('ville'))->unique()->values()
            ->map(fn ($v) => [
                'ville' => $v,
                'total' => $logements->where('ville', $v)->count(),
                'publies' => $logements->where('ville', $v)->where('statut', StatutLogement::Publie)->count(),
                'quartiers' => $logements->where('ville', $v)->pluck('quartier')->unique()->count(),
                'chambres' => (int) $logements->where('ville', $v)->where('statut', StatutLogement::Publie)->sum('unites'),
            ])->sortByDesc('total')->values();

        $ceMois = now()->format('Y-m');
        $moisPrecedent = now()->subMonth()->format('Y-m');

        return [
            'categories' => self::CATEGORIES,
            'mot' => self::CATEGORIES[$this->categorie][1],
            'inscrits' => self::CATEGORIES[$this->categorie][2],
            'salles' => $this->categorie === 'salle',
            'points' => $points,
            'mois' => $mois,
            'villes' => $villes,
            'statuts' => $statuts,
            'chiffres' => [
                'total' => $logements->count(),
                'publies' => $logements->where('statut', StatutLogement::Publie)->count(),
                // Fiches dont la gestion est confiée à VIVI ; places des salles publiées
                'places' => (int) $logements->where('statut', StatutLogement::Publie)->sum('capacite'),
                'geresParVivi' => $logements->filter(fn (Logement $l) => $l->estGereParVivi())->count(),
                'enVerification' => $logements->where('statut', StatutLogement::EnValidation)->count(),
                'sansPosition' => $logements->count() - $points->count(),
                'ceMois' => $logements->filter(fn ($l) => $l->created_at->format('Y-m') === $ceMois)->count(),
                'moisPrecedent' => $logements->filter(fn ($l) => $l->created_at->format('Y-m') === $moisPrecedent)->count(),
                'proprietaires' => $logements->pluck('proprietaire_id')->unique()->count(),
                'villesCouvertes' => $villes->where('total', '>', 0)->count(),
            ],
        ];
    }
}
