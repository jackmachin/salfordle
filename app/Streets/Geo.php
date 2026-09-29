<?php

namespace App\Streets;

/**
 * Small geometry helpers. Points are GeoJSON-ordered [lng, lat] pairs throughout.
 */
final class Geo
{
    private const EARTH_RADIUS_M = 6371008.8;

    private const METRES_PER_DEGREE_LAT = 110574.0;

    private const METRES_PER_DEGREE_LNG_AT_EQUATOR = 111320.0;

    /** Great-circle distance in metres. */
    public static function distance(array $a, array $b): float
    {
        $lat1 = deg2rad($a[1]);
        $lat2 = deg2rad($b[1]);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad($b[0] - $a[0]);

        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($h)));
    }

    /** Initial bearing from $a to $b in degrees, 0-360 clockwise from north. */
    public static function bearing(array $a, array $b): float
    {
        $lat1 = deg2rad($a[1]);
        $lat2 = deg2rad($b[1]);
        $dLng = deg2rad($b[0] - $a[0]);

        $y = sin($dLng) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLng);

        return fmod(rad2deg(atan2($y, $x)) + 360, 360);
    }

    /** Total length in metres of a set of lines. */
    public static function length(array $lines): float
    {
        $total = 0.0;

        foreach ($lines as $line) {
            for ($i = 1, $n = count($line); $i < $n; $i++) {
                $total += self::distance($line[$i - 1], $line[$i]);
            }
        }

        return $total;
    }

    /**
     * A representative point that lies on the street: the length-weighted
     * centre of the lines, snapped to the nearest point on them.
     */
    public static function anchor(array $lines): array
    {
        $sumLng = $sumLat = $total = 0.0;

        foreach ($lines as $line) {
            for ($i = 1, $n = count($line); $i < $n; $i++) {
                $length = self::distance($line[$i - 1], $line[$i]);
                $sumLng += ($line[$i - 1][0] + $line[$i][0]) / 2 * $length;
                $sumLat += ($line[$i - 1][1] + $line[$i][1]) / 2 * $length;
                $total += $length;
            }
        }

        if ($total == 0.0) {
            return $lines[0][0];
        }

        return self::nearestPointOnLines([$sumLng / $total, $sumLat / $total], $lines);
    }

    public static function nearestPointOnLines(array $point, array $lines): array
    {
        return self::nearest($point, $lines)['point'];
    }

    /** Metres from a point to the nearest point on the lines. */
    public static function distanceToLines(array $point, array $lines): float
    {
        return self::nearest($point, $lines)['distance'];
    }

    /**
     * Camera heading for a pano on the street: along the street rather than
     * across it (where the signs and house numbers are), facing whichever
     * way has more street ahead.
     */
    public static function headingAlong(array $point, array $lines): float
    {
        $nearest = self::nearest($point, $lines);
        $line = $lines[$nearest['line']];
        $i = $nearest['segment'];

        if (count($line) < 2) {
            return 0.0;
        }

        $bearing = self::bearing($line[$i], $line[$i + 1]);

        $behind = self::distance($line[$i], $nearest['point']);
        for ($k = 1; $k <= $i; $k++) {
            $behind += self::distance($line[$k - 1], $line[$k]);
        }

        return $behind > self::length([$line]) / 2 ? fmod($bearing + 180, 360) : $bearing;
    }

    /**
     * Points every $spacing metres along each line (starting half a gap in),
     * with the street's bearing at each point. Always returns at least one.
     *
     * @return list<array{point: array{0: float, 1: float}, bearing: float}>
     */
    public static function samplePoints(array $lines, float $spacing): array
    {
        $samples = [];

        foreach ($lines as $line) {
            $next = $spacing / 2;
            $walked = 0.0;

            for ($i = 1, $n = count($line); $i < $n; $i++) {
                $length = self::distance($line[$i - 1], $line[$i]);

                while ($length > 0 && $next <= $walked + $length) {
                    $t = ($next - $walked) / $length;
                    $samples[] = [
                        'point' => [
                            $line[$i - 1][0] + $t * ($line[$i][0] - $line[$i - 1][0]),
                            $line[$i - 1][1] + $t * ($line[$i][1] - $line[$i - 1][1]),
                        ],
                        'bearing' => self::bearing($line[$i - 1], $line[$i]),
                    ];
                    $next += $spacing;
                }

                $walked += $length;
            }
        }

        if ($samples === []) {
            $anchor = self::anchor($lines);
            $samples[] = ['point' => $anchor, 'bearing' => self::headingAlong($anchor, $lines)];
        }

        return $samples;
    }

    /** @return array{point: array, distance: float, line: int, segment: int} */
    private static function nearest(array $point, array $lines): array
    {
        [$toLocal, $toGeo] = self::localProjection($point[1]);
        [$px, $py] = $toLocal($point);

        $best = null;

        foreach ($lines as $l => $line) {
            if (count($line) === 1) {
                $line[] = $line[0];
            }

            for ($i = 1, $n = count($line); $i < $n; $i++) {
                [$ax, $ay] = $toLocal($line[$i - 1]);
                [$bx, $by] = $toLocal($line[$i]);
                $dx = $bx - $ax;
                $dy = $by - $ay;
                $lengthSquared = $dx * $dx + $dy * $dy;

                $t = $lengthSquared > 0
                    ? max(0.0, min(1.0, (($px - $ax) * $dx + ($py - $ay) * $dy) / $lengthSquared))
                    : 0.0;

                $cx = $ax + $t * $dx;
                $cy = $ay + $t * $dy;
                $distance = sqrt(($px - $cx) ** 2 + ($py - $cy) ** 2);

                if ($best === null || $distance < $best['distance']) {
                    $best = ['point' => [$cx, $cy], 'distance' => $distance, 'line' => $l, 'segment' => $i - 1];
                }
            }
        }

        $best['point'] = $toGeo($best['point']);

        return $best;
    }

    /** Evenly spread vertices across the lines, at most $count of them. */
    public static function spreadVertices(array $lines, int $count): array
    {
        $vertices = array_merge(...array_values($lines));
        $n = count($vertices);

        if ($n <= $count) {
            return $vertices;
        }

        $picked = [];

        for ($i = 0; $i < $count; $i++) {
            $picked[] = $vertices[(int) round($i * ($n - 1) / max(1, $count - 1))];
        }

        return $picked;
    }

    /** Whether a point falls inside a GeoJSON Polygon or MultiPolygon geometry. */
    public static function pointInPolygon(array $point, array $geometry): bool
    {
        $polygons = match ($geometry['type']) {
            'Polygon' => [$geometry['coordinates']],
            'MultiPolygon' => $geometry['coordinates'],
            default => [],
        };

        foreach ($polygons as $rings) {
            if (! self::inRing($point, $rings[0])) {
                continue;
            }

            $inHole = false;

            foreach (array_slice($rings, 1) as $hole) {
                if (self::inRing($point, $hole)) {
                    $inHole = true;
                    break;
                }
            }

            if (! $inHole) {
                return true;
            }
        }

        return false;
    }

    /**
     * Equirectangular projection to metres around a latitude. Accurate enough
     * for the few-kilometre distances within a borough.
     *
     * @return array{0: \Closure(array): array, 1: \Closure(array): array}
     */
    public static function localProjection(float $originLat): array
    {
        $lngScale = self::METRES_PER_DEGREE_LNG_AT_EQUATOR * cos(deg2rad($originLat));

        return [
            fn (array $p) => [$p[0] * $lngScale, $p[1] * self::METRES_PER_DEGREE_LAT],
            fn (array $p) => [$p[0] / $lngScale, $p[1] / self::METRES_PER_DEGREE_LAT],
        ];
    }

    private static function inRing(array $point, array $ring): bool
    {
        [$x, $y] = $point;
        $inside = false;

        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];

            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
