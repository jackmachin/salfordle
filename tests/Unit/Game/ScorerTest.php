<?php

namespace Tests\Unit\Game;

use App\Game\Scorer;
use App\Game\StreetFacts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScorerTest extends TestCase
{
    private function street(int $id, string $ward, string $postcode, string $type, string $letter, float $lat = 53.48, float $lng = -2.29): StreetFacts
    {
        return new StreetFacts($id, $ward, $postcode, $type, $letter, $lat, $lng);
    }

    /** @return array<int, StreetFacts> */
    private function pool(StreetFacts ...$streets): array
    {
        return array_column(array_map(fn ($s) => [$s->id, $s], $streets), 1, 0);
    }

    public function test_a_correct_guess_matches_everything_and_leaves_only_the_answer(): void
    {
        $answer = $this->street(1, 'Eccles', 'M30', 'Road', 'C');
        $other = $this->street(2, 'Eccles', 'M30', 'Road', 'C');

        $result = (new Scorer)->applyGuess($this->pool($answer, $other), $answer, $answer);

        $this->assertTrue($result->correct);
        $this->assertTrue($result->wardMatch && $result->postcodeMatch && $result->streetTypeMatch && $result->firstLetterMatch);
        $this->assertSame(0.0, $result->distanceMiles);
        $this->assertNull($result->direction);
        $this->assertSame([1], array_keys($result->pool));
    }

    public function test_a_miss_removes_every_street_sharing_the_guessed_value(): void
    {
        $answer = $this->street(1, 'Eccles', 'M30', 'Road', 'C');
        $guess = $this->street(2, 'Ordsall', 'M30', 'Road', 'C');
        $sameWardAsGuess = $this->street(3, 'Ordsall', 'M30', 'Road', 'C');
        $otherWard = $this->street(4, 'Claremont', 'M30', 'Road', 'C');

        $result = (new Scorer)->applyGuess($this->pool($answer, $guess, $sameWardAsGuess, $otherWard), $guess, $answer);

        $this->assertFalse($result->wardMatch);
        $this->assertEqualsCanonicalizing([1, 4], array_keys($result->pool));
    }

    public function test_a_match_keeps_only_streets_sharing_the_guessed_value(): void
    {
        $answer = $this->street(1, 'Eccles', 'M30', 'Road', 'C');
        $guess = $this->street(2, 'Eccles', 'M5', 'Street', 'B');
        $sameWard = $this->street(3, 'Eccles', 'M6', 'Close', 'D');
        $otherWard = $this->street(4, 'Ordsall', 'M6', 'Close', 'D');

        $result = (new Scorer)->applyGuess($this->pool($answer, $guess, $sameWard, $otherWard), $guess, $answer);

        $this->assertTrue($result->wardMatch);
        $this->assertFalse($result->postcodeMatch);
        $this->assertEqualsCanonicalizing([1, 3], array_keys($result->pool));
    }

    public function test_the_answer_always_survives_a_wrong_guess(): void
    {
        $answer = $this->street(1, 'Eccles', 'M30', 'Road', 'C');
        $candidates = [$answer];

        foreach (['Eccles', 'Ordsall'] as $w) {
            foreach (['M30', 'M5'] as $p) {
                foreach (['Road', 'Close'] as $t) {
                    foreach (['C', 'B'] as $l) {
                        $candidates[] = $this->street(count($candidates) + 1, $w, $p, $t, $l);
                    }
                }
            }
        }

        $scorer = new Scorer;

        foreach (array_slice($candidates, 1) as $guess) {
            $this->assertArrayHasKey(1, $scorer->applyGuess($this->pool(...$candidates), $guess, $answer)->pool);
        }
    }

    public function test_pools_narrow_across_successive_guesses(): void
    {
        $answer = $this->street(1, 'Eccles', 'M30', 'Road', 'C');
        $pool = $this->pool(
            $answer,
            $g1 = $this->street(2, 'Ordsall', 'M5', 'Street', 'A'),
            $this->street(3, 'Eccles', 'M30', 'Road', 'D'),
            $g2 = $this->street(4, 'Eccles', 'M30', 'Close', 'C'),
            $this->street(5, 'Eccles', 'M30', 'Road', 'C'),
        );

        $scorer = new Scorer;
        $pool = $scorer->applyGuess($pool, $g1, $answer)->pool;
        $this->assertEqualsCanonicalizing([1, 3, 4, 5], array_keys($pool));

        $pool = $scorer->applyGuess($pool, $g2, $answer)->pool;
        $this->assertEqualsCanonicalizing([1, 5], array_keys($pool));
    }

    public function test_distance_and_direction(): void
    {
        // ~1.3 miles north-east (about 45 degrees).
        $answer = $this->street(1, 'A', 'M1', 'Road', 'A', 53.4966, -2.2600);
        $guess = $this->street(2, 'B', 'M2', 'Road', 'B', 53.4830, -2.2830);

        $result = (new Scorer)->applyGuess([], $guess, $answer);

        $this->assertSame('NE', $result->direction);
        $this->assertEqualsWithDelta(1.33, $result->distanceMiles, 0.05);
    }

    public function test_a_letter_miss_keeps_only_letters_on_the_answers_side(): void
    {
        $answer = $this->street(1, 'Eccles', 'M30', 'Road', 'P');
        $guess = $this->street(2, 'Eccles', 'M30', 'Road', 'H');

        $pool = $this->pool(
            $answer,
            $guess,
            $this->street(3, 'Eccles', 'M30', 'Road', 'A'), // before H: out
            $this->street(4, 'Eccles', 'M30', 'Road', 'H'), // same as the wrong letter: out
            $this->street(5, 'Eccles', 'M30', 'Road', 'Z'), // after H: in
        );

        $result = (new Scorer)->applyGuess($pool, $guess, $answer);

        $this->assertFalse($result->firstLetterMatch);
        $this->assertSame('after', $result->letterHint);
        $this->assertEqualsCanonicalizing([1, 5], array_keys($result->pool));

        $this->assertSame('before', (new Scorer)->applyGuess([], $answer, $guess)->letterHint);
        $this->assertNull((new Scorer)->applyGuess([], $answer, $answer)->letterHint);
    }

    public function test_direction_keeps_only_streets_in_a_90_degree_wedge_from_the_guess(): void
    {
        // Guess at the origin; answer due north-east. Candidates placed ~1 km away at various bearings.
        $guess = $this->street(1, 'A', 'M1', 'Road', 'A', 53.4800, -2.3000);
        $at = fn (int $id, float $bearing) => $this->street(
            $id, 'A', 'M1', 'Road', 'A',
            53.4800 + 0.009 * cos(deg2rad($bearing)),
            -2.3000 + 0.009 * sin(deg2rad($bearing)) / cos(deg2rad(53.48)),
        );

        $answer = $at(2, 45);
        $pool = $this->pool($guess, $answer, $at(3, 10), $at(4, 80), $at(5, 100), $at(6, 225), $at(7, 350));

        $result = (new Scorer)->applyGuess($pool, $guess, $answer);

        $this->assertSame('NE', $result->direction);
        // Within 45 degrees of north-east (0-90): 10 and 80 stay; 100, 225 and 350 go.
        $this->assertEqualsCanonicalizing([2, 3, 4], array_keys($result->pool));
    }

    public function test_the_answer_survives_the_wedge_whatever_its_bearing(): void
    {
        $guess = $this->street(1, 'A', 'M1', 'Road', 'A', 53.48, -2.30);

        for ($bearing = 0; $bearing < 360; $bearing += 7) {
            $answer = $this->street(2, 'A', 'M1', 'Road', 'A',
                53.48 + 0.01 * cos(deg2rad($bearing)), -2.30 + 0.01 * sin(deg2rad($bearing)) / cos(deg2rad(53.48)));

            $this->assertArrayHasKey(2, (new Scorer)->applyGuess($this->pool($guess, $answer), $guess, $answer)->pool, "bearing {$bearing}");
        }
    }

    public static function bearings(): array
    {
        return [[0, 'N'], [22, 'N'], [23, 'NE'], [90, 'E'], [180, 'S'], [247, 'SW'], [248, 'W'], [300, 'NW'], [338, 'N'], [359.9, 'N']];
    }

    #[DataProvider('bearings')]
    public function test_compass(float $bearing, string $direction): void
    {
        $this->assertSame($direction, Scorer::compass($bearing));
    }
}
