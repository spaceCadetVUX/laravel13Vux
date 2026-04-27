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

        // Generate per-locale combined llms.txt files (Redis cache).
        $this->info('Generating locale-combined llms.txt files...');

        foreach (config('app.supported_locales') as $locale) {
            $service->regenerate($locale);
            $this->info("Generated llms.txt for [{$locale}]");
        }

        // Also regenerate per-document disk files for scoped routes.
        $documents = LlmsDocument::where('is_active', true)->get();

        if ($documents->isNotEmpty()) {
            $this->info("Regenerating {$documents->count()} scoped document(s)...");

            foreach ($documents as $document) {
                $this->line("  → {$document->slug}.txt");
                $service->generateDocument($document);
                $document->refresh();
                $this->line("    {$document->entry_count} entries, last_generated: {$document->last_generated_at->toDateTimeString()}");
            }
        }

        $this->info('All LLMs documents generated successfully.');

        return self::SUCCESS;
    }
}
