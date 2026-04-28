<?php

namespace Tests\Feature\Multilingual;

use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SeoTagsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('scout.driver', 'collection');
    }

    private function createProductWithTranslations(): array
    {
        $product = Product::factory()->create(['is_active' => true]);

        $vi = ProductTranslation::create([
            'product_id' => $product->id,
            'locale'     => 'vi',
            'name'       => 'Sản phẩm SEO',
            'slug'       => 'san-pham-seo',
        ]);

        $en = ProductTranslation::create([
            'product_id' => $product->id,
            'locale'     => 'en',
            'name'       => 'SEO Product',
            'slug'       => 'seo-product',
        ]);

        return compact('product', 'vi', 'en');
    }

    public function test_canonical_is_self_referencing(): void
    {
        $this->createProductWithTranslations();

        $html = $this->get('/vi/products/san-pham-seo')
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString(
            'rel="canonical"',
            $html
        );

        // Canonical points to the vi URL, not /en/
        $this->assertStringContainsString('/vi/products/san-pham-seo', $html);
        $this->assertStringNotContainsString(
            'rel="canonical" href="' . url('/en/products'),
            $html
        );
    }

    public function test_hreflang_appears_on_both_locales(): void
    {
        $this->createProductWithTranslations();

        $viHtml = $this->get('/vi/products/san-pham-seo')
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString('hreflang="vi"', $viHtml);
        $this->assertStringContainsString('hreflang="en"', $viHtml);

        $enHtml = $this->get('/en/products/seo-product')
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString('hreflang="vi"', $enHtml);
        $this->assertStringContainsString('hreflang="en"', $enHtml);
    }

    public function test_x_default_points_to_vi(): void
    {
        $this->createProductWithTranslations();

        $html = $this->get('/vi/products/san-pham-seo')
            ->assertStatus(200)
            ->getContent();

        // x-default hreflang must point to the /vi/ version
        $this->assertMatchesRegularExpression(
            '/hreflang="x-default"[^>]*href="[^"]*\/vi\//',
            $html
        );
    }
}
