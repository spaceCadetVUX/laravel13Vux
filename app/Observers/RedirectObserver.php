<?php

namespace App\Observers;

use App\Models\Seo\Redirect;
use App\Services\Seo\RedirectCacheService;

/**
 * Keeps the redirect cache in sync with the DB on every write.
 * NEVER manually flush the Redis key — always goes through this observer.
 *
 * cache_version is incremented so any in-flight response that already
 * fetched the old version will gracefully miss on the next request.
 */
class RedirectObserver
{
    public function saved(Redirect $redirect): void
    {
        // Increment version in DB — uses query builder directly,
        // so it does NOT re-fire Eloquent model events (no recursion).
        $redirect->increment('cache_version');

        // Flush the cached redirect map so it's rebuilt on next request.
        // Full rebuild (S36) will also be triggered here once wired.
        RedirectCacheService::flush();
        RedirectCacheService::rebuild();
    }

    public function deleted(Redirect $redirect): void
    {
        // No version bump needed on delete — just bust the cache.
        RedirectCacheService::flush();
        RedirectCacheService::rebuild();
    }
}
