<?php

namespace App\Game;

use App\Models\DailyAnswer;
use App\Models\Game;
use App\Models\Guess;

/**
 * What the client may see of a game. The answer is only included once the
 * game is over; until then, nothing here identifies it beyond the clues.
 */
class GameState
{
    public function __construct(private StreetIndex $streets, private Scorer $scorer) {}

    public function present(DailyAnswer $puzzle, ?Game $game): array
    {
        $guesses = $game?->guesses()->with('street')->get() ?? collect();
        $status = match (true) {
            $game?->solved_at !== null => 'won',
            $guesses->count() >= Scorer::MAX_GUESSES => 'lost',
            default => 'playing',
        };

        $answer = $puzzle->street;
        $pool = $this->pool($puzzle, $game);

        return [
            'puzzle' => [
                'number' => $puzzle->puzzle_number,
                'date' => $puzzle->date->toDateString(),
                // Browsers cache the image, so the URL changes with the puzzle (and again if its
                // street is swapped), or they'd keep showing the old street.
                'image_url' => "/api/puzzles/{$puzzle->puzzle_number}/image?v={$puzzle->updated_at->timestamp}", // relative: no http/https mix-ups behind the host's proxy
            ],
            'max_guesses' => Scorer::MAX_GUESSES,
            'total_streets' => count($this->streets->all()),
            'remaining' => count($pool),
            // Streets still consistent with every clue, so the guess box can dim the rest. This
            // tells the player nothing the clues don't: it's the clues, worked through.
            'candidates' => array_keys($pool),
            // Postcode districts still in play, with how many candidates each: "M5" => 12.
            'remaining_postcode_districts' => $this->districtCounts($pool),
            'status' => $status,
            'guesses' => $guesses->map(fn (Guess $g) => [
                'number' => $g->guess_number,
                'street' => [
                    'id' => $g->street->id,
                    'name' => $g->street->display_name,
                    'ward' => $g->street->ward,
                    'postcode_district' => $g->street->postcode_district,
                    'street_type' => $g->street->street_type,
                    'first_letter' => $g->street->first_letter,
                ],
                'correct' => $g->street_id === $puzzle->street_id,
                'matches' => [
                    'ward' => $g->ward_match,
                    'postcode_district' => $g->postcode_match,
                    'street_type' => $g->street_type_match,
                    'first_letter' => $g->first_letter_match,
                ],
                // Where the answer's first letter sits relative to this guess's.
                'first_letter_hint' => $g->first_letter_match ? null
                    : ($answer->first_letter > $g->street->first_letter ? 'after' : 'before'),
                'distance_miles' => $g->distance_miles,
                'direction' => $g->direction,
            ])->values()->all(),
            'answer' => $status === 'playing' ? null : [
                'id' => $answer->id,
                'name' => $answer->display_name,
                'ward' => $answer->ward,
                'postcode_district' => $answer->postcode_district,
                'street_type' => $answer->street_type,
                'sub_area' => $answer->sub_area,
            ],
        ];
    }

    /**
     * @param  array<int, StreetFacts>  $pool
     * @return array<string, int> in natural order (M3, M5, ... M44, M50)
     */
    private function districtCounts(array $pool): array
    {
        $counts = array_count_values(array_filter(array_map(fn (StreetFacts $s) => $s->postcodeDistrict, $pool)));
        uksort($counts, 'strnatcmp');

        return $counts;
    }

    /**
     * Candidates still consistent with every guess, replayed from the start
     * so it's always derived from stored guesses, never stored itself.
     *
     * @return array<int, StreetFacts>
     */
    public function pool(DailyAnswer $puzzle, ?Game $game): array
    {
        $pool = $this->streets->all();
        $answer = $this->streets->get($puzzle->street_id);

        foreach ($game?->guesses ?? [] as $guess) {
            $pool = $this->scorer->applyGuess($pool, $this->streets->get($guess->street_id), $answer)->pool;
        }

        return $pool;
    }
}
