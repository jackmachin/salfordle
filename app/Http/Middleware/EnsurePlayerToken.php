<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anonymous players are identified by a long-lived UUID cookie, so games and
 * streaks work without an account. Accounts can later claim a token's games.
 */
class EnsurePlayerToken
{
    public const COOKIE = 'player_token';

    private const LIFETIME_MINUTES = 60 * 24 * 365 * 5;

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie(self::COOKIE);
        $isNew = ! is_string($token) || ! Str::isUuid($token);

        if ($isNew) {
            $token = (string) Str::uuid();
        }

        $request->attributes->set(self::COOKIE, $token);
        $request->attributes->set('player_token_is_new', $isNew);

        $response = $next($request);

        if ($isNew) {
            $response->headers->setCookie(cookie(self::COOKIE, $token, self::LIFETIME_MINUTES));
        }

        return $response;
    }

    public static function token(Request $request): string
    {
        return $request->attributes->get(self::COOKIE);
    }
}
