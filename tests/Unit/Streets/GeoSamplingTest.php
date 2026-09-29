<?php

namespace Tests\Unit\Streets;

use App\Streets\Geo;
use PHPUnit\Framework\TestCase;

class GeoSamplingTest extends TestCase
{
    // A straight street running due north, ~1 km long (0.009 degrees of latitude).
    private const NORTH = [[[-2.30, 53.480], [-2.30, 53.489]]];

    public function test_samples_are_spaced_along_the_street_with_its_bearing(): void
    {
        $samples = Geo::samplePoints(self::NORTH, 100);

        $this->assertCount(10, $samples); // 50 m, 150 m ... 950 m
        $this->assertEqualsWithDelta(50, Geo::distance(self::NORTH[0][0], $samples[0]['point']), 1);
        $this->assertEqualsWithDelta(100, Geo::distance($samples[0]['point'], $samples[1]['point']), 1);
        $this->assertEqualsWithDelta(0, $samples[0]['bearing'], 0.01);
    }

    public function test_a_street_shorter_than_the_spacing_still_gets_one_sample(): void
    {
        $stub = [[[-2.30, 53.4800], [-2.30, 53.4801]]]; // ~11 m cul-de-sac

        $this->assertCount(1, Geo::samplePoints($stub, 40));
    }

    public function test_distance_to_lines(): void
    {
        // ~20 m east of the street.
        $this->assertEqualsWithDelta(20, Geo::distanceToLines([-2.2997, 53.484], self::NORTH), 1);
    }

    public function test_heading_faces_along_the_street_towards_the_longer_side(): void
    {
        $nearStart = [-2.30, 53.481];
        $nearEnd = [-2.30, 53.488];

        $this->assertEqualsWithDelta(0, Geo::headingAlong($nearStart, self::NORTH), 0.01);
        $this->assertEqualsWithDelta(180, Geo::headingAlong($nearEnd, self::NORTH), 0.01);
    }
}
