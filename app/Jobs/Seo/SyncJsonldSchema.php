<?php

namespace App\Jobs\Seo;

use App\Services\Seo\JsonldService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

class SyncJsonldSchema implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public readonly Model $model,
    ) {}

    /**
     * Delegate all sync logic to JsonldService.
     * The service is the single source of truth for JSON-LD management.
     */
    public function handle(JsonldService $service): void
    {
        $service->syncForModel($this->model);
    }
}
