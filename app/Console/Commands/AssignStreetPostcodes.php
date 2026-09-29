<?php

namespace App\Console\Commands;

use App\Models\Street;
use App\Streets\Geo;
use App\Streets\Sources\Postcodes;
use App\Streets\StreetLocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AssignStreetPostcodes extends Command
{
    protected $signature = 'streets:assign-postcodes
        {--refresh : Re-collect postcodes from postcodes.io instead of using the cached copy}';

    protected $description = 'Attach full unit postcodes to streets (for postcode search in the guess box)';

    private const CACHE = 'streetle/postcodes-nearby.json';

    public function handle(Postcodes $postcodes): int
    {
        $config = config('streetle.postcodes');
        $streets = Street::all(['id', 'geometry']);
        $lines = $streets->mapWithKeys(fn (Street $s) => [$s->id => $s->geometry['coordinates']])->all();

        $found = $this->collect($postcodes, $lines, $config);
        $this->components->info(count($found).' postcodes found near Salford streets. Matching them to streets...');

        $locator = new StreetLocator($lines);
        $byStreet = [];
        $unmatched = 0;

        foreach ($found as $postcode => $centre) {
            $near = $locator->near($centre, $config['max_street_metres']);

            if (! $near) {
                $unmatched++;

                continue;
            }

            $nearest = reset($near);

            foreach ($near as $id => $distance) {
                if ($distance <= $nearest + $config['tie_metres']) {
                    $byStreet[$id][] = $postcode;
                }
            }
        }

        DB::transaction(fn () => $streets->each(function (Street $street) use ($byStreet) {
            $codes = $byStreet[$street->id] ?? [];
            sort($codes);
            $street->update(['postcodes' => $codes]);
        }));

        $this->newLine();
        $this->components->twoColumnDetail('Postcodes matched to a street', count($found) - $unmatched);
        $this->components->twoColumnDetail('Postcodes with no street within '.$config['max_street_metres'].'m', $unmatched);
        $this->components->twoColumnDetail('Streets with at least one postcode', count($byStreet).' / '.$streets->count());

        return self::SUCCESS;
    }

    /** @return array<string, array{0: float, 1: float}> postcode => centroid */
    private function collect(Postcodes $postcodes, array $lines, array $config): array
    {
        $disk = Storage::disk('local');

        if (! $this->option('refresh') && $disk->exists(self::CACHE)) {
            return json_decode($disk->get(self::CACHE), true, flags: JSON_THROW_ON_ERROR);
        }

        // Points along every street, thinned to one per ~50 m grid square so overlapping streets don't repeat queries.
        $points = [];

        foreach ($lines as $streetLines) {
            foreach (Geo::samplePoints($streetLines, $config['search_spacing_metres']) as $sample) {
                [$lng, $lat] = $sample['point'];
                // ~45 x 50 m squares at Salford's latitude.
                $points[(int) round($lng * 1500).':'.(int) round($lat * 2200)] = $sample['point'];
            }
        }

        $this->components->info('Collecting postcodes around '.count($points).' points via postcodes.io...');
        $bar = $this->output->createProgressBar(count($points));

        $found = $postcodes->nearby(array_values($points), $config['search_radius_metres'], 40, fn (int $n) => $bar->advance($n));

        $bar->finish();
        $this->newLine(2);

        $disk->put(self::CACHE, json_encode($found, JSON_THROW_ON_ERROR));

        return $found;
    }
}
