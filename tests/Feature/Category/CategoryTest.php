<?php

namespace Tests\Feature\Category;

use App\Models\Category;
use App\Models\Product;
use App\Services\Category\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Flush cached tree so each test starts from a clean DB state.
        Cache::forget(CategoryService::TREE_CACHE_KEY);
        // Use in-memory Scout driver — products attach to categories without Meilisearch.
        Config::set('scout.driver', 'collection');
    }

    // ── Category tree ─────────────────────────────────────────────────────────

    public function test_can_get_category_tree(): void
    {
        $parent = Category::factory()->create(['name' => 'Electronics']);
        Category::factory()->child($parent)->create(['name' => 'Smartphones']);

        $response = $this->getJson('/api/v1/categories')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'slug', 'sort_order', 'children']],
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertCount(1, $response->json('data.0.children'));
        $this->assertEquals('Electronics', $response->json('data.0.name'));
        $this->assertEquals('Smartphones', $response->json('data.0.children.0.name'));
    }

    // ── Category detail ───────────────────────────────────────────────────────

    public function test_can_get_category_with_products(): void
    {
        $category = Category::factory()->create(['slug' => 'smartphones']);
        $product  = Product::factory()->create(['is_active' => true]);
        $product->categories()->attach($category->id);

        $this->getJson('/api/v1/categories/smartphones')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'category' => ['id', 'name', 'slug'],
                    'products',
                ],
                'meta' => ['total', 'current_page'],
            ])
            ->assertJsonPath('meta.total', 1);
    }

    public function test_nonexistent_category_returns_404(): void
    {
        $this->getJson('/api/v1/categories/does-not-exist')
            ->assertStatus(404);
    }
}
