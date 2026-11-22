<?php

namespace App\Sms;

/**
 * Envoi d'un SMS. Le pilote utilisé est choisi dans config/vivi.php (sms.pilote) :
 * changer de fournisseur ne demande qu'une nouvelle classe qui implémente cette interface.
 */
interface Sms
{
    /**
     * @param  string  $numero  numéro au format international, ex. +2290197000000
     *
     * @throws EchecEnvoiSms
     */
    public function envoyer(string $numero, string $message): void;
}
