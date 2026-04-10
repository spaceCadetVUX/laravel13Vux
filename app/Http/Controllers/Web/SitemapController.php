<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Seo\SitemapIndex;
use App\Services\Seo\SitemapService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class SitemapController extends Controller
{
    public function __construct(
        private readonly SitemapService $sitemapService,
    ) {}

    /**
     * Serve the master sitemap index (sitemap.xml).
     * Generated dynamically on every request; cached at the HTTP layer.
     */
    public function index(): Response
    {
        $xml = $this->sitemapService->generateIndex();

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Serve a child sitemap (sitemap-{name}.xml).
     *
     * 1. Look up the SitemapIndex by name — 404 if unknown or inactive.
     * 2. Try to stream the pre-generated file from disk.
     * 3. If the file is missing, generate it on-the-fly and return the result.
     */
    public function child(string $name): Response
    {
        /** @var SitemapIndex|null $index */
        $index = SitemapIndex::where('name', $name)
            ->where('is_active', true)
            ->first();

        if (! $index) {
            abort(404);
        }

        $storagePath = 'sitemaps/' . $index->filename;

        if (Storage::disk('public')->exists($storagePath)) {
            $xml = Storage::disk('public')->get($storagePath);
        } else {
            // File not yet generated — build it on-the-fly and persist it.
            $this->sitemapService->generateChild($index);
            $xml = Storage::disk('public')->get($storagePath);
        }

        return response($xml ?? '', 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
