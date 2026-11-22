<?php

namespace App\Http\Resources\Api;

use App\Models\Logement;
use App\Models\LogementPhoto;
use App\Support\Montant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Logement vu par un client : carte de résultat (relations non chargées = champs absents) ou fiche complète.
 * L'adresse exacte n'est jamais donnée ici : elle figure sur le billet après le paiement.
 *
 * @mixin Logement
 */
class LogementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $formule = $this->formule_affichee ?? ($this->relationLoaded('tarifs') ? $this->formuleParDefaut() : null);
        $tarif = $formule && $this->relationLoaded('tarifs') ? $this->tarif($formule) : null;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'titre' => $this->titre,
            'type' => Format::enum($this->type),
            // « logement » ou « salle » (fêtes, conférence) : une salle n'a ni chambres ni lits, capacite = personnes
            'categorie' => $this->type?->categorie() ?? 'logement',
            'est_salle' => $this->estSalle(),
            'capacite_libelle' => $this->personnes(),
            'ville' => $this->ville,
            'quartier' => $this->quartier,
            'capacite' => (int) $this->capacite,
            'chambres' => (int) $this->chambres,
            'lits' => (int) $this->lits,
            'salles_de_bain' => (int) $this->salles_de_bain,
            'unites' => (int) $this->unites,
            'note' => $this->nombre_avis ? round((float) $this->note_moyenne, 1) : null,
            'nombre_avis' => (int) ($this->nombre_avis ?? 0),
            'photo' => $this->relationLoaded('photos') ? $this->urlPhoto() : null,
            'a_partir_de' => $tarif ? [
                'formule' => Format::enum($formule), 'prix' => (int) $tarif->prix,
                'prix_texte' => Montant::fcfa($tarif->prix), 'unite' => $formule->unite(),
            ] : null,
            'formules_libres' => $this->when($this->formules_libres !== null, fn () => $this->formules_libres),
            'distance_km' => $this->when($this->distance_km !== null, fn () => round((float) $this->distance_km, 1)),
            // Zone approximative (cercle de 400 m) : jamais la position exacte avant le paiement
            'zone' => ($z = $this->positionApprochee()) ? ['latitude' => $z[0], 'longitude' => $z[1], 'rayon_m' => 400] : null,

            // ---------- Fiche complète ----------
            'description' => $this->when($request->routeIs('api.logements.show'), $this->description),
            'temps_preparation_minutes' => $this->when($request->routeIs('api.logements.show'), (int) $this->temps_preparation_minutes),
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->sortBy('ordre')->values()
                ->map(fn (LogementPhoto $p) => ['id' => $p->id, 'url' => Format::photo($p->chemin), 'legende' => $p->legende])),
            'equipements' => $this->whenLoaded('equipements', fn () => $this->equipements
                ->map(fn ($e) => ['id' => $e->id, 'nom' => $e->nom, 'icone' => $e->icone])->values()),
            'services' => $this->whenLoaded('services', fn () => $this->services->where('actif', true)->sortBy('ordre')->values()
                ->map(fn ($s) => ['id' => $s->id, 'nom' => $s->nom, 'description' => $s->description, 'prix' => (int) $s->prix, 'unite' => $s->unite, 'icone' => $s->icone])),
            'tarifs' => $this->whenLoaded('tarifs', fn () => collect($this->formulesProposees())->map(fn ($f) => [
                'formule' => Format::enum($f),
                'prix' => (int) $this->tarif($f)->prix,
                'prix_texte' => Montant::fcfa($this->tarif($f)->prix),
                'unite' => $f->unite(),
            ])),
        ];
    }
}
