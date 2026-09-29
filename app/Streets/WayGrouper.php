<?php

namespace App\Streets;

/**
 * Groups OSM ways that share a name into distinct streets. OSM splits one
 * street into many ways, but a name like "Church Street" can also belong to
 * several unrelated streets across the borough. Ways are joined when they
 * share a node or come within $gapMetres of each other (single linkage).
 */
final class WayGrouper
{
    public function __construct(private float $gapMetres) {}

    /**
     * @param  list<array{id: int, nodes: list<int>, coords: list<array{0: float, 1: float}>}>  $ways  ways with the same name key
     * @return list<list<array>> clusters of ways
     */
    public function group(array $ways): array
    {
        $n = count($ways);

        if ($n <= 1) {
            return $n ? [$ways] : [];
        }

        $parent = range(0, $n - 1);
        $find = function (int $i) use (&$parent): int {
            while ($parent[$i] !== $i) {
                $parent[$i] = $parent[$parent[$i]];
                $i = $parent[$i];
            }

            return $i;
        };
        $union = function (int $a, int $b) use (&$parent, $find): void {
            $parent[$find($a)] = $find($b);
        };

        // Shared nodes: the common case for a street split into segments.
        $nodeOwner = [];

        foreach ($ways as $i => $way) {
            foreach ($way['nodes'] as $node) {
                if (isset($nodeOwner[$node])) {
                    $union($i, $nodeOwner[$node]);
                } else {
                    $nodeOwner[$node] = $i;
                }
            }
        }

        // Proximity: dual carriageways, small gaps, missing junction nodes.
        [$toLocal] = Geo::localProjection($ways[0]['coords'][0][1]);
        $local = [];
        $boxes = [];

        foreach ($ways as $i => $way) {
            $local[$i] = array_map($toLocal, $way['coords']);
            $xs = array_column($local[$i], 0);
            $ys = array_column($local[$i], 1);
            $boxes[$i] = [min($xs), min($ys), max($xs), max($ys)];
        }

        $gapSquared = $this->gapMetres ** 2;

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($find($i) === $find($j) || ! $this->boxesNear($boxes[$i], $boxes[$j])) {
                    continue;
                }

                if ($this->verticesNear($local[$i], $local[$j], $gapSquared)) {
                    $union($i, $j);
                }
            }
        }

        $clusters = [];

        foreach ($ways as $i => $way) {
            $clusters[$find($i)][] = $way;
        }

        return array_values($clusters);
    }

    private function boxesNear(array $a, array $b): bool
    {
        $gap = $this->gapMetres;

        return $a[0] - $gap <= $b[2] && $b[0] - $gap <= $a[2]
            && $a[1] - $gap <= $b[3] && $b[1] - $gap <= $a[3];
    }

    private function verticesNear(array $a, array $b, float $gapSquared): bool
    {
        foreach ($a as [$ax, $ay]) {
            foreach ($b as [$bx, $by]) {
                if (($ax - $bx) ** 2 + ($ay - $by) ** 2 <= $gapSquared) {
                    return true;
                }
            }
        }

        return false;
    }
}
