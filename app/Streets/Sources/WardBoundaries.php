<?php

namespace App\Streets\Sources;

use App\Streets\Geo;
use RuntimeException;

class WardBoundaries extends CachedSource
{
    /** @var list<array{name: string, code: string, geometry: array, bbox: array}>|null */
    private ?array $wards = null;

    public function load(bool $refresh = false): static
    {
        $config = config('streetle.wards');

        $collection = $this->cached('wards.geojson', $refresh, function () use ($config) {
            $response = $this->http()->get($config['url'], [
                'where' => "{$config['district_field']}='".config('streetle.borough_gss')."'",
                'outFields' => "{$config['code_field']},{$config['name_field']}",
                'outSR' => 4326,
                'f' => 'geojson',
            ]);

            if (! $response->successful() || empty($response->json('features'))) {
                throw new RuntimeException("Ward boundary download failed: HTTP {$response->status()}");
            }

            return $response->json();
        });

        $this->wards = array_map(function (array $feature) use ($config) {
            $points = array_merge(...array_merge(...$this->polygons($feature['geometry'])));
            $lngs = array_column($points, 0);
            $lats = array_column($points, 1);

            return [
                'name' => $feature['properties'][$config['name_field']],
                'code' => $feature['properties'][$config['code_field']],
                'geometry' => $feature['geometry'],
                'bbox' => [min($lngs), min($lats), max($lngs), max($lats)],
            ];
        }, $collection['features']);

        return $this;
    }

    /** Ward name containing the point, or null if it is outside the borough. */
    public function wardAt(array $point): ?string
    {
        $this->wards ?? $this->load();

        foreach ($this->wards as $ward) {
            [$minLng, $minLat, $maxLng, $maxLat] = $ward['bbox'];

            if ($point[0] < $minLng || $point[0] > $maxLng || $point[1] < $minLat || $point[1] > $maxLat) {
                continue;
            }

            if (Geo::pointInPolygon($point, $ward['geometry'])) {
                return $ward['name'];
            }
        }

        return null;
    }

    /** @return list<string> */
    public function names(): array
    {
        $this->wards ?? $this->load();

        return array_column($this->wards, 'name');
    }

    private function polygons(array $geometry): array
    {
        return $geometry['type'] === 'Polygon' ? [$geometry['coordinates']] : $geometry['coordinates'];
    }
}
