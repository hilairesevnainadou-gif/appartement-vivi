<?php

namespace App\Http\Resources\Api;

use App\Enums\Formule;
use App\Models\Logement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Logement vu par son propriétaire : statut de vérification, adresse exacte, tarifs et horaires complets,
 * créneaux, équipements et services choisis. Mêmes noms de champs que ceux attendus à l'enregistrement
 * (POST /pro/logements), pour que l'application puisse renvoyer le formulaire tel quel.
 *
 * @mixin Logement
 */
class LogementProResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $heure = fn (?string $h) => $h ? substr($h, 0, 5) : null;
        $tarif = fn (Formule $f) => $this->relationLoaded('tarifs') ? $this->tarifs->firstWhere('formule', $f) : null;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'titre' => $this->titre,
            'type' => Format::enum($this->type),
            // « logement » ou « salle » (fêtes, conférence) : une salle n'a ni chambres ni lits, capacite = personnes
            'categorie' => $this->type?->categorie() ?? 'logement',
            'est_salle' => $this->estSalle(),
            'capacite_libelle' => $this->personnes(),
            'statut' => Format::enum($this->statut),
            'en_ligne' => (bool) $this->en_ligne,
            'visible' => $this->estVisible(),
            'motif_refus' => $this->motif_refus,
            'ville' => $this->ville,
            'quartier' => $this->quartier,
            'adresse' => $this->adresse,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'description' => $this->description,
            'capacite' => (int) $this->capacite,
            'chambres' => (int) $this->chambres,
            'unites' => (int) $this->unites,
            'lits' => (int) $this->lits,
            'salles_de_bain' => (int) $this->salles_de_bain,
            // Part de l'agent marketing : null = aucune ; mêmes noms qu'à l'enregistrement
            'marketing_type' => $this->marketing_type?->value,
            'marketing_valeur' => $this->marketing_type ? (float) $this->marketing_valeur : null,
            'temps_preparation_minutes' => (int) $this->temps_preparation_minutes,
            // Gestion : « proprietaire » (il accueille) ou « vivi » (gérant VIVI désigné par l'administration)
            'mode_gestion' => Format::enum($this->mode_gestion),
            'gerant_vivi' => $this->estGereParVivi() && $this->gerant_vivi_id
                ? ['id' => $this->gerant_vivi_id, 'nom' => $this->relationLoaded('gerantVivi') ? $this->gerantVivi?->name : null] : null,
            // Contrat de gestion fixé par l'administration (lecture seule), null tant qu'il n'est pas fixé
            'gestion_remuneration' => $this->libelleGestion() ? [
                'type' => $this->gestion_type->value, 'valeur' => (float) $this->gestion_valeur, 'libelle' => $this->libelleGestion(),
                'contrat' => $this->gestion_contrat, 'contrat_le' => $this->gestion_contrat_le?->toDateString(),
            ] : null,
            'gestion_vivi_demandee_le' => Format::date($this->gestion_vivi_demandee_le),
            'photo' => $this->relationLoaded('photos') ? $this->urlPhoto() : null,
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->sortBy('ordre')->values()
                ->map(fn ($p) => ['id' => $p->id, 'url' => Format::photo($p->chemin), 'legende' => $p->legende, 'ordre' => (int) $p->ordre])),
            'tarifs' => $this->whenLoaded('tarifs', fn () => [
                'heure' => ($t = $tarif(Formule::Heure)) ? [
                    'actif' => (bool) $t->actif, 'prix' => (int) $t->prix, 'heure_ouverture' => $heure($t->heure_ouverture),
                    'heure_fermeture' => $heure($t->heure_fermeture), 'duree_minimum_heures' => (int) $t->duree_minimum_heures,
                    'ouvert_24h' => $t->est24h(),
                ] : ['actif' => false],
                'demi_journee' => ($t = $tarif(Formule::DemiJournee)) ? ['actif' => (bool) $t->actif, 'prix' => (int) $t->prix] : ['actif' => false],
                'journee' => ($t = $tarif(Formule::Journee)) ? [
                    'actif' => (bool) $t->actif, 'prix' => (int) $t->prix,
                    'heure_arrivee' => $heure($t->heure_arrivee), 'heure_depart' => $heure($t->heure_depart),
                ] : ['actif' => false],
            ]),
            'creneaux' => $this->whenLoaded('creneaux', fn () => $this->creneaux->sortBy('ordre')->values()->map(fn ($c) => [
                'id' => $c->id, 'nom' => $c->nom, 'heure_debut' => $heure($c->heure_debut), 'heure_fin' => $heure($c->heure_fin),
            ])),
            'equipements' => $this->whenLoaded('equipements', fn () => $this->equipements->modelKeys()),
            'services' => $this->whenLoaded('services', fn () => $this->services->map(fn ($s) => [
                'id' => $s->id, 'nom' => $s->nom, 'beneficiaire' => $s->pivot->beneficiaire ?? null,
            ])->values()),
            'cree_le' => Format::date($this->created_at),
        ];
    }
}
