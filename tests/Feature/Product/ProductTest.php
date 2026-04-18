<?php

namespace Tests\Feature\Product;

use App\Enums\JsonldSchemaType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seo\JsonldSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Use the in-memory collection driver — no Meilisearch required in tests.
        Config::set('scout.driver', 'collection');
    }

    // ── List ──────────────────────────────────────────────────────────────────

    public function test_can_list_products(): void
    {
        Product::factory()->count(3)->create();

        $this->getJson('/api/v1/products')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'total', 'per_page', 'last_page'],
            ])
            ->assertJsonPath('meta.total', 3);
    }

    public function test_can_filter_products_by_category(): void
    {
        $category   = Category::factory()->create(['slug' => 'electronics']);
        $inCategory = Product::factory()->create();
        Product::factory()->create(); // outside category

        $inCategory->categories()->attach($category->id);

        $this->getJson('/api/v1/products?category=electronics')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', $inCategory->slug);
    }

    public function test_can_sort_products_by_price(): void
    {
        Product::factory()->create(['price' => 500.00]);
        Product::factory()->create(['price' => 10.00]);
        Product::factory()->create(['price' => 200.00]);

        $response = $this->getJson('/api/v1/products?sort=price_asc')
            ->assertStatus(200);

        $prices = collect($response->json('data'))
            ->pluck('price')
            ->map(fn ($p) => (float) $p)
            ->values()
            ->all();

        $this->assertEquals([10.0, 200.0, 500.0], $prices);
    }

    // ── Detail ────────────────────────────────────────────────────────────────

    public function test_can_get_product_detail(): void
    {
        Product::factory()->create(['slug' => 'test-product']);

        $this->getJson('/api/v1/products/test-product')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'slug', 'price',
                    'images', 'seo', 'jsonld_schemas',
                ],
            ]);
    }

    public function test_product_detail_includes_jsonld_schemas(): void
    {
        $product = Product::factory()->create(['slug' => 'schema-product']);

        JsonldSchema::create([
            'model_type'        => 'product',
            'model_id'          => $product->id,
            'schema_type'       => JsonldSchemaType::Product,
            'label'             => 'Product Schema',
            'payload'           => ['@type' => 'Product'],
            'is_active'         => true,
            'is_auto_generated' => false,
            'sort_order'        => 0,
        ]);

        $response = $this->getJson('/api/v1/products/schema-product')
            ->assertStatus(200);

        $this->assertNotEmpty($response->json('data.jsonld_schemas'));
    }

    public function test_nonexistent_product_returns_404(): void
    {
        $this->getJson('/api/v1/products/does-not-exist')
            ->assertStatus(404);
    }

    public function test_inactive_product_returns_404(): void
    {
        Product::factory()->inactive()->create(['slug' => 'hidden-product']);

        $this->getJson('/api/v1/products/hidden-product')
            ->assertStatus(404);
    }

    // ── Search ────────────────────────────────────────────────────────────────

    public function test_can_search_products(): void
    {
        Product::factory()->create(['name' => 'Wireless Keyboard']);

        $this->getJson('/api/v1/search?q=Wireless')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['products', 'blog'],
            ]);
    }
}
