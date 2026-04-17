<?php

namespace App\Http\Controllers\Api\V1\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchRequest;
use App\Http\Resources\Traits\ApiResponse;
use App\Models\BlogPost;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Throwable;

class ProductSearchController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/search?q={term}&type={products|blog|all}&page={n}&per_page={n}
     *
     * Full-text search via Meilisearch (Scout). Falls back to Eloquent LIKE
     * queries when SCOUT_DRIVER=null or Meilisearch is unreachable.
     */
    public function __invoke(SearchRequest $request): JsonResponse
    {
        $q       = $request->validated('q');
        $type    = $request->validated('type', 'all') ?? 'all';
        $perPage = (int) ($request->validated('per_page', 20) ?? 20);
        $page    = (int) ($request->validated('page', 1) ?? 1);

        [$products, $blog, $totalProducts, $totalBlog] = $this->isScoutEnabled()
            ? $this->scoutSearch($q, $type, $perPage, $page)
            : $this->likeSearch($q, $type, $perPage, $page);

        return $this->success(
            data: compact('products', 'blog'),
            meta: [
                'query'          => $q,
                'total_products' => $totalProducts,
                'total_blog'     => $totalBlog,
            ]
        );
    }

    // ── Driver detection ──────────────────────────────────────────────────────

    private function isScoutEnabled(): bool
    {
        $driver = config('scout.driver');

        return $driver !== 'null' && $driver !== null;
    }

    // ── Scout (Meilisearch) path ──────────────────────────────────────────────

    private function scoutSearch(string $q, string $type, int $perPage, int $page): array
    {
        try {
            $products      = [];
            $blog          = [];
            $totalProducts = 0;
            $totalBlog     = 0;

            if ($type === 'all' || $type === 'products') {
                $result        = Product::search($q)
                    ->where('is_active', true)
                    ->paginate($perPage, 'page', $page);
                $totalProducts = $result->total();
                $products      = $result->map(fn ($p) => $this->formatProduct($p))->all();
            }

            if ($type === 'all' || $type === 'blog') {
                $result    = BlogPost::search($q)
                    ->where('status', 'published')
                    ->paginate($perPage, 'page', $page);
                $totalBlog = $result->total();
                $blog      = $result->map(fn ($b) => $this->formatBlogPost($b))->all();
            }

            return [$products, $blog, $totalProducts, $totalBlog];
        } catch (Throwable) {
            // Meilisearch unavailable — fall back to Eloquent LIKE search
            return $this->likeSearch($q, $type, $perPage, $page);
        }
    }

    // ── Eloquent LIKE fallback (SCOUT_DRIVER=null / dev) ──────────────────────

    private function likeSearch(string $q, string $type, int $perPage, int $page): array
    {
        $term          = '%' . $q . '%';
        $products      = [];
        $blog          = [];
        $totalProducts = 0;
        $totalBlog     = 0;

        if ($type === 'all' || $type === 'products') {
            $paginator = Product::with(['category', 'images'])
                ->where('is_active', true)
                ->where(fn ($query) => $query
                    ->where('name', 'ilike', $term)
                    ->orWhere('sku', 'ilike', $term)
                    ->orWhere('short_description', 'ilike', $term))
                ->paginate($perPage, ['*'], 'page', $page);

            $totalProducts = $paginator->total();
            $products      = $paginator->map(fn ($p) => $this->formatProduct($p))->all();
        }

        if ($type === 'all' || $type === 'blog') {
            $paginator = BlogPost::with('blogCategory')
                ->published()
                ->where(fn ($query) => $query
                    ->where('title', 'ilike', $term)
                    ->orWhere('excerpt', 'ilike', $term))
                ->paginate($perPage, ['*'], 'page', $page);

            $totalBlog = $paginator->total();
            $blog      = $paginator->map(fn ($b) => $this->formatBlogPost($b))->all();
        }

        return [$products, $blog, $totalProducts, $totalBlog];
    }

    // ── Formatters ────────────────────────────────────────────────────────────

    private function formatProduct(Product $p): array
    {
        return [
            'id'                => $p->id,
            'name'              => $p->name,
            'slug'              => $p->slug,
            'sku'               => $p->sku,
            'short_description' => $p->short_description,
            'price'             => (string) $p->price,
            'sale_price'        => $p->sale_price ? (string) $p->sale_price : null,
            'category'          => $p->category?->name,
            'thumbnail'         => $p->images->first()?->url ?? null,
        ];
    }

    private function formatBlogPost(BlogPost $b): array
    {
        return [
            'id'      => $b->id,
            'title'   => $b->title,
            'slug'    => $b->slug,
            'excerpt' => $b->excerpt,
        ];
    }
}
