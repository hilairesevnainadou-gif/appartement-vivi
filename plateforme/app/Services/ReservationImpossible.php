<?php

namespace App\Services;

use RuntimeException;

/** Le séjour demandé ne peut pas (ou plus) être réservé ; le message est destiné au client. */
class ReservationImpossible extends RuntimeException {}
