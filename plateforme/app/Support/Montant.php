<?php

namespace App\Support;

/** Affichage des montants en francs CFA (entiers, sans centimes). */
class Montant
{
    /** 18000 → « 18 000 FCFA » (espace insécable entre les milliers et avant l'unité). */
    public static function fcfa(int|float|string|null $montant): string
    {
        if ($montant === null || $montant === '') {
            return '—';
        }

        return number_format((float) $montant, 0, ',', "\u{00A0}")."\u{00A0}FCFA";
    }
}
