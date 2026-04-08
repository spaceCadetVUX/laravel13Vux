<?php

namespace App\Services\Seo;

use App\Models\Seo\Redirect;
use Illuminate\Support\Facades\Cache;

/**
 * Manages the Redis redirect lookup cache.
 *
 * The cache stores ALL active redirects as a keyed array so the middleware
 * can resolve from_path → to_path in a single cache hit instead of a DB query
 * on every request.
 *
 * Cache key: 'redirects'
 * Invalidated: on every Redirect create/update/delete via RedirectObserver.
 * Full implementation: S36 (wires the middleware + TTL config).
 */
class RedirectCacheService
{
    public const CACHE_KEY = 'redirects';

    /**
     * Rebuild the full redirect map in cache.
     * Stub — full implementation in S36.
     */
    public static function rebuild(): void
    {
        // TODO S36: load all active redirects, key by from_path, store in Redis
        // Example:
        // $map = Redirect::where('is_active', true)
        //     ->pluck('to_path', 'from_path')
        //     ->toArray();
        // Cache::put(self::CACHE_KEY, $map, now()->addHours(24));
    }

    /**
     * Flush the redirect cache so the next request rebuilds it fresh.
     */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
