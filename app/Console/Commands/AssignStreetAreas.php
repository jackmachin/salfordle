<?php

namespace App\Console\Commands;

use App\Models\Street;
use App\Streets\Geo;
use App\Streets\Sources\Postcodes;
use App\Streets\Sources\WardBoundaries;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssignStreetAreas extends Command
{
    protected $signature = 'streets:assign-areas
        {--refresh : Re-download ward boundaries instead of using the cached copy}
        {--missing : Only streets without a ward or postcode district yet}';

    protected $description = 'Assign council ward (point-in-polygon on the street centre) and postcode district to each street';

    /** Vertices checked per street when looking for streets that cross a ward boundary. */
    private const STRADDLE_POINTS = 8;

    public function handle(WardBoundaries $wards, Postcodes $postcodes): int
    {
        $wards->load((bool) $this->option('refresh'));

        $streets = Street::query()
            ->when($this->option('missing'), fn ($q) => $q->where(
                fn ($q) => $q->whereNull('ward')->orWhereNull('postcode_district')
            ))
            ->get();

        if ($streets->isEmpty()) {
            $this->components->info('No streets to update. Run streets:import first.');

            return self::SUCCESS;
        }

        $this->components->info("Assigning wards to {$streets->count()} streets...");
        $straddlers = [];

        foreach ($streets as $street) {
            $anchor = [$street->lng, $street->lat];
            $street->ward = $wards->wardAt($anchor);

            $touched = collect(Geo::spreadVertices($street->geometry['coordinates'], self::STRADDLE_POINTS))
                ->map(fn (array $p) => $wards->wardAt($p))
                ->push($street->ward)
                ->filter()->unique();

            if ($touched->count() > 1) {
                $straddlers[] = [$street->display_name, $street->length_m, $street->ward, $touched->implode(', ')];
            }
        }

        $this->components->info('Looking up postcode districts via postcodes.io...');
        $this->assignOutcodes($streets, $postcodes);

        DB::transaction(fn () => $streets->each->save());

        $this->report($streets, $straddlers);

        return self::SUCCESS;
    }

    /**
     * Majority vote over the nearest postcode at several points along each
     * street, so a long street isn't decided by whichever postcode happens to
     * sit nearest its middle. Ties go to the street centre's postcode.
     */
    private function assignOutcodes(Collection $streets, Postcodes $postcodes): void
    {
        $perStreet = config('streetle.postcodes.points_per_street');
        $points = [];
        $owners = [];

        foreach ($streets as $i => $street) {
            $samples = [[$street->lng, $street->lat],
                ...Geo::spreadVertices($street->geometry['coordinates'], $perStreet - 1)];

            foreach ($samples as $point) {
                $points[] = $point;
                $owners[] = $i;
            }
        }

        $votes = [];

        foreach ($postcodes->outcodes($points) as $j => $outcode) {
            if ($outcode !== null) {
                $votes[$owners[$j]][] = $outcode;
            }
        }

        foreach ($streets as $i => $street) {
            $counts = array_count_values($votes[$i] ?? []);
            // array_count_values keeps first-seen order and arsort is stable, so the centre wins ties.
            arsort($counts);
            $street->postcode_district = array_key_first($counts);
        }
    }

    private function report(Collection $streets, array $straddlers): void
    {
        $all = Street::all();

        $this->newLine();
        $this->table(['Ward', 'Streets'], $all->countBy(fn ($s) => $s->ward ?? '(none)')->sortKeys()
            ->map(fn ($n, $ward) => [$ward, $n])->values());

        $this->line('Postcode districts: '.$all->countBy(fn ($s) => $s->postcode_district ?? '(none)')
            ->sortKeys()->map(fn ($n, $pc) => "{$pc} ({$n})")->implode(', '));

        $this->newLine();
        $this->components->twoColumnDetail('Streets updated', $streets->count());
        $this->components->twoColumnDetail('Without a ward', $all->whereNull('ward')->count());
        $this->components->twoColumnDetail('Without a postcode district', $all->whereNull('postcode_district')->count());
        $this->components->twoColumnDetail('Crossing a ward boundary', count($straddlers));

        if ($straddlers) {
            usort($straddlers, fn ($a, $b) => $b[1] <=> $a[1]);
            $this->newLine();
            $this->line('Longest streets crossing a ward boundary (ward assigned from the street centre):');
            $this->table(['Street', 'Length (m)', 'Assigned', 'Touches'], array_slice($straddlers, 0, 15));
        }
    }
}
