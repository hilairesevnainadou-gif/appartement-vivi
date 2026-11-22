<?php

namespace App\Support;

/** Outils de géolocalisation (positions en degrés décimaux). */
final class Geo
{
    /** Rayon des « logements près de vous » quand le visiteur partage sa position. */
    public const RAYON_KM = 25;

    /** Distance à vol d'oiseau entre deux points, en kilomètres (formule de haversine). */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** « 850 m », « 3,4 km », « 27 km ». */
    public static function distanceLisible(float $km): string
    {
        return match (true) {
            $km < 1 => (string) (max(1, (int) round($km * 1000 / 50)) * 50).' m',
            $km < 10 => number_format($km, 1, ',', ' ').' km',
            default => (string) round($km).' km',
        };
    }

    /** Position valide (et plausible) envoyée par le navigateur, sinon null. */
    public static function position(mixed $lat, mixed $lng): ?array
    {
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }
        $lat = (float) $lat;
        $lng = (float) $lng;

        return abs($lat) <= 90 && abs($lng) <= 180 && ! ($lat == 0.0 && $lng == 0.0) ? [$lat, $lng] : null;
    }
}
