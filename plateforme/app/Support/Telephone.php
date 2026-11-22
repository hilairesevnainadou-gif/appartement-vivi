<?php

namespace App\Support;

/**
 * Numéros de téléphone béninois. Depuis 2024, ils ont 10 chiffres et commencent par 01.
 * Stockés sur 10 chiffres (0197000000) ; envoyés en international (+2290197000000).
 */
class Telephone
{
    /** « +229 01 97 00 00 00 », « 0197000000 », « 229 0197000000 » → « 0197000000 » ; null si invalide. */
    public static function normaliser(?string $saisie): ?string
    {
        $chiffres = preg_replace('/\D/', '', (string) $saisie);

        if (str_starts_with($chiffres, '229') && strlen($chiffres) === 13) {
            $chiffres = substr($chiffres, 3);
        }

        return preg_match('/^01\d{8}$/', $chiffres) ? $chiffres : null;
    }

    public static function international(string $numero): string
    {
        return config('vivi.sms.indicatif', '+229').$numero;
    }

    /** « 0197000000 » → « 01 97 00 00 00 » */
    public static function lisible(string $numero): string
    {
        return trim(chunk_split($numero, 2, ' '));
    }

    /**
     * Numéro WhatsApp de n'importe quel pays, au format international « +<indicatif><numéro> » (E.164).
     * « +33 6 12 34 56 78 », « 0033612345678 » → « +33612345678 » ; un numéro béninois local « 01 97 00 00 00 »
     * → « +2290197000000 ». Null si la saisie n'est pas un numéro international plausible (8 à 15 chiffres).
     */
    public static function whatsapp(?string $saisie): ?string
    {
        $saisie = trim((string) $saisie);
        if ($beninois = self::normaliser($saisie)) {
            return self::international($beninois);
        }
        $chiffres = preg_replace('/\D/', '', $saisie);
        if (str_starts_with($saisie, '00')) {
            $chiffres = substr($chiffres, 2);
        } elseif (! str_starts_with($saisie, '+')) {
            return null;   // sans indicatif, impossible de savoir le pays
        }

        return preg_match('/^[1-9]\d{7,14}$/', $chiffres) ? '+'.$chiffres : null;
    }

    /** Numéro béninois (10 chiffres) d'un numéro WhatsApp international, sinon null. */
    public static function beninoisDe(?string $international): ?string
    {
        return $international && str_starts_with($international, '+229') ? self::normaliser($international) : null;
    }

    /** Lien « Écrire sur WhatsApp » vers ce numéro. */
    public static function lienWhatsapp(?string $international, ?string $message = null): ?string
    {
        return $international
            ? 'https://wa.me/'.ltrim($international, '+').($message ? '?text='.rawurlencode($message) : '')
            : null;
    }

    /** « +2290197000000 » → « +229 01 97 00 00 00 » ; autres pays : tel quel. */
    public static function lisibleInternational(?string $international): ?string
    {
        if (! $international) {
            return null;
        }
        if ($beninois = self::beninoisDe($international)) {
            return '+229 '.self::lisible($beninois);
        }

        return $international;
    }

    /** « 0197000000 » → « 01 •• •• •• 00 » pour l'afficher sans le dévoiler entièrement */
    public static function masque(string $numero): string
    {
        return substr($numero, 0, 2).' •• •• •• '.substr($numero, -2);
    }
}
