<?php

namespace App\Paiement;

use App\Models\Paiement;

/** Passerelle de paiement : FedaPay en production, simulation en local. */
interface Passerelle
{
    /** Nom enregistré dans paiements.passerelle. */
    public function nom(): string;

    /**
     * Crée la transaction chez le prestataire et renvoie l'adresse de la page de paiement.
     * Enregistre l'identifiant de transaction dans $paiement->reference_passerelle.
     *
     * @throws EchecPaiement
     */
    public function demarrer(Paiement $paiement, string $urlRetour): string;

    /**
     * Statut réel de la transaction, lu chez le prestataire (jamais déduit des paramètres de retour).
     *
     * @throws EchecPaiement
     */
    public function consulter(Paiement $paiement): EtatTransaction;
}
