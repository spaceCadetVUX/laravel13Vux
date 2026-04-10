<?php

namespace App\Console\Commands;

use App\Models\Seo\SitemapIndex;
use App\Services\Seo\SitemapService;
use Illuminate\Console\Command;

class SitemapGenerateCommand extends Command
{
    protected $signature = 'sitemap:generate
                            {--index= : Specific sitemap index name to regenerate (e.g. products, blog, categories)}';

    protected $description = 'Generate sitemap XML files from active sitemap_entries';

    public function handle(SitemapService $service): int
    {
        $indexName = $this->option('index');

        if ($indexName) {
            $index = SitemapIndex::where('name', $indexName)->first();

            if ($index === null) {
                $this->error("Sitemap index '{$indexName}' not found.");

                return self::FAILURE;
            }

            $this->info("Generating sitemap: {$index->filename} ...");
            $service->generateChild($index);
            $index->refresh();
            $this->info("Done — {$index->entry_count} entries written to {$index->filename}.");

            return self::SUCCESS;
        }

        // Generate all active child sitemaps with per-index progress output.
        $indexes = SitemapIndex::where('is_active', true)->get();

        if ($indexes->isEmpty()) {
            $this->warn('No active sitemap indexes found.');

            return self::SUCCESS;
        }

        $this->info("Generating {$indexes->count()} sitemap(s)...");

        foreach ($indexes as $index) {
            $this->line("  → {$index->filename}");
            $service->generateChild($index);
            $index->refresh();
            $this->line("    {$index->entry_count} entries, last_generated: {$index->last_generated_at->toDateTimeString()}");
        }

        $this->info('All sitemaps generated successfully.');

        return self::SUCCESS;
    }
}
