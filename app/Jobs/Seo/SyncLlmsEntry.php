<?php

namespace App\Jobs\Seo;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Stub — full implementation in S37.
 * Upserts an llms_entries row for the given model.
 */
class SyncLlmsEntry implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Model $model,
    ) {}

    public function handle(): void
    {
        // TODO S37: resolve LlmsDocument for model_type, build entry content,
        //           upsert LlmsEntry, update entry_count on parent document
    }
}
