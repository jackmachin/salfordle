<?php

namespace App\Providers;

use App\Game\StreetIndex;
use App\Http\Middleware\EnsurePlayerToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Loaded once per request, shared by the scorer and the game state.
        $this->app->scoped(StreetIndex::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every image fetch is billable, so cap them per player as well as globally.
        // A cookie-less client gets a fresh token each time, so those are limited by IP.
        RateLimiter::for('puzzle-image', fn (Request $request) => Limit::perDay(config('streetle.image.per_player_daily'))
            ->by($request->attributes->get('player_token_is_new', true)
                ? 'ip:'.$request->ip()
                : 'player:'.$request->attributes->get(EnsurePlayerToken::COOKIE)));
    }
}
