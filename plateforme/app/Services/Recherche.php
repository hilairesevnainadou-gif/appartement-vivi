<?php

namespace App\Services;

use App\Enums\Formule;
use App\Enums\TypeLogement;
use App\Models\Logement;
use App\Support\Geo;
use App\Support\Parametres;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Recherche de logements par ville, date et voyageurs, filtres et tri : commune à la page « Logements »
 * du site et à l'application mobile. Seuls les logements libres à la date choisie sont gardés.
 */
class Recherche
{
    public function __construct(private readonly Disponibilites $disponibilites) {}

    /**
     * Critères reçus (paramètres de l'adresse), nettoyés : toute valeur incohérente est ignorée.
     *
     * @param  array<string, mixed>  $saisie
     * @return array{ville: ?string, date: CarbonImmutable, voyageurs: int, formule: ?Formule, budget: int, categorie: ?string, types: list<string>, equipements: list<int>, services: list<int>, tri: string, position: ?array}
     */
    public function criteres(array $saisie): array
    {
        $villes = Parametres::get('villes');
        $aujourdhui = CarbonImmutable::today();

        $ville = in_array($saisie['ville'] ?? null, $villes, true) ? $saisie['ville'] : null;
        $date = rescue(fn () => CarbonImmutable::createFromFormat('!Y-m-d', (string) ($saisie['date'] ?? '')), null, false);
        $date = $date && $date->gte($aujourdhui) ? $date : $aujourdhui;
        $voyageurs = min(max(1, (int) ($saisie['voyageurs'] ?? 1)), TypeLogement::CAPACITE_MAX_SALLE);
        $formule = Formule::tryFrom((string) ($saisie['formule'] ?? ''));
        // Un budget sans formule porte sur le prix à l'heure (comme le curseur de la page)
        $budget = max(0, (int) ($saisie['budget'] ?? 0));
        if ($budget > 0 && ! $formule) {
            $formule = Formule::Heure;
        }
        // Catégorie : « logement » (appartements, chambres…) ou « salle » (fêtes, conférences) ; vide = tout
        $categorie = array_key_exists((string) ($saisie['categorie'] ?? ''), TypeLogement::CATEGORIES) ? $saisie['categorie'] : null;
        $types = array_values(array_filter((array) ($saisie['type'] ?? []), fn ($t) => TypeLogement::tryFrom((string) $t)));
        $equipements = array_map('intval', (array) ($saisie['equipement'] ?? []));
        $services = array_map('intval', (array) ($saisie['service'] ?? []));
        // Position partagée par le visiteur (« Près de moi ») : logements les plus proches d'abord
        $position = Geo::position($saisie['lat'] ?? null, $saisie['lng'] ?? null);
        $tri = in_array($saisie['tri'] ?? null, ['pertinence', 'prix', 'note', 'distance'], true) ? $saisie['tri'] : ($position ? 'distance' : 'pertinence');
        if ($tri === 'distance' && ! $position) {
            $tri = 'pertinence';
        }

        return compact('ville', 'date', 'voyageurs', 'formule', 'budget', 'categorie', 'types', 'equipements', 'services', 'tri', 'position');
    }

    /**
     * Logements trouvés, triés. Chacun porte formules_libres, formule_affichee et, avec une position, distance_km.
     *
     * @param  array  $c  résultat de criteres()
     * @return array{logements: Collection<int, Logement>, hors_rayon: bool}
     */
    public function executer(array $c): array
    {
        ['ville' => $ville, 'date' => $date, 'voyageurs' => $voyageurs, 'formule' => $formule, 'budget' => $budget,
            'types' => $types, 'equipements' => $equipements, 'services' => $services, 'tri' => $tri, 'position' => $position] = $c;
        $categorie = $c['categorie'] ?? null;

        // ---------- Logements qui répondent aux critères fixes ----------
        $logements = Logement::query()->visibles()->avecNote()
            ->with(['tarifs', 'creneaux', 'services', 'equipements', 'photos' => fn ($q) => $q->orderBy('ordre')])
            ->where('capacite', '>=', $voyageurs)
            ->when($ville, fn (Builder $q) => $q->where('ville', $ville))
            ->when($categorie, fn (Builder $q) => $q->whereIn('type', array_map(fn (TypeLogement $t) => $t->value, TypeLogement::deCategorie($categorie))))
            ->when($types, fn (Builder $q) => $q->whereIn('type', $types))
            ->when($formule, fn (Builder $q) => $q->whereHas('tarifs', fn (Builder $t) => $t
                ->where('formule', $formule)->where('actif', true)
                ->when($budget > 0, fn (Builder $t) => $t->where('prix', '<=', $budget))))
            ->when(! $formule, fn (Builder $q) => $q->whereHas('tarifs', fn (Builder $t) => $t->where('actif', true)))
            ->tap(function (Builder $q) use ($equipements) {
                foreach ($equipements as $id) {
                    $q->whereHas('equipements', fn (Builder $e) => $e->whereKey($id));
                }
            })
            ->tap(function (Builder $q) use ($services) {
                foreach ($services as $id) {
                    $q->whereHas('services', fn (Builder $s) => $s->whereKey($id));
                }
            })
            ->get();

        // ---------- Disponibilité à la date choisie ----------
        $logements = $logements
            ->map(function (Logement $l) use ($date, $formule) {
                $resume = $this->disponibilites->resume($l, $date);
                $l->setAttribute('formules_libres', array_keys(array_filter($resume)));
                $l->setAttribute('formule_affichee', $formule ?? $l->formuleParDefaut());

                return $l;
            })
            ->filter(fn (Logement $l) => $formule ? in_array($formule->value, $l->formules_libres, true) : $l->formules_libres !== []);

        // ---------- Distance au visiteur ----------
        $horsRayon = false;
        if ($position) {
            $logements = $logements->each(function (Logement $l) use ($position) {
                $l->setAttribute('distance_km', $l->latitude !== null && $l->longitude !== null
                    ? Geo::distanceKm($position[0], $position[1], (float) $l->latitude, (float) $l->longitude) : null);
            });
            // Sans ville choisie : ceux du rayon ; s'il n'y en a aucun, tous, du plus proche au plus loin
            if (! $ville) {
                $proches = $logements->filter(fn (Logement $l) => $l->distance_km !== null && $l->distance_km <= Geo::RAYON_KM);
                $horsRayon = $proches->isEmpty();
                $logements = $horsRayon ? $logements : $proches;
            }
        }

        // ---------- Tri ----------
        $prix = fn (Logement $l) => $l->tarif($l->formule_affichee)?->prix ?? PHP_INT_MAX;
        $logements = match ($tri) {
            'distance' => $logements->sortBy(fn (Logement $l) => $l->distance_km ?? PHP_INT_MAX),
            'prix' => $logements->sortBy($prix),
            'note' => $logements->sortByDesc(fn (Logement $l) => [(float) $l->note_moyenne, $l->nombre_avis]),
            default => $logements->sortByDesc(fn (Logement $l) => sprintf('%05.2f-%05d', (float) $l->note_moyenne, $l->nombre_avis)),
        };

        return ['logements' => $logements->values(), 'hors_rayon' => $horsRayon];
    }
}
