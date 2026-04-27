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
        public readonly string $locale = 'vi',
    ) {}

    public function handle(SitemapService $service): void
    {
        $service->upsertEntry($this->model, null, $this->locale);
    }
}
