<?php

namespace Tests\Unit\Streets;

use App\Streets\WayGrouper;
use PHPUnit\Framework\TestCase;

class WayGrouperTest extends TestCase
{
    // ~0.0009 degrees of latitude is ~100 m.
    private function way(int $id, array $nodes, array $coords): array
    {
        return ['id' => $id, 'nodes' => $nodes, 'coords' => $coords];
    }

    private function ids(array $clusters): array
    {
        $ids = array_map(fn ($c) => array_column($c, 'id'), $clusters);
        array_walk($ids, fn (&$c) => sort($c));
        usort($ids, fn ($a, $b) => $a[0] <=> $b[0]);

        return $ids;
    }

    public function test_ways_sharing_a_node_are_one_street(): void
    {
        $clusters = (new WayGrouper(150))->group([
            $this->way(1, [10, 11], [[-2.30, 53.480], [-2.30, 53.481]]),
            $this->way(2, [11, 12], [[-2.30, 53.481], [-2.30, 53.482]]),
        ]);

        $this->assertSame([[1, 2]], $this->ids($clusters));
    }

    public function test_nearby_ways_without_a_shared_node_are_one_street(): void
    {
        // Dual carriageway: two parallel ways ~20 m apart.
        $clusters = (new WayGrouper(150))->group([
            $this->way(1, [10, 11], [[-2.3000, 53.480], [-2.3000, 53.485]]),
            $this->way(2, [20, 21], [[-2.3003, 53.480], [-2.3003, 53.485]]),
        ]);

        $this->assertSame([[1, 2]], $this->ids($clusters));
    }

    public function test_distant_ways_with_the_same_name_are_separate_streets(): void
    {
        // Two "Church Street"s a few kilometres apart, the first one in two segments.
        $clusters = (new WayGrouper(150))->group([
            $this->way(1, [10, 11], [[-2.30, 53.480], [-2.30, 53.481]]),
            $this->way(2, [11, 12], [[-2.30, 53.481], [-2.30, 53.482]]),
            $this->way(3, [30, 31], [[-2.35, 53.500], [-2.35, 53.501]]),
        ]);

        $this->assertSame([[1, 2], [3]], $this->ids($clusters));
    }

    public function test_linking_is_transitive(): void
    {
        // 1 and 3 are far apart but both touch 2.
        $clusters = (new WayGrouper(150))->group([
            $this->way(1, [1, 2], [[-2.30, 53.480], [-2.30, 53.484]]),
            $this->way(3, [5, 6], [[-2.30, 53.488], [-2.30, 53.492]]),
            $this->way(2, [2, 5], [[-2.30, 53.484], [-2.30, 53.488]]),
        ]);

        $this->assertSame([[1, 2, 3]], $this->ids($clusters));
    }
}
