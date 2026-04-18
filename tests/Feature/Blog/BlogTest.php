<?php

namespace Tests\Feature\Blog;

use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('scout.driver', 'collection');
    }

    // ── 1. List published posts ───────────────────────────────────────────────

    public function test_can_list_published_blog_posts(): void
    {
        BlogPost::factory()->count(3)->published()->create();

        $this->getJson('/api/v1/blog')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'title', 'slug', 'excerpt', 'published_at']],
                'meta' => ['current_page', 'total', 'per_page', 'last_page'],
            ])
            ->assertJsonPath('meta.total', 3);
    }

    // ── 2. Draft posts hidden from list ──────────────────────────────────────

    public function test_draft_posts_not_in_list(): void
    {
        BlogPost::factory()->count(2)->published()->create();
        BlogPost::factory()->draft()->create();

        $this->getJson('/api/v1/blog')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    // ── 3. Filter by category ─────────────────────────────────────────────────

    public function test_can_filter_blog_by_category(): void
    {
        $category = BlogCategory::factory()->create(['slug' => 'tech-news']);
        $inCat    = BlogPost::factory()->published()->create(['blog_category_id' => $category->id]);
        BlogPost::factory()->published()->create(); // different category

        $this->getJson('/api/v1/blog?category=tech-news')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', $inCat->slug);
    }

    // ── 4. Filter by tag ──────────────────────────────────────────────────────

    public function test_can_filter_blog_by_tag(): void
    {
        $tag    = BlogTag::factory()->create(['slug' => 'casambi']);
        $tagged = BlogPost::factory()->published()->create();
        $tagged->tags()->attach($tag->id);
        BlogPost::factory()->published()->create(); // untagged

        $this->getJson('/api/v1/blog?tag=casambi')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', $tagged->slug);
    }

    // ── 5. Blog post detail ───────────────────────────────────────────────────

    public function test_can_get_blog_post_detail(): void
    {
        BlogPost::factory()->published()->create(['slug' => 'mesh-lighting']);

        $this->getJson('/api/v1/blog/mesh-lighting')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id', 'title', 'slug', 'excerpt', 'content',
                    'seo', 'jsonld_schemas', 'published_at',
                ],
            ]);
    }

    // ── 6. Draft post returns 404 ─────────────────────────────────────────────

    public function test_draft_post_detail_returns_404(): void
    {
        BlogPost::factory()->draft()->create(['slug' => 'hidden-draft']);

        $this->getJson('/api/v1/blog/hidden-draft')
            ->assertStatus(404);
    }

    // ── 7. Only approved comments returned ───────────────────────────────────

    public function test_can_list_approved_comments(): void
    {
        $post = BlogPost::factory()->published()->create(['slug' => 'post-with-comments']);

        BlogComment::factory()->count(2)->approved()->create(['blog_post_id' => $post->id]);
        BlogComment::factory()->pending()->create(['blog_post_id' => $post->id]);

        $this->getJson('/api/v1/blog/post-with-comments/comments')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    // ── 8. Authenticated user can submit comment ──────────────────────────────

    public function test_authenticated_user_can_submit_comment(): void
    {
        $user  = User::factory()->create();
        $post  = BlogPost::factory()->published()->create(['slug' => 'commentable-post']);
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/blog/commentable-post/comments', ['body' => 'Great article!'])
            ->assertStatus(201)
            ->assertJsonPath('data.is_approved', false);
    }

    // ── 9. Unauthenticated user cannot submit comment ─────────────────────────

    public function test_unauthenticated_user_cannot_comment(): void
    {
        BlogPost::factory()->published()->create(['slug' => 'auth-required-post']);

        $this->postJson('/api/v1/blog/auth-required-post/comments', ['body' => 'Hello!'])
            ->assertStatus(401);
    }

    // ── 10. Blog categories tree ──────────────────────────────────────────────

    public function test_can_get_blog_categories(): void
    {
        $parent = BlogCategory::factory()->create();
        BlogCategory::factory()->create(['parent_id' => $parent->id]);

        $this->getJson('/api/v1/blog/categories')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'slug', 'children']],
            ]);
    }

    // ── 11. Blog tags list ────────────────────────────────────────────────────

    public function test_can_get_blog_tags(): void
    {
        BlogTag::factory()->count(3)->create();

        $this->getJson('/api/v1/blog/tags')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'slug']],
            ])
            ->assertJsonCount(3, 'data');
    }
}
