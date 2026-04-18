<?php

namespace Tests\Feature\Seo;

use App\Models\Seo\SitemapIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_index_returns_xml(): void
    {
        $this->get('/sitemap.xml')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function test_sitemap_index_contains_child_links(): void
    {
        SitemapIndex::create([
            'name'      => 'products',
            'filename'  => 'sitemap-products.xml',
            'url'       => url('sitemap-products.xml'),
            'is_active' => true,
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $this->assertStringContainsString('sitemap-products.xml', $response->getContent());
    }

    public function test_product_sitemap_returns_xml(): void
    {
        Storage::fake('public');

        SitemapIndex::create([
            'name'      => 'products',
            'filename'  => 'sitemap-products.xml',
            'url'       => url('sitemap-products.xml'),
            'is_active' => true,
        ]);

        $this->get('/sitemap-products.xml')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function test_blog_sitemap_returns_xml(): void
    {
        Storage::fake('public');

        SitemapIndex::create([
            'name'      => 'blog',
            'filename'  => 'sitemap-blog.xml',
            'url'       => url('sitemap-blog.xml'),
            'is_active' => true,
        ]);

        $this->get('/sitemap-blog.xml')
            ->assertStatus(200);
    }

    public function test_unknown_sitemap_returns_404(): void
    {
        $this->get('/sitemap-nonexistent.xml')
            ->assertStatus(404);
    }
}
