<?php

namespace App\Jobs\Seo;

use App\Services\Seo\SitemapService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

class SyncSitemapEntry implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly Model $model,
    ) {}

    /**
     * Delegate all upsert logic to SitemapService.
     * The service is the single source of truth for sitemap management.
     */
    public function handle(SitemapService $service): void
    {
        $service->upsertEntry($this->model);
    }
}
