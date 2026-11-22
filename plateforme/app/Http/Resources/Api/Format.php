<?php

namespace App\Http\Resources\Api;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Facades\Storage;

/** Petits formats communs aux réponses de l'API mobile : énumérations, dates, photos. */
final class Format
{
    /** Énumération → {valeur, libelle} (libellé français affichable tel quel). */
    public static function enum(?BackedEnum $e): ?array
    {
        if (! $e) {
            return null;
        }

        return ['valeur' => $e->value, 'libelle' => method_exists($e, 'getLabel') ? $e->getLabel() : $e->value];
    }

    /** Date et heure en ISO 8601 avec fuseau (Africa/Porto-Novo). */
    public static function date(?DateTimeInterface $d): ?string
    {
        return $d?->format(DATE_ATOM);
    }

    /** Jour seul : AAAA-MM-JJ. */
    public static function jour(?DateTimeInterface $d): ?string
    {
        return $d?->format('Y-m-d');
    }

    public static function photo(?string $chemin): ?string
    {
        return $chemin ? Storage::disk('public')->url($chemin) : null;
    }
}
