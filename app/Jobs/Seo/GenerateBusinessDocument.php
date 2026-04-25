<?php

namespace App\Jobs\Seo;

use App\Models\Seo\LlmsDocument;
use App\Services\Seo\LlmsGeneratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateBusinessDocument implements ShouldQueue
{
    use Queueable;

    public function handle(LlmsGeneratorService $service): void
    {
        $document = LlmsDocument::where('slug', 'business')->where('is_active', true)->first();

        if ($document === null) {
            return;
        }

        $service->generateDocument($document);
    }
}
