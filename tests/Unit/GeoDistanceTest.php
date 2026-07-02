<?php

namespace Tests\Unit;

use App\Support\GeoDistance;
use PHPUnit\Framework\TestCase;

/**
 * Pure haversine math backing the accept-order service-zone guard. Kept as a
 * standalone helper so the distance formula is unit-testable without booting
 * the framework or the database.
 */
class GeoDistanceTest extends TestCase
{
    public function test_the_same_point_is_zero_kilometres_apart(): void
    {
        $this->assertSame(0.0, GeoDistance::haversineKm(11.5564, 104.9282, 11.5564, 104.9282));
    }

    public function test_one_degree_of_longitude_at_the_equator_is_about_111_km(): void
    {
        // 1° along the equator ≈ 111.19 km — the canonical haversine sanity check.
        $this->assertEqualsWithDelta(111.19, GeoDistance::haversineKm(0.0, 0.0, 0.0, 1.0), 0.5);
    }

    public function test_phnom_penh_to_bangkok_is_about_530_km(): void
    {
        // Real-world anchor used by the feature test's "outside the zone" case.
        $this->assertEqualsWithDelta(
            530.0,
            GeoDistance::haversineKm(11.5564, 104.9282, 13.7563, 100.5018),
            15.0,
        );
    }
}
