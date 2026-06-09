<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Seo\SitemapIndex;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Serve the master sitemap index (sitemap.xml).
     * Lists all 8 active child sitemaps (vi/en × 4 types).
     */
    public function index(): Response
    {
        $indexes = SitemapIndex::where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()
            ->view('sitemap.index', compact('indexes'))
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Serve static pages sitemap (sitemap-static.xml).
     * Hardcoded list of pages not tied to any DB model.
     */
    public function static(): Response
    {
        $base = rtrim(config('app.url'), '/');

        $pages = [
            // Home
            ['vi' => "$base/vi", 'en' => "$base/en", 'priority' => '1.0', 'changefreq' => 'daily'],
            // About
            ['vi' => "$base/vi/gioi-thieu", 'en' => "$base/en/about", 'priority' => '0.7', 'changefreq' => 'monthly'],
            // Shop
            ['vi' => "$base/vi/cua-hang", 'en' => "$base/en/shop", 'priority' => '0.8', 'changefreq' => 'daily'],
            // Blog index
            ['vi' => "$base/vi/bai-viet", 'en' => "$base/en/blog", 'priority' => '0.7', 'changefreq' => 'daily'],
            // Solutions
            ['vi' => "$base/vi/giai-phap/dali-casambi", 'en' => "$base/en/solutions/dali-casambi", 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['vi' => "$base/vi/giai-phap/wireless-casambi", 'en' => "$base/en/solutions/wireless-casambi", 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['vi' => "$base/vi/giai-phap/theo-vai-tro", 'en' => "$base/en/solutions/by-role", 'priority' => '0.6', 'changefreq' => 'monthly'],
        ];

        return response()
            ->view('sitemap.static', compact('pages'))
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /**
     * Serve a child sitemap (sitemap-{locale}-{type}.xml).
     * Renders live from sitemap_entries with hreflang xlinks.
     */
    public function child(string $locale, string $type): Response
    {
        $index = SitemapIndex::where('name', "{$locale}-{$type}")
            ->where('is_active', true)
            ->firstOrFail();

        $entries = $index->entries()
            ->where('is_active', true)
            ->orderBy('url')
            ->get();

        return response()
            ->view('sitemap.child', compact('entries', 'locale', 'type'))
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
