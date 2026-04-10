<?php

namespace App\Jobs\Seo;

use App\Services\Seo\LlmsGeneratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

class SyncLlmsEntry implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly Model $model,
    ) {}

    /**
     * Delegate all upsert logic to LlmsGeneratorService.
     * The service is the single source of truth for LLMs entry management.
     */
    public function handle(LlmsGeneratorService $service): void
    {
        $service->upsertEntry($this->model);
    }
}
