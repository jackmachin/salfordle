<?php

namespace App\Console\Commands;

use App\Game\DailyPuzzles;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RerollPuzzle extends Command
{
    protected $signature = 'puzzles:reroll
        {--date= : Puzzle date (YYYY-MM-DD). Defaults to today}
        {--force : Skip the confirmation, even when players have games on it}';

    protected $description = "Swap a day's answer for a different street (e.g. a street sign is in shot), deleting games already played on it";

    public function handle(DailyPuzzles $puzzles): int
    {
        $date = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'), config('streetle.game.timezone'))->startOfDay()
            : $puzzles->todayDate();

        $puzzle = $puzzles->forDate($date);
        $games = $puzzle->games()->count();

        if ($games && ! $this->option('force')
            && ! $this->confirm("Puzzle #{$puzzle->puzzle_number} has {$games} games in progress or finished. Delete them and swap the street?")) {
            return self::FAILURE;
        }

        $deleted = $puzzles->reroll($puzzle);

        // Deliberately not printing the street: whoever runs this may want to play it.
        $this->components->info("Puzzle #{$puzzle->puzzle_number} ({$date->toDateString()}) has a new street. {$deleted} games deleted.");

        return self::SUCCESS;
    }
}
