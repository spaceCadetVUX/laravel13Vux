<?php

namespace App\Jobs\Seo;

use App\Models\Seo\Redirect;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Increments the hits counter on a Redirect row in the background.
 * Dispatched by RedirectCacheService::resolve() so the counter doesn't
 * add latency to the HTTP redirect response.
 */
class IncrementRedirectHits implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $redirectId,
    ) {}

    public function handle(): void
    {
        // increment() uses a direct query — does not fire Eloquent model events,
        // so it will NOT re-trigger RedirectObserver (no cache invalidation loop).
        Redirect::where('id', $this->redirectId)->increment('hits');
    }
}
