<?php

namespace App\Streets\Sources;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Street View Image Metadata API. Metadata requests are free (no per-request
 * charge) but still count against the key's rate limits.
 */
class StreetViewMetadata
{
    /** Statuses that mean the key itself is refused, so carrying on is pointless. */
    private const FATAL = ['OVER_QUERY_LIMIT', 'REQUEST_DENIED'];

    /**
     * Look up the nearest outdoor pano for each point, concurrently.
     *
     * @param  array<int|string, array{0: float, 1: float}>  $points  keyed by caller id
     * @return array<int|string, array{status: string, pano_id: ?string, point: ?array, date: ?string, copyright: ?string}|null>
     *                                                                                                                             null where the request failed or Google said to retry
     */
    public function lookup(array $points): array
    {
        $key = config('services.google_maps.key');

        if (! $key) {
            throw new RuntimeException('GOOGLE_MAPS_KEY is not set.');
        }

        $config = config('streetle.coverage');

        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn ($id) => $pool->as((string) $id)->timeout(30)
                ->withHeaders(array_filter(['Referer' => config('services.google_maps.referer')]))
                ->get($config['metadata_url'], [
                    'location' => "{$points[$id][1]},{$points[$id][0]}",
                    'radius' => $config['search_radius_metres'],
                    'source' => 'outdoor',
                    'key' => $key,
                ]),
            array_keys($points),
        ), concurrency: $config['concurrency']);

        $results = [];

        foreach (array_keys($points) as $id) {
            $response = $responses[(string) $id] ?? null;
            $results[$id] = $response instanceof Response && $response->successful()
                ? $this->parse($response->json())
                : null;
        }

        return $results;
    }

    private function parse(?array $body): ?array
    {
        $status = $body['status'] ?? null;

        // Google's own "server error, try again": not an answer about coverage.
        if ($status === null || $status === 'UNKNOWN_ERROR') {
            return null;
        }

        if (in_array($status, self::FATAL, true)) {
            // Mask anything key-shaped in case Google's message quotes the request.
            $message = preg_replace('/AIza[\w-]+/', '[key]', (string) ($body['error_message'] ?? ''));

            throw new RuntimeException("Street View Metadata API refused the request ({$status}): {$message}");
        }

        return [
            'status' => $status,
            'pano_id' => $body['pano_id'] ?? null,
            'point' => isset($body['location']) ? [$body['location']['lng'], $body['location']['lat']] : null,
            'date' => $body['date'] ?? null,
            'copyright' => $body['copyright'] ?? null,
        ];
    }
}
