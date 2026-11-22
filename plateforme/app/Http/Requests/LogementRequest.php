<?php

namespace App\Http\Requests;

use App\Enums\TypeLogement;
use App\Support\Parametres;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Fiche d'un logement saisie par son propriétaire : informations, équipements, tarifs, créneaux, services. */
class LogementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // l'accès est contrôlé par les routes (rôle propriétaire) et par le contrôleur (ses logements)
    }

    private function estSalle(): bool
    {
        return (bool) TypeLogement::tryFrom((string) $this->input('type'))?->estSalle();
    }

    public function rules(): array
    {
        $heure = ['nullable', 'date_format:H:i'];

        return [
            'titre' => ['required', 'string', 'min:4', 'max:120'],
            'type' => ['required', Rule::enum(TypeLogement::class)],
            'ville' => ['required', Rule::in(Parametres::get('villes'))],
            'quartier' => ['required', 'string', 'max:120'],
            'adresse' => ['nullable', 'string', 'max:255'],
            // Logement : quelques voyageurs ; salle de fêtes ou de conférence : jusqu'à 1 000 personnes
            'capacite' => ['required', 'integer', 'min:1', 'max:'.($this->estSalle() ? TypeLogement::CAPACITE_MAX_SALLE : TypeLogement::CAPACITE_MAX_LOGEMENT)],
            'chambres' => ['required', 'integer', 'min:0', 'max:20'],
            'unites' => ['nullable', 'integer', 'min:1', 'max:200'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'lits' => ['required', 'integer', 'min:'.($this->estSalle() ? 0 : 1), 'max:20'],
            'salles_de_bain' => ['required', 'integer', 'min:0', 'max:10'],
            'description' => ['nullable', 'string', 'max:3000'],
            'temps_preparation_minutes' => ['required', 'integer', Rule::in([0, 30, 60, 120])],
            // « proprietaire » : il accueille lui-même ; « vivi » : il confie la gestion à VIVI
            'mode_gestion' => ['nullable', Rule::enum(\App\Enums\ModeGestion::class)],
            'equipements' => ['array'],
            'equipements.*' => ['integer', 'exists:equipements,id'],
            'services' => ['array'],
            'services.*' => ['integer', 'exists:services,id'],

            // Part de l'agent marketing : pourcentage de la location (50 % au plus) ou montant fixe par réservation
            'marketing_type' => ['nullable', Rule::enum(\App\Enums\TypeMarketing::class)],
            'marketing_valeur' => ['nullable', 'required_with:marketing_type', 'numeric', 'min:0',
                Rule::when($this->input('marketing_type') === 'pourcentage', ['max:'.\App\Enums\TypeMarketing::POURCENTAGE_MAX], ['max:1000000'])],

            'tarifs.heure.actif' => ['boolean'],
            'tarifs.heure.prix' => ['exclude_unless:tarifs.heure.actif,true', 'required', 'integer', 'min:500', 'max:1000000'],
            'tarifs.heure.heure_ouverture' => ['exclude_unless:tarifs.heure.actif,true', 'required', ...$heure],
            'tarifs.heure.heure_fermeture' => ['exclude_unless:tarifs.heure.actif,true', 'required', ...$heure],
            'tarifs.heure.duree_minimum_heures' => ['exclude_unless:tarifs.heure.actif,true', 'required', 'integer', 'min:1', 'max:6'],

            'tarifs.demi_journee.actif' => ['boolean'],
            'tarifs.demi_journee.prix' => ['exclude_unless:tarifs.demi_journee.actif,true', 'required', 'integer', 'min:500', 'max:1000000'],
            'creneaux' => ['array', 'max:4'],
            'creneaux.*.id' => ['nullable', 'integer'],
            'creneaux.*.nom' => ['required', 'string', 'max:40'],
            'creneaux.*.heure_debut' => ['required', ...$heure],
            'creneaux.*.heure_fin' => ['required', ...$heure],

            'tarifs.journee.actif' => ['boolean'],
            'tarifs.journee.prix' => ['exclude_unless:tarifs.journee.actif,true', 'required', 'integer', 'min:500', 'max:5000000'],
            'tarifs.journee.heure_arrivee' => ['exclude_unless:tarifs.journee.actif,true', 'required', ...$heure],
            'tarifs.journee.heure_depart' => ['exclude_unless:tarifs.journee.actif,true', 'required', ...$heure],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $t = (array) $this->input('tarifs', []);
            $actives = array_filter(['heure', 'demi_journee', 'journee'], fn ($f) => (bool) ($t[$f]['actif'] ?? false));
            if ($actives === []) {
                $v->errors()->add('tarifs', 'Activez au moins une formule : à l’heure, à la demi-journée ou à la journée.');
            }
            if (in_array('heure', $actives, true)) {
                $de = $t['heure']['heure_ouverture'] ?? '';
                $a = $t['heure']['heure_fermeture'] ?? '';
                $min = (int) ($t['heure']['duree_minimum_heures'] ?? 1);
                // Fermeture à 00:00 = minuit
                $minutes = fn ($h) => $h === '00:00' ? 1440 : ((int) substr($h, 0, 2) * 60 + (int) substr($h, 3, 2));
                if ($de !== '' && $a !== '' && $minutes($a) - $minutes($de) < $min * 60) {
                    $v->errors()->add('tarifs.heure.heure_fermeture', "La fermeture doit laisser au moins {$min} h après l’ouverture.");
                }
            }
            if (in_array('demi_journee', $actives, true) && count((array) $this->input('creneaux', [])) === 0) {
                $v->errors()->add('creneaux', 'Ajoutez au moins un créneau pour la formule à la demi-journée.');
            }
            foreach ((array) $this->input('creneaux', []) as $i => $c) {
                if (($c['heure_debut'] ?? '') !== '' && ($c['heure_debut'] ?? '') === ($c['heure_fin'] ?? '')) {
                    $v->errors()->add("creneaux.$i.heure_fin", 'Le créneau doit finir après son début.');
                }
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'titre' => 'nom du logement',
            'capacite' => $this->estSalle() ? 'capacité (personnes)' : 'nombre de voyageurs',
            'salles_de_bain' => 'salles de bain',
            'tarifs.heure.prix' => 'prix par heure',
            'tarifs.demi_journee.prix' => 'prix du créneau',
            'tarifs.journee.prix' => 'prix par jour',
            'creneaux.*.nom' => 'nom du créneau',
            'marketing_type' => 'part de l’agent marketing',
            'marketing_valeur' => 'part de l’agent marketing',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Cases à cocher absentes = formule désactivée
        $t = (array) $this->input('tarifs', []);
        foreach (['heure', 'demi_journee', 'journee'] as $f) {
            $t[$f]['actif'] = (bool) ($t[$f]['actif'] ?? false);
        }
        $this->merge(['tarifs' => $t, 'creneaux' => array_values((array) $this->input('creneaux', []))]);

        // Une salle n'a ni chambres ni lits, et se loue d'un seul tenant
        if ($this->estSalle()) {
            $this->merge(['chambres' => 0, 'lits' => 0, 'unites' => 1, 'salles_de_bain' => (int) $this->input('salles_de_bain', 0)]);
        }
    }
}
