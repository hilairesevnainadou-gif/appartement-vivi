<?php

namespace Tests\Concerns;

use App\Enums\StatutAbonnement;
use App\Models\Abonnement;
use App\Models\FormuleAbonnement;
use App\Models\User;

/** Donne au propriétaire un abonnement en cours : sans lui, ses logements ne sont pas visibles des clients. */
trait AvecAbonnement
{
    protected function abonner(User $proprietaire, int $logementsMax = 10): Abonnement
    {
        $formule = FormuleAbonnement::firstOrCreate(['nom' => 'Test '.$logementsMax], [
            'prix_mensuel' => 10000, 'logements_max' => $logementsMax, 'gestionnaires_max' => 1, 'actif' => true,
        ]);

        return $proprietaire->abonnements()->create([
            'formule_abonnement_id' => $formule->id, 'debut' => today()->subDays(5), 'fin' => today()->addDays(25),
            'prix_paye' => $formule->prix_mensuel, 'statut' => StatutAbonnement::Actif,
        ]);
    }
}
