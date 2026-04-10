<?php

namespace App\Console\Commands;

use App\Models\Seo\LlmsDocument;
use App\Services\Seo\LlmsGeneratorService;
use Illuminate\Console\Command;

class LlmsGenerateCommand extends Command
{
    protected $signature = 'llms:generate
                            {--slug= : Specific document slug to regenerate (e.g. products, blog, root)}';

    protected $description = 'Generate llms.txt files from active llms_entries';

    public function handle(LlmsGeneratorService $service): int
    {
        $slug = $this->option('slug');

        if ($slug) {
            $document = LlmsDocument::where('slug', $slug)->first();

            if ($document === null) {
                $this->error("LLMs document with slug '{$slug}' not found.");

                return self::FAILURE;
            }

            $this->info("Generating LLMs document: {$document->slug}.txt ...");
            $service->generateDocument($document);
            $document->refresh();
            $this->info("Done — {$document->entry_count} entries written to {$document->slug}.txt.");

            return self::SUCCESS;
        }

        // Generate all active documents with per-document progress output.
        $documents = LlmsDocument::where('is_active', true)->get();

        if ($documents->isEmpty()) {
            $this->warn('No active LLMs documents found.');

            return self::SUCCESS;
        }

        $this->info("Generating {$documents->count()} LLMs document(s)...");

        foreach ($documents as $document) {
            $this->line("  → {$document->slug}.txt");
            $service->generateDocument($document);
            $document->refresh();
            $this->line("    {$document->entry_count} entries, last_generated: {$document->last_generated_at->toDateTimeString()}");
        }

        $this->info('All LLMs documents generated successfully.');

        return self::SUCCESS;
    }
}
