<?php

namespace App\Services;

use App\Enums\StatutPaiement;
use App\Models\Paiement;
use App\Paiement\EchecPaiement;
use App\Paiement\Passerelle;

/**
 * Statut d'un paiement relu chez le prestataire puis appliqué (réservation confirmée, abonnement activé…).
 * Utilisé par l'application mobile, qui interroge le serveur au retour de la page de paiement ;
 * le webhook FedaPay reste la source principale de confirmation.
 */
class SuiviPaiements
{
    public function __construct(
        private readonly Passerelle $passerelle,
        private readonly Reservations $reservations,
        private readonly Abonnements $abonnements,
    ) {}

    public function actualiser(Paiement $paiement): Paiement
    {
        if ($paiement->statut !== StatutPaiement::EnAttente) {
            return $paiement;
        }

        try {
            $etat = $this->passerelle->consulter($paiement);
        } catch (EchecPaiement) {
            return $paiement;   // prestataire injoignable : le webhook confirmera, l'application réessaie
        }

        $abonnement = $paiement->payable_type === 'abonnement';
        if ($etat->estPayee()) {
            $abonnement ? $this->abonnements->activer($paiement, $etat) : $this->reservations->confirmer($paiement, $etat);
        } elseif ($etat->estEchouee()) {
            $abonnement ? $this->abonnements->echouer($paiement, $etat) : $this->reservations->echouer($paiement, $etat);
        }

        return $paiement->refresh();
    }
}
