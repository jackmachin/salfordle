<?php

namespace Tests\Unit\Streets;

use App\Streets\StreetLocator;
use PHPUnit\Framework\TestCase;

class StreetLocatorTest extends TestCase
{
    public function test_it_finds_the_nearest_streets_within_range(): void
    {
        // Two parallel north-south streets ~100 m apart, and one far away.
        $locator = new StreetLocator([
            1 => [[[-2.3000, 53.480], [-2.3000, 53.485]]],
            2 => [[[-2.2985, 53.480], [-2.2985, 53.485]]],
            3 => [[[-2.2500, 53.480], [-2.2500, 53.485]]],
        ]);

        // ~20 m east of street 1, ~80 m west of street 2.
        $near = $locator->near([-2.2997, 53.4825], 90);

        $this->assertSame([1, 2], array_keys($near));
        $this->assertEqualsWithDelta(20, $near[1], 2);
        $this->assertEqualsWithDelta(80, $near[2], 2);

        $this->assertSame([1], array_keys($locator->near([-2.2997, 53.4825], 50)));
        $this->assertSame([], $locator->near([-2.2700, 53.4825], 90));
    }

    public function test_long_segments_are_found_from_cells_far_from_their_ends(): void
    {
        // A single 2 km segment with no vertices near the query point.
        $locator = new StreetLocator([7 => [[[-2.30, 53.470], [-2.30, 53.488]]]], cellMetres: 100);

        $this->assertSame([7], array_keys($locator->near([-2.3003, 53.479], 50)));
    }
}
