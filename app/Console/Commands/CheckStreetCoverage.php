<?php

namespace App\Console\Commands;

use App\Models\Street;
use App\Models\StreetSample;
use App\Streets\Geo;
use App\Streets\Sources\StreetViewMetadata;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CheckStreetCoverage extends Command
{
    protected $signature = 'streets:check-coverage
        {--limit= : Maximum Metadata API calls this run (the run is resumable)}
        {--street=* : Only these streets (id or display name)}
        {--resample : Regenerate sample points for the selected streets (discards their results)}
        {--rescore : Recompute scores and locked panos from stored results, without calling the API}
        {--give-up-failing : Record points Google still errors on as API_ERROR (not usable) instead of leaving them for a retry}';

    protected $description = 'Sample points along each street, check Street View coverage, score streets and lock a pano and heading';

    /** Sample statuses we derive on top of Google's own (OK, ZERO_RESULTS, NOT_FOUND...). */
    public const USABLE = 'OK';

    public function handle(StreetViewMetadata $metadata): int
    {
        $streets = Street::query()
            ->when($this->option('street'), fn (Builder $q, array $refs) => $q->where(
                fn (Builder $q) => $q->whereIn('id', array_filter($refs, 'is_numeric'))->orWhereIn('display_name', $refs)
            ));

        $this->generateSamples((clone $streets), (bool) $this->option('resample'));

        $failed = false;

        if (! $this->option('rescore')) {
            try {
                $this->checkSamples($metadata, (clone $streets)->pluck('id'));
            } catch (RuntimeException $e) {
                // Progress so far is already saved; score what we have and stop.
                $this->components->error($e->getMessage());
                $failed = true;
            }
        }

        $this->score((clone $streets)->get());
        $this->report();

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function generateSamples(Builder $streets, bool $resample): void
    {
        $config = config('streetle.coverage');

        if (! $resample) {
            $streets->whereDoesntHave('samples');
        }

        $count = 0;

        // lazyById, not each(): streets drop out of whereDoesntHave as they gain samples, which would shift offset paging.
        $streets->lazyById(200)->each(function (Street $street) use ($config, $resample, &$count) {
            $lines = $street->geometry['coordinates'];
            $spacing = max($config['sample_spacing_metres'], Geo::length($lines) / $config['max_samples_per_street']);

            $rows = [];

            foreach (Geo::samplePoints($lines, $spacing) as $seq => $sample) {
                $rows[] = [
                    'seq' => $seq,
                    'lng' => round($sample['point'][0], 6),
                    'lat' => round($sample['point'][1], 6),
                    'bearing' => (int) round($sample['bearing']) % 360,
                ];
            }

            DB::transaction(function () use ($street, $rows, $resample) {
                if ($resample) {
                    $street->samples()->delete();
                    $street->update(['coverage_score' => null, 'pano_id' => null, 'pano_date' => null, 'heading' => null]);
                }

                $street->samples()->createMany($rows);
            });

            $count++;
        });

        if ($count) {
            $this->components->info("Generated sample points for {$count} streets.");
        }
    }

    private function checkSamples(StreetViewMetadata $metadata, Collection $streetIds): void
    {
        $query = StreetSample::whereNull('checked_at')->whereIn('street_id', $streetIds)->orderBy('street_id')->orderBy('seq');
        $total = min((clone $query)->count(), (int) ($this->option('limit') ?: PHP_INT_MAX));

        if ($total === 0) {
            $this->components->info('No unchecked sample points.');

            return;
        }

        $this->components->info("Checking {$total} sample points with the Street View Metadata API...");
        $bar = $this->output->createProgressBar($total);
        $lines = [];
        $done = $failedBatches = 0;

        while ($done < $total) {
            $batch = (clone $query)->with('street:id,geometry')->limit(min(100, $total - $done))->get();

            if ($batch->isEmpty()) {
                break;
            }

            $results = $metadata->lookup($batch->mapWithKeys(fn (StreetSample $s) => [$s->id => [$s->lng, $s->lat]])->all());

            $giveUp = (bool) $this->option('give-up-failing');

            DB::transaction(function () use ($batch, $results, $giveUp, &$lines) {
                foreach ($batch as $sample) {
                    if ($results[$sample->id] === null) {
                        // Some locations make Google error every time, whatever the radius or source.
                        if ($giveUp) {
                            $sample->update(['status' => 'API_ERROR', 'checked_at' => now()]);
                        }

                        continue; // otherwise left unchecked for the next run
                    }

                    $lines[$sample->street_id] ??= $sample->street->geometry['coordinates'];
                    $this->recordResult($sample, $results[$sample->id], $lines[$sample->street_id]);
                }
            });

            $failures = $giveUp ? 0 : collect($results)->filter(fn ($r) => $r === null)->count();
            $checked = $batch->count() - $failures;
            $done += $checked;
            $bar->advance($checked);

            // Google's errors come in bursts. Failed points stay unchecked and are picked up
            // again by the next batch, so back off and retry rather than stopping.
            if ($failures === 0) {
                $failedBatches = 0;
            } elseif ($checked > 0) {
                sleep(2);
            } elseif (++$failedBatches >= 5) {
                $bar->finish();
                throw new RuntimeException('Five batches in a row failed completely (network or Google outage?). Re-run to resume.');
            } else {
                sleep(5 * $failedBatches);
            }
        }

        $bar->finish();
        $this->newLine(2);
    }

    private function recordResult(StreetSample $sample, array $result, array $lines): void
    {
        $config = config('streetle.coverage');
        $status = $result['status'];

        if ($status === 'OK') {
            $status = match (true) {
                ! str_contains((string) $result['copyright'], 'Google') => 'USER_PANO',
                (int) substr((string) $result['date'], 0, 4) < $config['min_pano_year'] => 'OLD',
                Geo::distanceToLines($result['point'], $lines) > $config['max_pano_offset_metres'] => 'OFF_STREET',
                default => self::USABLE,
            };
        }

        $sample->update([
            'status' => $status,
            'pano_id' => $result['pano_id'],
            'pano_lng' => $result['point'] ? round($result['point'][0], 6) : null,
            'pano_lat' => $result['point'] ? round($result['point'][1], 6) : null,
            'pano_date' => $result['date'] ? substr($result['date'], 0, 7) : null,
            'checked_at' => now(),
        ]);
    }

    /**
     * Coverage = usable points / all points, once every point is checked.
     * The locked pano is the newest usable one, nearest the street centre on ties.
     */
    private function score(Collection $streets): void
    {
        $streets->load('samples');

        DB::transaction(fn () => $streets->each(function (Street $street) {
            if ($street->samples->isEmpty() || $street->samples->contains(fn ($s) => $s->checked_at === null)) {
                $street->update(['coverage_score' => null, 'pano_id' => null, 'pano_date' => null, 'heading' => null]);

                return;
            }

            $usable = $street->samples->where('status', self::USABLE);
            $best = $usable
                ->sortBy(fn (StreetSample $s) => Geo::distance([$s->pano_lng, $s->pano_lat], [$street->lng, $street->lat]))
                ->sortByDesc('pano_date')
                ->first();

            $street->update([
                'coverage_score' => round($usable->count() / $street->samples->count(), 4),
                'pano_id' => $best?->pano_id,
                'pano_date' => $best?->pano_date,
                'heading' => $best ? (int) round(Geo::headingAlong([$best->pano_lng, $best->pano_lat], $street->geometry['coordinates'])) % 360 : null,
            ]);
        }));
    }

    private function report(): void
    {
        $threshold = config('streetle.coverage.answer_threshold');
        $samples = StreetSample::query();

        $this->components->twoColumnDetail('Sample points checked', (clone $samples)->whereNotNull('checked_at')->count().' / '.(clone $samples)->count());
        $this->line('  '.StreetSample::whereNotNull('status')->groupBy('status')->selectRaw('status, count(*) as n')
            ->pluck('n', 'status')->map(fn ($n, $s) => "{$s}: {$n}")->implode(', '));

        $scored = Street::whereNotNull('coverage_score');
        $this->components->twoColumnDetail('Streets scored', (clone $scored)->count().' / '.Street::count());
        $this->components->twoColumnDetail("At or above {$threshold} coverage", (clone $scored)->where('coverage_score', '>=', $threshold)->count());
        $this->components->twoColumnDetail('Scored with no usable pano', (clone $scored)->whereNull('pano_id')->count());
        $this->components->twoColumnDetail('Answer eligible (also long enough, pano not shared)', Street::answerEligible()->count());
    }
}
