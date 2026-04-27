<?php

namespace App\Services\Seo;

use App\Jobs\Seo\IncrementRedirectHits;
use App\Models\Seo\Redirect;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Redis-backed redirect lookup cache.
 *
 * Key pattern: redirects:v{max_cache_version}
 * TTL: 3600 seconds (60 min)
 *
 * Versioned keys mean we never need an explicit delete:
 * - RedirectObserver increments cache_version on every write
 * - Next getAll() call computes the new version, misses the old key, rebuilds
 * - Old keys expire naturally after TTL
 *
 * Falls back to a direct DB query when Redis is unavailable (local dev).
 */
class RedirectCacheService
{
    private const TTL = 3600;

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Return all active redirects, from Redis cache when possible.
     *
     * Flow:
     *   1. Get max(cache_version) from DB — cheap indexed aggregate
     *   2. Check Redis for redirects:v{version}
     *   3. Cache hit  → return deserialized Collection
     *   4. Cache miss → load from DB, write to Redis, return Collection
     */
    public function getAll(): Collection
    {
        $version = $this->currentVersion();
        $key     = $this->cacheKey($version);

        try {
            $cached = Cache::store('redis')->get($key);

            if ($cached !== null) {
                // Cache hit — deserialize and return
                return $cached instanceof Collection
                    ? $cached
                    : new Collection($cached);
            }
        } catch (\Throwable) {
            // Redis unavailable (e.g. local dev) — fall through to DB
        }

        $redirects = $this->loadFromDb();
        $this->storeInRedis($key, $redirects);

        return $redirects;
    }

    /**
     * Reload all active redirects from DB and write to Redis under the
     * current version key. Old version keys expire naturally after TTL.
     */
    public function rebuild(): void
    {
        $redirects = $this->loadFromDb();
        $key       = $this->cacheKey($this->currentVersion());

        $this->storeInRedis($key, $redirects);
    }

    /**
     * Resolve a from_path to its Redirect model.
     * When $locale is provided, only matches rows where locale is null or equals $locale.
     * Increments the hits counter asynchronously so resolution has no
     * write-latency overhead on the redirect response.
     *
     * Returns null when no active redirect matches the path.
     */
    public function resolve(string $fromPath, ?string $locale = null): ?Redirect
    {
        /** @var Redirect|null $redirect */
        $redirect = $this->getAll()->first(function (Redirect $r) use ($fromPath, $locale): bool {
            if ($r->from_path !== $fromPath) {
                return false;
            }

            return $locale === null || $r->locale === null || $r->locale === $locale;
        });

        if ($redirect !== null) {
            dispatch(new IncrementRedirectHits($redirect->id));
        }

        return $redirect;
    }

    /**
     * Invalidate the cache by rebuilding immediately.
     * Called by RedirectObserver on every create / update / delete.
     */
    public function invalidate(): void
    {
        $this->rebuild();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /** Redis key for a specific cache version. */
    private function cacheKey(int $version): string
    {
        return "redirects:v{$version}";
    }

    /**
     * Get the highest cache_version stored across all redirect rows.
     * Returns 0 when the table is empty so the key is always well-formed.
     */
    private function currentVersion(): int
    {
        return (int) Redirect::max('cache_version');
    }

    /** Load all active redirects from DB as an Eloquent Collection. */
    private function loadFromDb(): Collection
    {
        return Redirect::where('is_active', true)
            ->orderBy('from_path')
            ->get();
    }

    /**
     * Persist a Collection to Redis.
     * Silently no-ops when Redis is unavailable — callers fall back to DB.
     */
    private function storeInRedis(string $key, Collection $redirects): void
    {
        try {
            Cache::store('redis')->put($key, $redirects, self::TTL);
        } catch (\Throwable) {
            // Redis unavailable — cache miss on every request until Redis comes back.
        }
    }
}
