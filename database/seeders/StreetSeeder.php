<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Loads the streets produced by the data pipeline (exported with streets:export),
 * keeping their ids so existing puzzles and guesses still point at the right streets.
 * Safe to re-run: rows are upserted by id.
 */
class StreetSeeder extends Seeder
{
    private const FILE = 'database/data/streets.json';

    private const JSON_COLUMNS = ['geometry', 'osm_way_ids', 'postcodes'];

    public function run(): void
    {
        $path = base_path(self::FILE);

        if (! is_file($path)) {
            throw new RuntimeException(self::FILE.' is missing. Run streets:export where the pipeline data lives.');
        }

        $now = now();
        $rows = array_map(function (array $street) use ($now) {
            foreach (self::JSON_COLUMNS as $column) {
                $street[$column] = isset($street[$column]) ? json_encode($street[$column]) : null;
            }

            return $street + ['created_at' => $now, 'updated_at' => $now];
        }, json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR));

        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('streets')->upsert($chunk, ['id']);
            }
        });

        $this->command?->info('Seeded '.count($rows).' streets.');
    }
}
