<?php

namespace App\Paiement;

/** Statut d'une transaction tel que le prestataire le rapporte. */
final class EtatTransaction
{
    public const PAYEE = 'payee';

    public const EN_ATTENTE = 'en_attente';

    public const ECHOUEE = 'echouee';

    public function __construct(
        public readonly string $statut,
        public readonly ?string $moyen = null,
        public readonly array $donnees = [],
    ) {}

    public function estPayee(): bool
    {
        return $this->statut === self::PAYEE;
    }

    public function estEchouee(): bool
    {
        return $this->statut === self::ECHOUEE;
    }
}
