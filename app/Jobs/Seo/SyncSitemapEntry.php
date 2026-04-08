<?php

namespace App\Jobs\Seo;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Stub — full implementation in S36.
 * Upserts a sitemap_entries row for the given model.
 */
class SyncSitemapEntry implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Model $model,
    ) {}

    public function handle(): void
    {
        // TODO S36: resolve sitemap_index for model_type, build URL,
        //           upsert SitemapEntry, update entry_count on parent index
    }
}
