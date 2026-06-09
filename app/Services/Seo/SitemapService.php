<?php

namespace App\Services\Seo;

use App\Enums\SitemapChangefreq;
use App\Models\Seo\SitemapEntry;
use App\Models\Seo\SitemapIndex;
use App\Support\LocaleUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SitemapService
{
    /**
     * Morph alias → SEO defaults for sitemap entries.
     * URL generation uses LocaleUrl::for() — not route() — so paths match config/localeurl.php.
     */
    private const MODEL_CONFIG = [
        'product'       => ['changefreq' => SitemapChangefreq::Daily,  'priority' => 0.8],
        'blog_post'     => ['changefreq' => SitemapChangefreq::Weekly, 'priority' => 0.6],
        'category'      => ['changefreq' => SitemapChangefreq::Weekly, 'priority' => 0.7],
        'blog_category' => ['changefreq' => SitemapChangefreq::Weekly, 'priority' => 0.5],
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
     * Upsert a single sitemap_entries row for a model + locale.
     * Finds the correct SitemapIndex by model class + locale.
     * Skips if no translation exists for the given locale.
     * Builds alternate_urls jsonb for hreflang xlinks.
     */
    public function upsertEntry(Model $model, ?SitemapIndex $index = null, string $locale = 'vi'): void
    {
        $morphAlias = $model->getMorphClass();
        $config     = self::MODEL_CONFIG[$morphAlias] ?? null;

        if ($config === null) {
            return;
        }

        $index ??= SitemapIndex::where('model_type', get_class($model))
            ->where('locale', $locale)
            ->first();

        if ($index === null) {
            return;
        }

        // For translated models: require a locale translation — missing = remove stale entry.
        // For non-translated models (Brand, Manufacturer): fall through using model slug directly.
        $hasTranslations = method_exists($model, 'translation');
        $translation     = $hasTranslations ? $model->translation($locale) : null;

        if ($hasTranslations && $translation === null) {
            SitemapEntry::where('sitemap_index_id', $index->id)
                ->where('model_type', $morphAlias)
                ->where('model_id', $model->getKey())
                ->delete();
            return;
        }

        $slug = (string) ($translation?->slug ?? $model->getAttribute('slug') ?? '');

        if ($slug === '') {
            return;
        }

        $isBlogPost = $morphAlias === 'blog_post' && $model instanceof \App\Models\BlogPost;
        $url = $isBlogPost
            ? \App\Support\LocaleUrl::forBlogPost($model, $locale)
            : LocaleUrl::for($morphAlias, $slug, $locale);

        if ($url === '') {
            return;
        }

        // Build alternate_urls for hreflang xlinks.
        $alternateUrls = [];
        foreach (config('app.supported_locales') as $altLocale) {
            if ($isBlogPost) {
                $altUrl = \App\Support\LocaleUrl::forBlogPost($model, $altLocale);
                if ($altUrl !== '') {
                    $alternateUrls[$altLocale] = $altUrl;
                }
            } elseif ($hasTranslations) {
                $altTranslation = $model->translation($altLocale);
                if ($altTranslation) {
                    $alternateUrls[$altLocale] = LocaleUrl::for($morphAlias, $altTranslation->slug, $altLocale);
                }
            } else {
                $alternateUrls[$altLocale] = LocaleUrl::for($morphAlias, $slug, $altLocale);
            }
        }

        $isActive = (bool) ($model->getAttribute('is_active') ?? true);

        SitemapEntry::updateOrCreate(
            [
                'sitemap_index_id' => $index->id,
                'model_type'       => $morphAlias,
                'model_id'         => $model->getKey(),
            ],
            [
                'locale'        => $locale,
                'url'           => $url,
                'alternate_urls' => $alternateUrls ?: null,
                'changefreq'    => $config['changefreq'],
                'priority'      => $config['priority'],
                'last_modified' => $model->updated_at ?? now(),
                'is_active'     => $isActive,
            ]
        );

        $this->syncEntryCount($index);
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

        $dom->appendChild($dom->createProcessingInstruction('xml-stylesheet', 'type="text/xsl" href="/sitemap.xsl"'));

        $urlset = $dom->createElementNS(
            'http://www.sitemaps.org/schemas/sitemap/0.9',
            'urlset'
        );
        $urlset->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:xhtml',
            'http://www.w3.org/1999/xhtml'
        );
        $dom->appendChild($urlset);

        $defaultLocale = config('app.fallback_locale', 'vi');

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

            // ── hreflang alternate links ──────────────────────────────────────
            $alternateUrls = (array) ($entry->alternate_urls ?? []);
            if (! empty($alternateUrls)) {
                foreach ($alternateUrls as $hreflang => $href) {
                    $link = $dom->createElementNS('http://www.w3.org/1999/xhtml', 'xhtml:link');
                    $link->setAttribute('rel', 'alternate');
                    $link->setAttribute('hreflang', (string) $hreflang);
                    $link->setAttribute('href', htmlspecialchars((string) $href, ENT_XML1));
                    $url->appendChild($link);
                }

                $defaultHref = $alternateUrls[$defaultLocale] ?? reset($alternateUrls);
                $xdefault = $dom->createElementNS('http://www.w3.org/1999/xhtml', 'xhtml:link');
                $xdefault->setAttribute('rel', 'alternate');
                $xdefault->setAttribute('hreflang', 'x-default');
                $xdefault->setAttribute('href', htmlspecialchars((string) $defaultHref, ENT_XML1));
                $url->appendChild($xdefault);
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
