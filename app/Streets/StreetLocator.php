<?php

namespace App\Streets;

use Closure;

/**
 * Finds the streets nearest a point. Segments are bucketed into a grid of
 * square cells so a lookup only measures the few segments close by.
 */
final class StreetLocator
{
    /** @var array<string, list<array{int, float, float, float, float}>> cell => [street id, ax, ay, bx, by] */
    private array $cells = [];

    private Closure $toLocal;

    /**
     * @param  array<int, array>  $streets  street id => lines ([lng, lat] points)
     */
    public function __construct(array $streets, private float $cellMetres = 100)
    {
        $firstPoint = $streets ? reset($streets)[0][0] : [0, 53.49];
        [$this->toLocal] = Geo::localProjection($firstPoint[1]);

        foreach ($streets as $id => $lines) {
            foreach ($lines as $line) {
                for ($i = 1, $n = count($line); $i < $n; $i++) {
                    [$ax, $ay] = ($this->toLocal)($line[$i - 1]);
                    [$bx, $by] = ($this->toLocal)($line[$i]);

                    foreach ($this->cellsCovering(min($ax, $bx), min($ay, $by), max($ax, $bx), max($ay, $by)) as $cell) {
                        $this->cells[$cell][] = [$id, $ax, $ay, $bx, $by];
                    }
                }
            }
        }
    }

    /**
     * Streets within $maxMetres of the point, nearest first.
     *
     * @return array<int, float> street id => distance in metres
     */
    public function near(array $point, float $maxMetres): array
    {
        [$px, $py] = ($this->toLocal)($point);
        $found = [];

        foreach ($this->cellsCovering($px - $maxMetres, $py - $maxMetres, $px + $maxMetres, $py + $maxMetres) as $cell) {
            foreach ($this->cells[$cell] ?? [] as [$id, $ax, $ay, $bx, $by]) {
                $distance = self::segmentDistance($px, $py, $ax, $ay, $bx, $by);

                if ($distance <= $maxMetres && $distance < ($found[$id] ?? INF)) {
                    $found[$id] = $distance;
                }
            }
        }

        asort($found);

        return $found;
    }

    /** @return list<string> */
    private function cellsCovering(float $minX, float $minY, float $maxX, float $maxY): array
    {
        $cells = [];

        for ($x = (int) floor($minX / $this->cellMetres); $x <= (int) floor($maxX / $this->cellMetres); $x++) {
            for ($y = (int) floor($minY / $this->cellMetres); $y <= (int) floor($maxY / $this->cellMetres); $y++) {
                $cells[] = "{$x}:{$y}";
            }
        }

        return $cells;
    }

    private static function segmentDistance(float $px, float $py, float $ax, float $ay, float $bx, float $by): float
    {
        $dx = $bx - $ax;
        $dy = $by - $ay;
        $lengthSquared = $dx * $dx + $dy * $dy;
        $t = $lengthSquared > 0 ? max(0.0, min(1.0, (($px - $ax) * $dx + ($py - $ay) * $dy) / $lengthSquared)) : 0.0;

        return sqrt(($px - $ax - $t * $dx) ** 2 + ($py - $ay - $t * $dy) ** 2);
    }
}
