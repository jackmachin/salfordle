<?php

namespace App\Console\Commands;

use App\Models\DailyAnswer;
use App\Models\Guess;
use App\Models\Street;
use App\Streets\Geo;
use App\Streets\Sources\Overpass;
use App\Streets\Sources\WardBoundaries;
use App\Streets\StreetName;
use App\Streets\WayGrouper;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ImportStreets extends Command
{
    protected $signature = 'streets:import
        {--refresh : Re-download OSM and ward data instead of using the cached copies}';

    protected $description = 'Import Salford streets from OpenStreetMap, grouping OSM ways into distinct streets';

    public function handle(Overpass $overpass, WardBoundaries $wards): int
    {
        $refresh = (bool) $this->option('refresh');

        $this->components->info('Loading OSM roads, places and ward boundaries (cached unless --refresh)...');
        $ways = $overpass->roads($refresh);
        $places = $overpass->places($refresh);
        $wards->load($refresh);

        $streets = $this->buildStreets($ways, $places, $wards, $outside);
        $this->assignDisplayNames($streets);

        [$created, $updated, $deleted, $kept] = DB::transaction(fn () => $this->persist($streets));

        $this->newLine();
        $this->components->twoColumnDetail('OSM ways', count($ways));
        $this->components->twoColumnDetail('Streets imported', $streets->count());
        $this->components->twoColumnDetail('  created / updated / deleted', "{$created} / {$updated} / {$deleted}");
        $this->components->twoColumnDetail('Skipped (centre outside Salford)', $outside);
        $this->components->twoColumnDetail('Names shared by 2+ streets', $streets->where('shared', true)->pluck('key')->unique()->count());

        if ($kept) {
            $this->components->warn("{$kept} streets no longer in OSM were kept because games reference them.");
        }

        $this->reportTypes($streets);

        return self::SUCCESS;
    }

    private function buildStreets(array $ways, array $places, WardBoundaries $wards, ?int &$outside): Collection
    {
        $grouper = new WayGrouper(config('streetle.group_gap_metres'));
        $outside = 0;
        $streets = collect();

        $byName = collect($ways)->groupBy(fn (array $way) => StreetName::key($way['name']));

        foreach ($byName as $key => $group) {
            foreach ($grouper->group($group->all()) as $cluster) {
                $lines = array_column($cluster, 'coords');
                $anchor = Geo::anchor($lines);

                // Roads crossing the borough edge are returned too; keep only those centred inside it.
                if ($wards->wardAt($anchor) === null) {
                    $outside++;

                    continue;
                }

                $name = collect($cluster)
                    ->map(fn (array $way) => StreetName::clean($way['name']))
                    ->countBy()->sortDesc()->keys()->first();

                $streets->push([
                    'key' => $key,
                    'name' => $name,
                    'anchor' => $anchor,
                    'lines' => $lines,
                    'way_ids' => array_column($cluster, 'id'),
                    'length' => Geo::length($lines),
                    'sub_area' => $this->nearestPlace($anchor, $places),
                ]);
            }
        }

        return $streets;
    }

    private function nearestPlace(array $point, array $places): ?string
    {
        return collect($places)
            ->sortBy(fn (array $place) => Geo::distance($point, $place['point']))
            ->first()['name'] ?? null;
    }

    /** "Church Street (Eccles)" for names shared by several streets. */
    private function assignDisplayNames(Collection $streets): void
    {
        $streets->transform(fn (array $s) => $s + ['display_name' => $s['name'], 'shared' => false]);

        foreach ($streets->groupBy('key', preserveKeys: true)->filter(fn ($g) => $g->count() > 1) as $group) {
            $labels = $group->countBy(fn (array $s) => $s['sub_area'] ?? 'Salford');
            $seen = [];

            foreach ($group->sortBy(fn (array $s) => $s['anchor'][0])->keys() as $index) {
                $street = $streets[$index];
                $label = $street['sub_area'] ?? 'Salford';

                if ($labels[$label] > 1) {
                    $seen[$label] = ($seen[$label] ?? 0) + 1;
                    $label .= " {$seen[$label]}";
                }

                $streets[$index] = ['display_name' => "{$street['name']} ({$label})", 'shared' => true] + $street;
            }
        }
    }

    /** Update existing streets in place where possible so coverage data and game references survive a re-import. */
    private function persist(Collection $streets): array
    {
        // Free up display names so streets can swap them without tripping the unique index.
        // Load afterwards, so every re-matched street is dirty and gets its real name back.
        Street::query()->each(fn (Street $s) => $s->updateQuietly(['display_name' => "__reimport_{$s->id}"]));

        $existing = Street::all()->groupBy(fn (Street $s) => StreetName::key($s->name));
        $claimed = [];
        $created = $updated = 0;

        foreach ($streets as $street) {
            $match = ($existing[$street['key']] ?? collect())
                ->reject(fn (Street $s) => isset($claimed[$s->id]))
                ->map(fn (Street $s) => [$s, Geo::distance($street['anchor'], [$s->lng, $s->lat])])
                ->filter(fn (array $pair) => $pair[1] <= config('streetle.reimport_match_metres'))
                ->sortBy(1)
                ->first()[0] ?? null;

            $attributes = [
                'name' => $street['name'],
                'display_name' => $street['display_name'],
                'sub_area' => $street['sub_area'],
                'street_type' => StreetName::type($street['name']),
                'first_letter' => StreetName::firstLetter($street['name']),
                'lng' => round($street['anchor'][0], 6),
                'lat' => round($street['anchor'][1], 6),
                'length_m' => (int) round($street['length']),
                'geometry' => ['type' => 'MultiLineString', 'coordinates' => $street['lines']],
                'osm_way_ids' => $street['way_ids'],
            ];

            if ($match) {
                $match->update($attributes);
                $claimed[$match->id] = true;
                $updated++;
            } else {
                Street::create($attributes);
                $created++;
            }
        }

        $deleted = $kept = 0;

        foreach (Street::whereNotIn('id', array_keys($claimed))->where('display_name', 'like', '__reimport_%')->get() as $gone) {
            $referenced = DailyAnswer::where('street_id', $gone->id)->exists()
                || Guess::where('street_id', $gone->id)->exists();

            if ($referenced) {
                $gone->update(['display_name' => "{$gone->name} (retired #{$gone->id})", 'is_active' => false]);
                $kept++;
            } else {
                $gone->delete();
                $deleted++;
            }
        }

        return [$created, $updated, $deleted, $kept];
    }

    private function reportTypes(Collection $streets): void
    {
        $types = $streets->countBy(fn (array $s) => StreetName::type($s['name']))->sortDesc();

        $this->newLine();
        $this->table(['Street type', 'Streets'], $types->map(fn ($n, $type) => [$type, $n])->values());

        $otherEndings = $streets
            ->filter(fn (array $s) => StreetName::type($s['name']) === StreetName::OTHER)
            ->countBy(fn (array $s) => last(explode(' ', $s['name'])))
            ->sortDesc()->take(15);

        if ($otherEndings->isNotEmpty()) {
            $this->line('Most common endings classed as "Other": '
                .$otherEndings->map(fn ($n, $word) => "{$word} ({$n})")->implode(', '));
        }
    }
}
