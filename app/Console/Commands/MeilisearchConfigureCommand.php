<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Meilisearch\Client as MeilisearchClient;
use Throwable;

class MeilisearchConfigureCommand extends Command
{
    protected $signature = 'meilisearch:configure';

    protected $description = 'Configure Meilisearch index settings (searchable, filterable, sortable attributes)';

    public function handle(MeilisearchClient $client): int
    {
        $prefix = config('scout.prefix', '');

        $indexes = [
            $prefix . 'products'   => $this->productSettings(),
            $prefix . 'blog_posts' => $this->blogPostSettings(),
        ];

        foreach ($indexes as $indexName => $settings) {
            $this->line("Configuring index: <info>{$indexName}</info>");

            try {
                $index = $client->index($indexName);
                $index->updateSearchableAttributes($settings['searchableAttributes']);
                $index->updateFilterableAttributes($settings['filterableAttributes']);
                $index->updateSortableAttributes($settings['sortableAttributes']);
                $this->line("  <comment>✓</comment> Settings applied.");
            } catch (Throwable $e) {
                $this->error("  Failed: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        $this->info('Meilisearch indexes configured successfully.');

        return self::SUCCESS;
    }

    private function productSettings(): array
    {
        return [
            'searchableAttributes' => ['name', 'sku', 'short_description'],
            'filterableAttributes' => ['category_id', 'price', 'sale_price', 'is_active', 'stock_quantity'],
            'sortableAttributes'   => ['price', 'created_at', 'name'],
        ];
    }

    private function blogPostSettings(): array
    {
        return [
            'searchableAttributes' => ['title', 'excerpt'],
            'filterableAttributes' => ['status', 'blog_category_id'],
            'sortableAttributes'   => ['published_at'],
        ];
    }
}
