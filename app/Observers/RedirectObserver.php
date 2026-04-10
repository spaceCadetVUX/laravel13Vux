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
 * The new version key is then written to Redis by invalidate() → rebuild().
 */
class RedirectObserver
{
    public function __construct(
        private readonly RedirectCacheService $cache,
    ) {}

    public function saved(Redirect $redirect): void
    {
        // Increment version via query builder — does NOT re-fire Eloquent model
        // events, so there is no observer recursion.
        $redirect->increment('cache_version');

        // Rebuild under the new version key. Old key expires after TTL.
        $this->cache->invalidate();
    }

    public function deleted(Redirect $redirect): void
    {
        // No version bump on delete — record is gone, just refresh the cache.
        $this->cache->invalidate();
    }
}
