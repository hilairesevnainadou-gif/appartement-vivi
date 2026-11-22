<?php

namespace App\Services;

use RuntimeException;

/** Le code présenté ne permet pas d'accueillir le client ; titre et texte sont affichés tels quels. */
class ArriveeRefusee extends RuntimeException
{
    public function __construct(public readonly string $titre, string $texte)
    {
        parent::__construct($texte);
    }
}
