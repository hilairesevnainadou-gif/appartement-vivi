<?php

namespace App\Http\Resources\Api;

/**
 * Réservation vue par le propriétaire ou le gestionnaire : client, origine (plateforme ou hors plateforme),
 * crédit au solde, encaissement sur place et note interne. Ni QR Code ni code de secours.
 */
class ReservationProResource extends ReservationResource
{
    protected bool $pro = true;
}
