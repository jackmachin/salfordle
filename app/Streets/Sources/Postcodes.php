<?php

namespace App\Streets\Sources;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Reverse geocoding via postcodes.io (ONS Postcode Directory, no key needed). */
class Postcodes
{
    private const BATCH_SIZE = 100; // postcodes.io bulk limit

    /**
     * Postcode district (outcode) of the nearest postcode to each point.
     *
     * @param  list<array{0: float, 1: float}>  $points
     * @return list<string|null> in the same order; null when nothing is within the radius
     */
    public function outcodes(array $points): array
    {
        $outcodes = [];

        foreach ($this->reverse($points, config('streetle.postcodes.radius_metres'), 1) as $nearby) {
            $outcodes[] = $nearby[0]['outcode'] ?? null;
        }

        return $outcodes;
    }

    /**
     * Every postcode centred within $radius of any of the points.
     *
     * @param  list<array{0: float, 1: float}>  $points
     * @param  (callable(int): void)|null  $progress  called with the number of points done after each batch
     * @return array<string, array{0: float, 1: float}> postcode => centroid [lng, lat]
     */
    public function nearby(array $points, int $radius, int $limit, ?callable $progress = null): array
    {
        $postcodes = [];

        foreach ($this->reverse($points, $radius, $limit, $progress) as $nearby) {
            foreach ($nearby as $result) {
                $postcodes[$result['postcode']] = [$result['longitude'], $result['latitude']];
            }
        }

        return $postcodes;
    }

    /**
     * postcodes.io results for each point, nearest first, yielded batch by batch
     * so the (large) raw responses never pile up in memory.
     *
     * @return \Generator<int, list<array>>
     */
    private function reverse(array $points, int $radius, int $limit, ?callable $progress = null): \Generator
    {
        foreach (array_chunk($points, self::BATCH_SIZE) as $batch) {
            $response = Http::withUserAgent(config('streetle.user_agent'))
                ->timeout(60)
                ->retry(3, 2000)
                ->post(config('streetle.postcodes.url'), [
                    'geolocations' => array_map(fn (array $p) => [
                        'longitude' => $p[0],
                        'latitude' => $p[1],
                        'radius' => $radius,
                        'limit' => $limit,
                    ], $batch),
                ]);

            $batchResults = $response->json('result');

            if (! is_array($batchResults) || count($batchResults) !== count($batch)) {
                throw new RuntimeException("postcodes.io lookup failed: HTTP {$response->status()}");
            }

            foreach ($batchResults as $result) {
                yield $result['result'] ?? [];
            }

            if ($progress) {
                $progress(count($batch));
            }
        }
    }
}
