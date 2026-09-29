<?php

namespace App\Streets\Sources;

use RuntimeException;

class Overpass extends CachedSource
{
    /**
     * Named road ways in the borough.
     *
     * @return list<array{id: int, name: string, highway: string, nodes: list<int>, coords: list<array{0: float, 1: float}>}>
     */
    public function roads(bool $refresh = false): array
    {
        $types = implode('|', config('streetle.overpass.highway_types'));

        $elements = $this->cached('overpass-roads.json', $refresh, fn () => $this->query(
            "{$this->area()}way(area.a)[\"highway\"~\"^({$types})$\"][\"name\"];out geom;"
        ));

        return array_map(fn (array $way) => [
            'id' => $way['id'],
            'name' => $way['tags']['name'],
            'highway' => $way['tags']['highway'],
            'nodes' => $way['nodes'],
            'coords' => array_map(fn (array $p) => [$p['lon'], $p['lat']], $way['geometry']),
        ], $elements);
    }

    /**
     * Named places (towns, suburbs, neighbourhoods) in the borough.
     *
     * @return list<array{name: string, place: string, point: array{0: float, 1: float}}>
     */
    public function places(bool $refresh = false): array
    {
        $types = implode('|', config('streetle.overpass.place_types'));

        $elements = $this->cached('overpass-places.json', $refresh, fn () => $this->query(
            "{$this->area()}node(area.a)[\"place\"~\"^({$types})$\"][\"name\"];out;"
        ));

        return array_map(fn (array $node) => [
            'name' => $node['tags']['name'],
            'place' => $node['tags']['place'],
            'point' => [$node['lon'], $node['lat']],
        ], $elements);
    }

    private function area(): string
    {
        $gss = config('streetle.borough_gss');

        return "[out:json][timeout:300];area[\"boundary\"=\"administrative\"][\"ref:gss\"=\"{$gss}\"]->.a;";
    }

    private function query(string $ql): array
    {
        $errors = [];

        foreach (config('streetle.overpass.endpoints') as $endpoint) {
            $response = $this->http()->asForm()->post($endpoint, ['data' => $ql]);
            $elements = $response->successful() ? $response->json('elements') : null;

            // A busy server can answer 200 with an HTML error page instead of JSON.
            if (is_array($elements)) {
                return $elements;
            }

            $errors[] = "{$endpoint}: HTTP {$response->status()}";
        }

        throw new RuntimeException('All Overpass endpoints failed: '.implode('; ', $errors));
    }
}
