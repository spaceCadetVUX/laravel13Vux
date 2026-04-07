<?php

namespace App\Observers;

use App\Models\Seo\Redirect;

/**
 * Stub — filled in S34.
 * Increments cache_version on any write to bust the Redis redirect cache.
 * NEVER manually flush the Redis key — always done here automatically.
 */
class RedirectObserver
{
    public function saved(Redirect $redirect): void
    {
        // TODO S34: increment cache_version, flush Redis redirect cache key
    }

    public function deleted(Redirect $redirect): void
    {
        // TODO S34: flush Redis redirect cache key
    }
}
