<?php

namespace App\Services\Seo;

use App\Enums\SitemapChangefreq;
use App\Models\Seo\SitemapEntry;
use App\Models\Seo\SitemapIndex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SitemapService
{
    /**
     * Morph alias → sitemap index name + URL path + SEO defaults.
     * index_name must match the `name` column in sitemap_indexes (from seeder).
     */
    private const MODEL_CONFIG = [
        'product'   => [
            'index_name'  => 'products',
            'path_prefix' => '/products/',
            'changefreq'  => SitemapChangefreq::Daily,
            'priority'    => 0.8,
        ],
        'blog_post' => [
            'index_name'  => 'blog',
            'path_prefix' => '/blog/',
            'changefreq'  => SitemapChangefreq::Weekly,
            'priority'    => 0.6,
        ],
        'category'  => [
            'index_name'  => 'categories',
            'path_prefix' => '/categories/',
            'changefreq'  => SitemapChangefreq::Weekly,
            'priority'    => 0.7,
        ],
    ];

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Regenerate all active child sitemaps.
     * Called by the artisan command `php artisan sitemap:generate`.
     */
    public function generateAll(): void
    {
        SitemapIndex::where('is_active', true)->each(
            fn (SitemapIndex $index) => $this->generateChild($index)
        );
    }

    /**
     * Generate the XML file for a single child sitemap and write it to disk.
     * Updates entry_count and last_generated_at on the index row.
     *
     * Output: storage/app/public/sitemaps/{filename}
     * Public URL: /storage/sitemaps/{filename}
     */
    public function generateChild(SitemapIndex $index): void
    {
        $entries = SitemapEntry::where('sitemap_index_id', $index->id)
            ->where('is_active', true)
            ->orderBy('url')
            ->get();

        $xml = $this->buildUrlset($entries);

        Storage::disk('public')->makeDirectory('sitemaps');
        Storage::disk('public')->put('sitemaps/' . $index->filename, $xml);

        $index->update([
            'entry_count'       => $entries->count(),
            'last_generated_at' => now(),
        ]);
    }

    /**
     * Build the master sitemap index XML string.
     * Lists every active child sitemap with its URL and last-modified date.
     * Served dynamically — not written to disk.
     */
    public function generateIndex(): string
    {
        $indexes = SitemapIndex::where('is_active', true)
            ->orderBy('name')
            ->get();

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $sitemapIndex = $dom->createElementNS(
            'http://www.sitemaps.org/schemas/sitemap/0.9',
            'sitemapindex'
        );
        $dom->appendChild($sitemapIndex);

        foreach ($indexes as $index) {
            $sitemap = $dom->createElement('sitemap');
            $sitemapIndex->appendChild($sitemap);

            $sitemap->appendChild($dom->createElement('loc', htmlspecialchars((string) $index->url, ENT_XML1)));

            if ($index->last_generated_at) {
                $sitemap->appendChild(
                    $dom->createElement('lastmod', $index->last_generated_at->toDateString())
                );
            }
        }

        return $dom->saveXML();
    }

    /**
     * Upsert a single sitemap_entries row for a model.
     * Resolves the correct SitemapIndex from the model's morph alias.
     * Updates the parent index entry_count after upsert.
     */
    public function upsertEntry(Model $model, ?SitemapIndex $index = null): void
    {
        $morphAlias = $model->getMorphClass();
        $config     = self::MODEL_CONFIG[$morphAlias] ?? null;

        if ($config === null) {
            return;
        }

        $index ??= SitemapIndex::where('name', $config['index_name'])->first();

        if ($index === null) {
            return;
        }

        $slug = (string) ($model->getAttribute('slug') ?? '');

        if ($slug === '') {
            return;
        }

        $baseUrl  = rtrim((string) config('app.url'), '/');
        $url      = $baseUrl . $config['path_prefix'] . $slug;
        $isActive = (bool) ($model->getAttribute('is_active') ?? true);

        SitemapEntry::updateOrCreate(
            [
                'model_type' => $morphAlias,
                'model_id'   => $model->getKey(),
            ],
            [
                'sitemap_index_id' => $index->id,
                'url'              => $url,
                'changefreq'       => $config['changefreq'],
                'priority'         => $config['priority'],
                'last_modified'    => $model->updated_at ?? now(),
                'is_active'        => $isActive,
            ]
        );

        $this->syncEntryCount($index);

        // Regenerate the XML file on disk so the controller always serves fresh content.
        // This runs inside the SyncSitemapEntry queue job — no performance impact on web requests.
        $this->generateChild($index);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Build a <urlset> XML document from a collection of SitemapEntry rows.
     */
    private function buildUrlset(\Illuminate\Database\Eloquent\Collection $entries): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $urlset = $dom->createElementNS(
            'http://www.sitemaps.org/schemas/sitemap/0.9',
            'urlset'
        );
        $dom->appendChild($urlset);

        foreach ($entries as $entry) {
            $url = $dom->createElement('url');
            $urlset->appendChild($url);

            $url->appendChild($dom->createElement('loc', htmlspecialchars((string) $entry->url, ENT_XML1)));

            if ($entry->last_modified) {
                $url->appendChild(
                    $dom->createElement('lastmod', $entry->last_modified->toDateString())
                );
            }

            if ($entry->changefreq) {
                $url->appendChild(
                    $dom->createElement('changefreq', $entry->changefreq->value)
                );
            }

            if ($entry->priority !== null) {
                $url->appendChild(
                    $dom->createElement('priority', number_format((float) $entry->priority, 1))
                );
            }
        }

        return $dom->saveXML();
    }

    /**
     * Recalculate and persist the active entry count on a sitemap index.
     */
    private function syncEntryCount(SitemapIndex $index): void
    {
        $index->update([
            'entry_count' => SitemapEntry::where('sitemap_index_id', $index->id)
                ->where('is_active', true)
                ->count(),
        ]);
    }
}
