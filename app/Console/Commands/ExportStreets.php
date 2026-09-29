<?php

namespace App\Console\Commands;

use App\Models\Street;
use Illuminate\Console\Command;

class ExportStreets extends Command
{
    protected $signature = 'streets:export {path=database/data/streets.json : Where to write the file}';

    protected $description = 'Export the streets table (the pipeline output) to a JSON file that StreetSeeder loads';

    public function handle(): int
    {
        $rows = Street::orderBy('id')->get()
            ->map(fn (Street $s) => json_encode(
                $s->makeHidden(['created_at', 'updated_at'])->toArray(),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));

        // One street per line, so a re-export shows up in git as a readable per-street diff.
        $path = base_path($this->argument('path'));
        @mkdir(dirname($path), recursive: true);
        file_put_contents($path, "[\n".$rows->implode(",\n")."\n]\n");

        $this->components->info("Exported {$rows->count()} streets to {$this->argument('path')} (".round(filesize($path) / 1048576, 1).' MB).');

        return self::SUCCESS;
    }
}
