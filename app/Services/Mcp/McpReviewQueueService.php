<?php

namespace App\Services\Mcp;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Support\Collection;

class McpReviewQueueService
{
    private const MODEL_MAP = [
        'product'       => Product::class,
        'category'      => Category::class,
        'blog_post'     => BlogPost::class,
        'blog_category' => BlogCategory::class,
        'brand'         => Brand::class,
        'manufacturer'  => Manufacturer::class,
    ];

    public function __construct(private readonly McpAuditService $auditService) {}

    public function queue(array $filters): array
    {
        $types        = $this->parseTypes($filters['model_type'] ?? null);
        $draftedAfter = isset($filters['drafted_after']) ? now()->parse($filters['drafted_after']) : null;
        $perPage      = min((int) ($filters['per_page'] ?? 20), 100);
        $page         = max((int) ($filters['page'] ?? 1), 1);

        $allItems = collect();

        foreach ($types as $type) {
            $allItems = $allItems->merge($this->queryType($type, $draftedAfter));
        }

        $allItems = $allItems->sortByDesc('drafted_at')->values();

        $total = $allItems->count();
        $paged = $allItems->forPage($page, $perPage)->values();

        return [
            'data'    => $paged->all(),
            'summary' => [
                'total_pending' => $total,
                'by_type'       => $this->summarizeByType($allItems),
            ],
            'meta'    => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / $perPage),
            ],
        ];
    }

    private function queryType(string $type, ?object $draftedAfter): Collection
    {
        $modelClass = self::MODEL_MAP[$type] ?? null;

        if (! $modelClass) {
            return collect();
        }

        // brand/manufacturer have no translations() relation
        $eagerLoads = match (true) {
            in_array($type, ['brand', 'manufacturer'], true) => ['seoMetas'],
            $type === 'category'                             => ['translations'],
            default                                          => ['translations', 'seoMetas'],
        };

        $query = $modelClass::query()
            ->whereNotNull('mcp_drafted_at')
            ->with($eagerLoads);

        if ($draftedAfter) {
            $query->where('mcp_drafted_at', '>=', $draftedAfter);
        }

        return $query->get()->map(function ($entity) use ($type) {
            $readiness  = $this->auditService->readinessScore($entity, $type);
            $entityName = $this->resolveName($entity, $type);
            $slug       = $this->resolveSlug($entity, $type);

            return [
                'model_type'       => $type,
                'slug'             => $slug,
                'name'             => $entityName,
                'drafted_by'       => $entity->mcp_token_id ? "mcp_token:{$entity->mcp_token_id}" : null,
                'drafted_at'       => $entity->mcp_drafted_at?->toIso8601String(),
                'readiness_score'  => $readiness['score'],
                'readiness_issues' => $readiness['issues'],
                'review_url'       => $this->buildReviewUrl($type, $slug),
                'activate_url'     => "PATCH /api/v1/mcp/{$type}s/{$slug}/activate",
            ];
        });
    }

    private function resolveSlug(mixed $entity, string $type): string
    {
        // blog_posts.slug was dropped — slug lives on blog_post_translations
        if ($type === 'blog_post') {
            return $entity->translations->firstWhere('locale', 'vi')?->slug
                ?? $entity->translations->firstWhere('locale', 'en')?->slug
                ?? (string) $entity->id;
        }

        return (string) $entity->slug;
    }

    private function resolveName(mixed $entity, string $type): string
    {
        if (in_array($type, ['brand', 'manufacturer'], true)) {
            return $entity->name;
        }

        $nameField = $type === 'blog_post' ? 'title' : 'name';

        return $entity->translations->firstWhere('locale', 'vi')?->{$nameField}
            ?? $entity->translations->firstWhere('locale', 'en')?->{$nameField}
            ?? $this->resolveSlug($entity, $type);
    }

    private function buildReviewUrl(string $type, string $slug): string
    {
        $segment = match ($type) {
            'product'       => 'products',
            'category'      => 'categories',
            'blog_post'     => 'blog-posts',
            'blog_category' => 'blog-categories',
            'brand'         => 'brands',
            'manufacturer'  => 'manufacturers',
            default         => $type . 's',
        };

        return "/admin/{$segment}/{$slug}/edit";
    }

    private function summarizeByType(Collection $items): array
    {
        $summary = [];
        foreach (array_keys(self::MODEL_MAP) as $type) {
            $count = $items->where('model_type', $type)->count();
            if ($count > 0) {
                $summary[$type] = $count;
            }
        }

        return $summary;
    }

    private function parseTypes(?string $param): array
    {
        $valid = array_keys(self::MODEL_MAP);

        if (blank($param)) {
            return $valid;
        }

        $requested = array_map('trim', explode(',', $param));

        return array_values(array_intersect($requested, $valid)) ?: $valid;
    }
}
