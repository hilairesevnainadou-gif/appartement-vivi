<?php

namespace App\Paiement;

use App\Models\Paiement;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Paiement simulé, pour travailler en local sans compte FedaPay : une page VIVI propose
 * « Paiement réussi » ou « Paiement refusé ». Rien n'est débité. Interdit en production.
 */
class SimulationPasserelle implements Passerelle
{
    public function nom(): string
    {
        return 'simulation';
    }

    public function demarrer(Paiement $paiement, string $urlRetour): string
    {
        $paiement->forceFill([
            'reference_passerelle' => 'SIMU-'.Str::upper(Str::random(8)),
            'donnees' => ['simulation' => 'en_attente', 'retour' => $urlRetour],
        ])->save();

        return URL::temporarySignedRoute('paiement.simulation', now()->addHour(), ['paiement' => $paiement]);
    }

    public function consulter(Paiement $paiement): EtatTransaction
    {
        $choix = $paiement->donnees['simulation'] ?? 'en_attente';

        return new EtatTransaction(
            statut: match ($choix) {
                'reussi' => EtatTransaction::PAYEE,
                'refuse' => EtatTransaction::ECHOUEE,
                default => EtatTransaction::EN_ATTENTE,
            },
            moyen: $paiement->donnees['moyen'] ?? null,
            donnees: ['simulation' => $choix],
        );
    }
}
