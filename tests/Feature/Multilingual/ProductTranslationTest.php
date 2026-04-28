<?php

namespace Tests\Feature\Multilingual;

use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ProductTranslationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('scout.driver', 'collection');
    }

    public function test_product_show_vi_returns_200(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        ProductTranslation::create([
            'product_id' => $product->id,
            'locale'     => 'vi',
            'name'       => 'Sản phẩm A',
            'slug'       => 'san-pham-a',
        ]);

        $this->get('/vi/products/san-pham-a')->assertStatus(200);
    }

    public function test_product_show_en_without_translation_redirects_to_vi(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        ProductTranslation::create([
            'product_id' => $product->id,
            'locale'     => 'vi',
            'name'       => 'Sản phẩm B',
            'slug'       => 'san-pham-b',
        ]);

        $this->get('/en/products/san-pham-b')
            ->assertStatus(302)
            ->assertRedirect('/vi/products/san-pham-b');
    }

    public function test_product_show_en_with_translation_returns_200(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        ProductTranslation::create([
            'product_id' => $product->id,
            'locale'     => 'vi',
            'name'       => 'Sản phẩm C',
            'slug'       => 'san-pham-c',
        ]);
        ProductTranslation::create([
            'product_id' => $product->id,
            'locale'     => 'en',
            'name'       => 'Product C',
            'slug'       => 'product-c',
        ]);

        $this->get('/en/products/product-c')->assertStatus(200);
    }

    public function test_product_show_nonexistent_slug_returns_404(): void
    {
        $this->get('/vi/products/nonexistent')->assertStatus(404);
    }
}
