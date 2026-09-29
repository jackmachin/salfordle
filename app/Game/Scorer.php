<?php

namespace App\Game;

use App\Streets\Geo;

/**
 * The game's rules, as a pure function of (pool, guess, answer).
 *
 * Every clue narrows the pool:
 *  - ward, postcode district, street type: a match keeps only streets sharing the
 *    guess's value, a miss removes every street sharing it;
 *  - first letter: a match keeps that letter, a miss keeps only letters on the
 *    answer's side of the guess's (before or after it in the alphabet);
 *  - direction: keeps only streets within 45 degrees either side of the compass
 *    point shown, as seen from the guess.
 * Distance is informational only and never filters.
 */
final class Scorer
{
    public const MAX_GUESSES = 6;

    private const METRES_PER_MILE = 1609.344;

    private const DIRECTIONS = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];

    /** Half-width of the direction wedge. Wider than a compass point (22.5) so it's forgiving. */
    private const WEDGE_HALF_ANGLE = 45;

    /** Categorical clues: result flag => StreetFacts property. */
    private const CLUES = [
        'ward' => 'ward',
        'postcode' => 'postcodeDistrict',
        'streetType' => 'streetType',
    ];

    /**
     * @param  array<int, StreetFacts>  $pool  keyed by street id
     */
    public function applyGuess(array $pool, StreetFacts $guess, StreetFacts $answer): GuessResult
    {
        $correct = $guess->id === $answer->id;
        $matches = [];

        foreach (self::CLUES as $clue => $property) {
            $matches[$clue] = $guess->{$property} === $answer->{$property};
        }

        $letterMatch = $guess->firstLetter === $answer->firstLetter;
        $letterHint = $letterMatch ? null : ($answer->firstLetter > $guess->firstLetter ? 'after' : 'before');

        $from = [$guess->lng, $guess->lat];
        $to = [$answer->lng, $answer->lat];
        $direction = $correct ? null : self::compass(Geo::bearing($from, $to));

        $pool = $correct ? [$answer->id => $answer] : array_filter(
            $pool,
            function (StreetFacts $candidate) use ($guess, $matches, $letterHint, $direction, $from) {
                if ($candidate->id === $guess->id) {
                    return false;
                }

                foreach (self::CLUES as $clue => $property) {
                    if (($candidate->{$property} === $guess->{$property}) !== $matches[$clue]) {
                        return false;
                    }
                }

                $letterOk = match ($letterHint) {
                    null => $candidate->firstLetter === $guess->firstLetter,
                    'after' => $candidate->firstLetter > $guess->firstLetter,
                    'before' => $candidate->firstLetter < $guess->firstLetter,
                };

                return $letterOk && self::inWedge(Geo::bearing($from, [$candidate->lng, $candidate->lat]), $direction);
            },
        );

        return new GuessResult(
            correct: $correct,
            wardMatch: $matches['ward'],
            postcodeMatch: $matches['postcode'],
            streetTypeMatch: $matches['streetType'],
            firstLetterMatch: $letterMatch,
            letterHint: $letterHint,
            distanceMiles: round(Geo::distance($from, $to) / self::METRES_PER_MILE, 2),
            direction: $direction,
            pool: $pool,
        );
    }

    /** 8-point compass direction for a bearing in degrees. */
    public static function compass(float $bearing): string
    {
        return self::DIRECTIONS[(int) round(fmod($bearing + 360, 360) / 45) % 8];
    }

    /** Whether a bearing lies within the wedge around a compass direction. The answer's always does. */
    public static function inWedge(float $bearing, string $direction): bool
    {
        $centre = array_search($direction, self::DIRECTIONS, true) * 45;
        $offset = abs(fmod($bearing - $centre + 540, 360) - 180);

        return $offset <= self::WEDGE_HALF_ANGLE;
    }
}
