<?php

namespace App\Services;

use App\Enums\Formule;
use App\Support\Montant;
use Carbon\CarbonImmutable;

/** Prix détaillé d'un séjour, calculé par le serveur. Montants en FCFA entiers. */
final class Devis
{
    /**
     * @param  list<array{service_id: int, nom: string, quantite: int, prix_unitaire: int, montant: int}>  $lignesServices
     */
    public function __construct(
        public readonly Formule $formule,
        public readonly CarbonImmutable $debut,
        public readonly CarbonImmutable $fin,
        public readonly int $location,
        public readonly string $detailLocation,
        public readonly array $lignesServices,
        public readonly int $services,
        public readonly float $tauxCommission,
        public readonly int $commission,
        public readonly int $total,
        public readonly ?int $creneauId = null,
        public readonly int $bonus = 0,
        public readonly int $remiseAgent = 0,   // part marketing que l'agent ne paie pas quand il réserve un logement qu'il promeut
    ) {}

    /** Même devis, avec une part du total réglée par le bonus du client. */
    public function avecBonus(int $bonus): self
    {
        return new self($this->formule, $this->debut, $this->fin, $this->location, $this->detailLocation, $this->lignesServices,
            $this->services, $this->tauxCommission, $this->commission, $this->total, $this->creneauId, max(0, min($bonus, $this->total)), $this->remiseAgent);
    }

    /** Même devis pour un agent qui réserve un logement qu'il promeut : sa part marketing est déduite du total. */
    public function avecRemiseAgent(int $remise): self
    {
        $remise = max(0, min($remise, $this->location));

        return new self($this->formule, $this->debut, $this->fin, $this->location, $this->detailLocation, $this->lignesServices,
            $this->services, $this->tauxCommission, $this->commission, $this->total + $this->remiseAgent - $remise, $this->creneauId,
            min($this->bonus, $this->total + $this->remiseAgent - $remise), $remise);
    }

    /** Montant à payer par FedaPay. */
    public function aPayer(): int
    {
        return $this->total - $this->bonus;
    }

    /** Forme envoyée au navigateur (bloc de réservation de la fiche logement). */
    public function enTableau(): array
    {
        return [
            'formule' => $this->formule->value,
            'debut' => $this->debut->toIso8601String(),
            'fin' => $this->fin->toIso8601String(),
            'location' => $this->location,
            'detail_location' => $this->detailLocation,
            'services' => $this->services,
            'lignes_services' => $this->lignesServices,
            'taux_commission' => $this->tauxCommission,
            'commission' => $this->commission,
            'remise_agent' => $this->remiseAgent,
            'total' => $this->total,
            'bonus' => $this->bonus,
            'a_payer' => $this->aPayer(),
            'texte' => [
                'location' => Montant::fcfa($this->location),
                'services' => Montant::fcfa($this->services),
                'commission' => Montant::fcfa($this->commission),
                'remise_agent' => Montant::fcfa($this->remiseAgent),
                'total' => Montant::fcfa($this->total),
                'bonus' => Montant::fcfa($this->bonus),
                'a_payer' => Montant::fcfa($this->aPayer()),
            ],
        ];
    }
}
