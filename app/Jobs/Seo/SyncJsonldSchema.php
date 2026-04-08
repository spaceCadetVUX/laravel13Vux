<?php

namespace App\Jobs\Seo;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Stub — full implementation in S35.
 * Syncs/creates a JSON-LD schema record for the given model.
 */
class SyncJsonldSchema implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Model $model,
    ) {}

    public function handle(): void
    {
        // TODO S35: resolve template by schema_type, fill placeholders,
        //           upsert JsonldSchema record for $this->model
    }
}
