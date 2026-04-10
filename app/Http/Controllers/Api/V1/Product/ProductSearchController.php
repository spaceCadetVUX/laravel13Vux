<?php

namespace App\Http\Controllers\Api\V1\Product;

use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\ApiResponse;
use App\Models\BlogPost;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ProductSearchController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/search?q={term}
     *
     * Searches products (name, sku, short_description) and published blog posts
     * (title, excerpt) using Scout (Meilisearch in production).
     *
     * When SCOUT_DRIVER=null or Meilisearch is unreachable, falls back to
     * Eloquent LIKE queries so the endpoint works in development.
     *
     * Returns a combined result set with a 'type' discriminator on each item.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return $this->success(data: ['products' => [], 'blog_posts' => []]);
        }

        [$products, $blogPosts] = $this->useScoutDriver()
            ? $this->scoutSearch($q)
            : $this->likeSearch($q);

        return $this->success(data: [
            'products'   => $products,
            'blog_posts' => $blogPosts,
        ]);
    }

    // ── Strategy selector ─────────────────────────────────────────────────────

    private function useScoutDriver(): bool
    {
        return config('scout.driver') !== 'null'
            && config('scout.driver') !== null;
    }

    // ── Scout (Meilisearch) path ───────────────────────────────────────────────

    private function scoutSearch(string $q): array
    {
        try {
            $products = Product::search($q)
                ->query(fn ($query) => $query->where('is_active', true)->with('category', 'images'))
                ->get()
                ->map(fn ($p) => $this->formatProduct($p));

            $blogPosts = BlogPost::search($q)
                ->query(fn ($query) => $query->published())
                ->get()
                ->map(fn ($b) => $this->formatBlogPost($b));

            return [$products, $blogPosts];
        } catch (Throwable) {
            // Meilisearch unavailable — fall back to LIKE search
            return $this->likeSearch($q);
        }
    }

    // ── Eloquent LIKE fallback (dev / SCOUT_DRIVER=null) ─────────────────────

    private function likeSearch(string $q): array
    {
        $term = '%' . $q . '%';

        $products = Product::with(['category', 'images'])
            ->where('is_active', true)
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term)
                    ->orWhere('short_description', 'like', $term);
            })
            ->limit(20)
            ->get()
            ->map(fn ($p) => $this->formatProduct($p));

        $blogPosts = BlogPost::published()
            ->where(function ($query) use ($term) {
                $query->where('title', 'like', $term)
                    ->orWhere('excerpt', 'like', $term);
            })
            ->limit(10)
            ->get()
            ->map(fn ($b) => $this->formatBlogPost($b));

        return [$products, $blogPosts];
    }

    // ── Formatters ────────────────────────────────────────────────────────────

    private function formatProduct(Product $p): array
    {
        return [
            'type'              => 'product',
            'id'                => $p->id,
            'name'              => $p->name,
            'slug'              => $p->slug,
            'sku'               => $p->sku,
            'short_description' => $p->short_description,
            'price'             => (string) $p->price,
            'sale_price'        => $p->sale_price ? (string) $p->sale_price : null,
            'category'          => $p->category?->name,
            'thumbnail'         => $p->images->first()?->url,
        ];
    }

    private function formatBlogPost(BlogPost $b): array
    {
        return [
            'type'    => 'blog_post',
            'id'      => $b->id,
            'title'   => $b->title,
            'slug'    => $b->slug,
            'excerpt' => $b->excerpt,
        ];
    }
}
