<?php

namespace Tests\Unit\Streets;

use App\Streets\Geo;
use PHPUnit\Framework\TestCase;

class GeoTest extends TestCase
{
    // Salford Crescent station -> MediaCityUK, roughly 2.1 km apart.
    private const CRESCENT = [-2.2757, 53.4863];

    private const MEDIACITY = [-2.2966, 53.4722];

    public function test_distance(): void
    {
        $this->assertEqualsWithDelta(2085, Geo::distance(self::CRESCENT, self::MEDIACITY), 20);
        $this->assertSame(0.0, Geo::distance(self::CRESCENT, self::CRESCENT));
    }

    public function test_bearing(): void
    {
        $this->assertEqualsWithDelta(0, Geo::bearing([0, 0], [0, 1]), 0.001);
        $this->assertEqualsWithDelta(90, Geo::bearing([0, 0], [1, 0]), 0.001);
        $this->assertEqualsWithDelta(180, Geo::bearing([0, 1], [0, 0]), 0.001);
        $this->assertEqualsWithDelta(270, Geo::bearing([1, 0], [0, 0]), 0.001);
    }

    public function test_anchor_lies_on_the_line(): void
    {
        // An L-shaped street: the plain centroid would sit off the street, inside the corner.
        $lines = [[[0.0, 0.0], [0.0, 0.01]], [[0.0, 0.01], [0.01, 0.01]]];

        $anchor = Geo::anchor($lines);
        $onLine = abs($anchor[0]) < 1e-9 || abs($anchor[1] - 0.01) < 1e-9;

        $this->assertTrue($onLine, 'anchor '.json_encode($anchor).' is not on the street');
    }

    public function test_point_in_polygon_respects_holes_and_multipolygons(): void
    {
        $square = [[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]];
        $hole = [[4, 4], [6, 4], [6, 6], [4, 6], [4, 4]];
        $polygon = ['type' => 'Polygon', 'coordinates' => [$square, $hole]];

        $this->assertTrue(Geo::pointInPolygon([2, 2], $polygon));
        $this->assertFalse(Geo::pointInPolygon([5, 5], $polygon));
        $this->assertFalse(Geo::pointInPolygon([11, 5], $polygon));

        $multi = ['type' => 'MultiPolygon', 'coordinates' => [
            [$square],
            [[[20, 20], [30, 20], [30, 30], [20, 30], [20, 20]]],
        ]];

        $this->assertTrue(Geo::pointInPolygon([25, 25], $multi));
        $this->assertFalse(Geo::pointInPolygon([15, 15], $multi));
    }

    public function test_spread_vertices(): void
    {
        $lines = [[[0, 0], [1, 0], [2, 0]], [[3, 0], [4, 0]]];

        $this->assertSame([[0, 0], [2, 0], [4, 0]], Geo::spreadVertices($lines, 3));
        $this->assertCount(5, Geo::spreadVertices($lines, 10));
    }
}
