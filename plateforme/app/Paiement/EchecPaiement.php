<?php

namespace App\Paiement;

use RuntimeException;

/** Le prestataire de paiement a refusé la demande ou ne répond pas. */
class EchecPaiement extends RuntimeException {}
