<?php

namespace App\Http\Controllers;

use App\Game\DailyPuzzles;
use App\Game\GameState;
use App\Http\Middleware\EnsurePlayerToken;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PuzzleController extends Controller
{
    /** Today's puzzle and this player's progress on it. */
    public function __invoke(Request $request, DailyPuzzles $puzzles, GameState $state): JsonResponse
    {
        $puzzle = $puzzles->today();

        $game = Game::where('player_token', EnsurePlayerToken::token($request))
            ->where('daily_answer_id', $puzzle->id)
            ->first();

        return response()->json($state->present($puzzle, $game));
    }
}
