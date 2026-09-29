<?php

namespace App\Http\Controllers;

use App\Game\DailyPuzzles;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proxies today's Street View image. The browser never sees the pano_id
 * (which the free Metadata API would turn into coordinates, i.e. the
 * answer) or the API key. Nothing is stored server-side.
 */
class PuzzleImageController extends Controller
{
    public function __invoke(DailyPuzzles $puzzles, int $number): Response
    {
        $config = config('streetle.image');
        $puzzle = $puzzles->today();

        // Only today's image: past ones would be billable fetches for nothing.
        abort_unless($number === $puzzle->puzzle_number, 404, 'That puzzle has ended. Reload for today\'s street.');

        $street = $puzzle->street;

        // Our own daily ceiling on billable fetches, behind the Google Cloud quota.
        $counter = 'streetle:image-fetches:'.$puzzles->todayDate()->toDateString();
        Cache::add($counter, 0, now()->addDays(2));

        if (Cache::increment($counter) > $config['daily_cap']) {
            abort(503, "Salfordle is too popular today. Come back tomorrow!");
        }

        $image = Http::timeout(15)
            ->withHeaders(array_filter(['Referer' => config('services.google_maps.referer')]))
            ->get($config['url'], [
                'size' => $config['size'],
                'pano' => $street->pano_id,
                'heading' => $street->heading ?? 0,
                'pitch' => 0,
                'fov' => $config['fov'],
                'return_error_code' => 'true',
                'key' => config('services.google_maps.key'),
            ]);

        if (! $image->successful()) {
            Log::warning('Street View image fetch failed', ['status' => $image->status(), 'street_id' => $street->id]);
            abort(502, 'The Street View image is unavailable right now.');
        }

        return response($image->body(), 200, [
            'Content-Type' => $image->header('Content-Type') ?: 'image/jpeg',
            // Honour Google's own caching headers rather than inventing our own.
            'Cache-Control' => $image->header('Cache-Control') ?: 'no-store',
        ]);
    }
}
