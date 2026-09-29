<?php

namespace App\Streets\Sources;

use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

abstract class CachedSource
{
    /** Raw downloads are kept so re-runs are reproducible and don't hammer public APIs. */
    protected function cached(string $file, bool $refresh, Closure $fetch): array
    {
        $disk = Storage::disk('local');
        $path = "streetle/{$file}";

        if (! $refresh && $disk->exists($path)) {
            return json_decode($disk->get($path), true, flags: JSON_THROW_ON_ERROR);
        }

        $data = $fetch();
        $disk->put($path, json_encode($data, JSON_THROW_ON_ERROR));

        return $data;
    }

    protected function http(): PendingRequest
    {
        return Http::withUserAgent(config('streetle.user_agent'))
            ->acceptJson()
            ->timeout(300)
            ->retry(3, 5000, throw: false);
    }
}
