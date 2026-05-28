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
        $documents = LlmsDocument::where('is_active', true)
            ->where(fn ($q) => $q->where('slug', 'business')
                ->orWhere('slug', 'like', 'business-%'))
            ->get();

        foreach ($documents as $document) {
            $service->generateDocument($document);
        }
    }
}
