<?php

namespace App\Game;

use App\Models\DailyAnswer;
use App\Models\Game;
use App\Models\Street;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DailyPuzzles
{
    public function today(): DailyAnswer
    {
        return $this->forDate($this->todayDate());
    }

    public function todayDate(): CarbonImmutable
    {
        return CarbonImmutable::now(config('streetle.game.timezone'))->startOfDay();
    }

    /** The puzzle for a date, picking an answer if none is scheduled yet. */
    public function forDate(CarbonImmutable $date): DailyAnswer
    {
        $existing = DailyAnswer::whereDate('date', $date->toDateString())->first();

        if ($existing) {
            return $existing;
        }

        try {
            return DailyAnswer::create([
                'date' => $date->toDateString(),
                'puzzle_number' => $this->number($date),
                'street_id' => $this->pickStreet()->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Another request created it first.
            return DailyAnswer::whereDate('date', $date->toDateString())->firstOrFail();
        }
    }

    /**
     * Swap a puzzle's answer for a different street, keeping its date and number.
     * Games already played on it are deleted: their guesses were scored against the old answer.
     *
     * @return int games deleted
     */
    public function reroll(DailyAnswer $puzzle): int
    {
        return DB::transaction(function () use ($puzzle) {
            $games = $puzzle->games()->count();
            $puzzle->games()->each(fn (Game $game) => $game->delete()); // guesses cascade

            $puzzle->update(['street_id' => $this->pickStreet(except: $puzzle->street_id)->id]);

            return $games;
        });
    }

    public function number(CarbonImmutable $date): int
    {
        $first = CarbonImmutable::parse(config('streetle.game.first_puzzle_date'), config('streetle.game.timezone'));

        return (int) $first->startOfDay()->diffInDays($date->startOfDay()) + 1;
    }

    /** A random eligible street that has never been an answer. */
    private function pickStreet(?int $except = null): Street
    {
        return Street::answerEligible()
            ->when(config('streetle.game.require_review'), fn ($q) => $q->active())
            ->whereNotIn('id', DailyAnswer::select('street_id'))
            ->when($except, fn ($q) => $q->whereKeyNot($except))
            ->inRandomOrder()
            ->first()
            ?? throw new RuntimeException('No eligible streets left to use as an answer.');
    }
}
