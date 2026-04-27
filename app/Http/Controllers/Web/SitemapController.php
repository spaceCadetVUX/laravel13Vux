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
