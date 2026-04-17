<?php

namespace Tests\Feature\Product;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Use the in-memory collection driver — no Meilisearch required in tests.
        Config::set('scout.driver', 'collection');
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_returns_422_when_query_is_missing(): void
    {
        $this->getJson('/api/v1/search')
            ->assertStatus(422);
    }

    public function test_returns_422_when_query_is_too_short(): void
    {
        $this->getJson('/api/v1/search?q=x')
            ->assertStatus(422);
    }

    public function test_returns_422_when_type_is_invalid(): void
    {
        $this->getJson('/api/v1/search?q=LED&type=invalid')
            ->assertStatus(422);
    }

    // ── Response shape ────────────────────────────────────────────────────────

    public function test_returns_products_and_blog_for_type_all(): void
    {
        Product::factory()->create(['name' => 'LED Smart Panel', 'is_active' => true]);

        BlogPost::create([
            'title'        => 'LED Installation Guide',
            'slug'         => 'led-installation-guide',
            'content'      => 'Full content here.',
            'status'       => BlogPostStatus::Published,
            'published_at' => now(),
        ]);

        $this->getJson('/api/v1/search?q=LED')
            ->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => ['products', 'blog'],
                'meta' => ['query', 'total_products', 'total_blog'],
            ])
            ->assertJson([
                'status' => 'success',
                'meta'   => [
                    'query'          => 'LED',
                    'total_products' => 1,
                    'total_blog'     => 1,
                ],
            ]);
    }

    public function test_returns_only_products_when_type_is_products(): void
    {
        Product::factory()->create(['name' => 'LED Panel Pro', 'is_active' => true]);

        BlogPost::create([
            'title'        => 'LED Blog Post',
            'slug'         => 'led-blog-post',
            'content'      => 'Some content.',
            'status'       => BlogPostStatus::Published,
            'published_at' => now(),
        ]);

        $this->getJson('/api/v1/search?q=LED&type=products')
            ->assertStatus(200)
            ->assertJsonPath('data.blog', [])
            ->assertJsonPath('meta.total_blog', 0)
            ->assertJson(['meta' => ['total_products' => 1]]);
    }

    public function test_returns_only_blog_when_type_is_blog(): void
    {
        Product::factory()->create(['name' => 'LED Panel Pro', 'is_active' => true]);

        BlogPost::create([
            'title'        => 'LED Blog Guide',
            'slug'         => 'led-blog-guide',
            'content'      => 'Some content.',
            'status'       => BlogPostStatus::Published,
            'published_at' => now(),
        ]);

        $this->getJson('/api/v1/search?q=LED&type=blog')
            ->assertStatus(200)
            ->assertJsonPath('data.products', [])
            ->assertJsonPath('meta.total_products', 0)
            ->assertJson(['meta' => ['total_blog' => 1]]);
    }

    // ── Filtering ─────────────────────────────────────────────────────────────

    public function test_excludes_inactive_products(): void
    {
        Product::factory()->inactive()->create(['name' => 'LED Hidden Product']);

        $this->getJson('/api/v1/search?q=LED&type=products')
            ->assertStatus(200)
            ->assertJsonPath('meta.total_products', 0);
    }

    public function test_excludes_draft_blog_posts(): void
    {
        BlogPost::create([
            'title'   => 'LED Draft Article',
            'slug'    => 'led-draft-article',
            'content' => 'Draft content.',
            'status'  => BlogPostStatus::Draft,
        ]);

        $this->getJson('/api/v1/search?q=LED&type=blog')
            ->assertStatus(200)
            ->assertJsonPath('meta.total_blog', 0);
    }
}
