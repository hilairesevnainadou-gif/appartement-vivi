<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Envoi d'une notification sans jamais bloquer l'opération (un SMS ou un push qui échoue n'annule pas un paiement).
 * Dans une transaction, l'envoi attend la validation : rien ne part si l'opération est annulée.
 */
class Prevenir
{
    public static function sansBloquer(callable $envoi): void
    {
        DB::afterCommit(function () use ($envoi) {
            try {
                $envoi();
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
