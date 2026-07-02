<?php

namespace App\Support;

/**
 * Great-circle distance between two WGS-84 points.
 *
 * Backs the accept-order service-zone guard. A center+radius check is a
 * deliberate simplification of 6amMart's polygon zones: chabhouy has no zone
 * geometry seeded, so proximity to a configured centre is the faithful
 * minimal port of "you are outside the service area, move closer".
 */
final class GeoDistance
{
    /** Mean Earth radius in kilometres. */
    private const EARTH_RADIUS_KM = 6371.0;

    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
