<?php

namespace App\Http\Controllers;

use App\Game\DailyPuzzles;
use App\Game\GameState;
use App\Game\Scorer;
use App\Game\StreetIndex;
use App\Http\Middleware\EnsurePlayerToken;
use App\Models\Game;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GuessController extends Controller
{
    public function __invoke(Request $request, DailyPuzzles $puzzles, StreetIndex $streets, Scorer $scorer, GameState $state): JsonResponse
    {
        $data = $request->validate([
            'street_id' => ['required', 'integer', Rule::exists('streets', 'id')],
            // The puzzle number the client is showing, so a guess made across midnight isn't applied to the new puzzle.
            'puzzle' => ['nullable', 'integer'],
        ]);

        $puzzle = $puzzles->today();

        if (isset($data['puzzle']) && $data['puzzle'] !== $puzzle->puzzle_number) {
            abort(409, 'A new puzzle has started. Reload to play it.');
        }

        $game = Game::createOrFirst([
            'player_token' => EnsurePlayerToken::token($request),
            'daily_answer_id' => $puzzle->id,
        ])->load('guesses');

        if ($game->solved_at !== null || $game->guesses->count() >= Scorer::MAX_GUESSES) {
            abort(409, 'This game is already over.');
        }

        if ($game->guesses->contains('street_id', $data['street_id'])) {
            throw ValidationException::withMessages(['street_id' => 'You have already guessed that street.']);
        }

        $result = $scorer->applyGuess(
            $state->pool($puzzle, $game),
            $streets->get($data['street_id']),
            $streets->get($puzzle->street_id),
        );

        try {
            DB::transaction(function () use ($game, $data, $result) {
                $game->guesses()->create([
                    'street_id' => $data['street_id'],
                    'guess_number' => $game->guesses->count() + 1,
                    'distance_miles' => $result->distanceMiles,
                    'direction' => $result->direction,
                    'ward_match' => $result->wardMatch,
                    'postcode_match' => $result->postcodeMatch,
                    'street_type_match' => $result->streetTypeMatch,
                    'first_letter_match' => $result->firstLetterMatch,
                ]);

                $game->update([
                    'guess_count' => $game->guesses->count() + 1,
                    'solved_at' => $result->correct ? now() : null,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'That guess was already submitted.'); // double-submit racing itself
        }

        return response()->json($state->present($puzzle, $game->fresh()));
    }
}
