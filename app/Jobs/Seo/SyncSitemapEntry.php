<?php

namespace App\Jobs\Seo;

use App\Enums\SitemapChangefreq;
use App\Models\Seo\SitemapEntry;
use App\Models\Seo\SitemapIndex;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

class SyncSitemapEntry implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Maps morph alias → sitemap_index name + URL path prefix + SEO defaults.
     * index_name must match the `name` column in sitemap_indexes (from seeder).
     */
    private const SITEMAP_CONFIG = [
        'product'   => [
            'index_name' => 'products',
            'path_prefix' => '/products/',
            'changefreq'  => SitemapChangefreq::Daily,
            'priority'    => 0.8,
        ],
        'blog_post' => [
            'index_name' => 'blog',
            'path_prefix' => '/blog/',
            'changefreq'  => SitemapChangefreq::Weekly,
            'priority'    => 0.6,
        ],
        'category'  => [
            'index_name' => 'categories',
            'path_prefix' => '/categories/',
            'changefreq'  => SitemapChangefreq::Weekly,
            'priority'    => 0.7,
        ],
    ];

    public function __construct(
        public readonly Model $model,
    ) {}

    public function handle(): void
    {
        $morphAlias = $this->model->getMorphClass();
        $config     = self::SITEMAP_CONFIG[$morphAlias] ?? null;

        if ($config === null) {
            return;
        }

        $sitemapIndex = SitemapIndex::where('name', $config['index_name'])->first();

        if ($sitemapIndex === null) {
            return;
        }

        $slug = (string) ($this->model->getAttribute('slug') ?? '');
        $url  = rtrim((string) config('app.url'), '/') . $config['path_prefix'] . $slug;

        // Respect the model's own active flag when present.
        $isActive = (bool) ($this->model->getAttribute('is_active') ?? true);

        SitemapEntry::updateOrCreate(
            [
                'model_type' => $morphAlias,
                'model_id'   => $this->model->getKey(),
            ],
            [
                'sitemap_index_id' => $sitemapIndex->id,
                'url'              => $url,
                'changefreq'       => $config['changefreq'],
                'priority'         => $config['priority'],
                'last_modified'    => $this->model->updated_at ?? now(),
                'is_active'        => $isActive,
            ]
        );

        // Keep the parent index entry_count in sync (active entries only).
        $sitemapIndex->update([
            'entry_count' => SitemapEntry::where('sitemap_index_id', $sitemapIndex->id)
                ->where('is_active', true)
                ->count(),
        ]);
    }
}
