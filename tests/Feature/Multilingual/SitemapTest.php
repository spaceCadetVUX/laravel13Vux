<?php

namespace Tests\Feature\Multilingual;

use App\Models\Seo\SitemapEntry;
use App\Models\Seo\SitemapIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    private const CHILD_TYPES = ['products', 'product-categories', 'blog', 'blog-categories'];

    private function seedAllIndexes(): void
    {
        foreach (['vi', 'en'] as $locale) {
            foreach (self::CHILD_TYPES as $type) {
                SitemapIndex::create([
                    'name'      => "{$locale}-{$type}",
                    'filename'  => "sitemap-{$locale}-{$type}.xml",
                    'url'       => url("sitemap-{$locale}-{$type}.xml"),
                    'locale'    => $locale,
                    'is_active' => true,
                ]);
            }
        }
    }

    public function test_sitemap_index_has_8_children(): void
    {
        $this->seedAllIndexes();

        $content = $this->get('/sitemap.xml')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        $this->assertSame(8, substr_count($content, '<sitemap>'));
    }

    public function test_child_sitemap_has_hreflang_xlinks(): void
    {
        $index = SitemapIndex::create([
            'name'      => 'vi-products',
            'filename'  => 'sitemap-vi-products.xml',
            'url'       => url('sitemap-vi-products.xml'),
            'locale'    => 'vi',
            'is_active' => true,
        ]);

        SitemapEntry::create([
            'sitemap_index_id' => $index->id,
            'model_type'       => 'product',
            'model_id'         => '00000000-0000-0000-0000-000000000001',
            'locale'           => 'vi',
            'url'              => url('/vi/products/test-product'),
            'alternate_urls'   => [
                'vi' => url('/vi/products/test-product'),
                'en' => url('/en/products/test-product'),
            ],
            'is_active'        => true,
        ]);

        $content = $this->get('/sitemap-vi-products.xml')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        $this->assertStringContainsString('xhtml:link', $content);
        $this->assertStringContainsString('hreflang="vi"', $content);
        $this->assertStringContainsString('hreflang="en"', $content);
    }
}
