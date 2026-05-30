<?php

namespace App\Services\Mcp;

use App\Models\Brand;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Support\Collection;

class McpAuditService
{
    // Supported model types → model class + description field
    private const MODEL_MAP = [
        'product'          => [Product::class,      'translations', 'description'],
        'category'         => [Category::class,      'translations', 'description'],
        'blog_post'        => [BlogPost::class,      'translations', 'body'],
        'blog_category'    => [BlogCategory::class,  'translations', 'description'],
        'brand'            => [Brand::class,         null,           'description'],
        'manufacturer'     => [Manufacturer::class,  null,           'description'],
    ];

    public function audit(array $filters): array
    {
        $types      = $this->parseTypes($filters['model_type'] ?? null);
        $locales    = $this->parseLocales($filters['locale'] ?? 'vi,en');
        $missingReq = $this->parseMissingFields($filters['missing'] ?? null);
        $isActive   = isset($filters['is_active']) ? filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
        $perPage    = min((int) ($filters['per_page'] ?? 50), 100);
        $page       = max((int) ($filters['page'] ?? 1), 1);

        $allItems = collect();

        foreach ($types as $type) {
            $allItems = $allItems->merge(
                $this->auditType($type, $locales, $missingReq, $isActive)
            );
        }

        // Pagination
        $total  = $allItems->count();
        $paged  = $allItems->forPage($page, $perPage)->values();

        // Summary
        $summary = $this->buildSummary($allItems);

        return [
            'data'    => $paged->all(),
            'summary' => $summary,
            'meta'    => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / $perPage),
            ],
        ];
    }

    private function auditType(string $type, array $locales, array $missingReq, ?bool $isActive): Collection
    {
        [$modelClass, $relationName, $descField] = self::MODEL_MAP[$type];
        $hasTranslation = $relationName !== null;

        $query = $modelClass::query();

        // is_active filter
        if ($isActive !== null) {
            if ($type === 'blog_post') {
                $status = \App\Enums\BlogPostStatus::Published;
                $isActive ? $query->where('status', $status) : $query->where('status', '!=', $status);
            } else {
                $query->where('is_active', $isActive);
            }
        }

        // Eager load what we need
        $eagerLoads = [];
        if ($hasTranslation) {
            $eagerLoads[] = 'translations';
        }
        // Load SEO meta for non-category types (category stores SEO in translation)
        if ($type !== 'category') {
            $eagerLoads[] = 'seoMetas';
        }

        if ($eagerLoads) {
            $query->with($eagerLoads);
        }

        return $query->get()->map(function ($entity) use ($type, $locales, $descField, $hasTranslation, $missingReq) {
            $missing = [];

            foreach ($locales as $locale) {
                $localeMissing = [];

                if (in_array('description', $missingReq, true)) {
                    if (! $this->hasDescription($entity, $locale, $descField, $hasTranslation)) {
                        $localeMissing[] = 'description';
                    }
                }

                if (in_array('meta_title', $missingReq, true) || in_array('meta_description', $missingReq, true)) {
                    [$hasMeta, $hasMetaDesc] = $this->hasSeo($entity, $locale, $type);
                    if (in_array('meta_title', $missingReq, true) && ! $hasMeta) {
                        $localeMissing[] = 'meta_title';
                    }
                    if (in_array('meta_description', $missingReq, true) && ! $hasMetaDesc) {
                        $localeMissing[] = 'meta_description';
                    }
                }

                if ($localeMissing) {
                    $missing[$locale] = $localeMissing;
                }
            }

            // Only include in results if something is actually missing
            if (empty($missing)) {
                return null;
            }

            $slug = $this->entitySlug($entity, $type);

            return [
                'model_type'  => $type,
                'slug'        => $slug,
                'name'        => $this->entityName($entity, $type),
                'is_active'   => $this->entityIsActive($entity, $type),
                'missing'     => $missing,
                'context_url' => "/api/v1/mcp/{$type}s/{$slug}/context",
            ];
        })->filter()->values();
    }

    private function hasDescription(mixed $entity, string $locale, string $field, bool $hasTranslation): bool
    {
        if (! $hasTranslation) {
            return filled($entity->{$field});
        }

        $translation = $entity->translations->firstWhere('locale', $locale);

        return $translation && filled($translation->{$field});
    }

    private function hasSeo(mixed $entity, string $locale, string $type): array
    {
        // Category stores SEO in its translation table directly
        if ($type === 'category') {
            $translation = $entity->translations->firstWhere('locale', $locale);
            return [
                filled($translation?->meta_title),
                filled($translation?->meta_description),
            ];
        }

        // All other types use the polymorphic seo_meta table
        $seoMeta = $entity->seoMetas->firstWhere('locale', $locale);

        return [
            filled($seoMeta?->meta_title),
            filled($seoMeta?->meta_description),
        ];
    }

    private function entitySlug(mixed $entity, string $type): string
    {
        // blog_posts.slug was dropped — slug lives on blog_post_translations
        if ($type === 'blog_post') {
            return $entity->translations->firstWhere('locale', 'vi')?->slug
                ?? $entity->translations->firstWhere('locale', 'en')?->slug
                ?? (string) $entity->id;
        }

        return (string) $entity->slug;
    }

    private function entityName(mixed $entity, string $type): string
    {
        if (in_array($type, ['brand', 'manufacturer'], true)) {
            return $entity->name;
        }

        $nameField = $type === 'blog_post' ? 'title' : 'name';

        return $entity->translations->firstWhere('locale', 'vi')?->{$nameField}
            ?? $entity->translations->firstWhere('locale', 'en')?->{$nameField}
            ?? $this->entitySlug($entity, $type);
    }

    private function entityIsActive(mixed $entity, string $type): bool
    {
        if ($type === 'blog_post') {
            return $entity->status === \App\Enums\BlogPostStatus::Published;
        }

        return (bool) $entity->is_active;
    }

    private function buildSummary(Collection $items): array
    {
        $byType = [];
        foreach (array_keys(self::MODEL_MAP) as $type) {
            $count = $items->where('model_type', $type)->count();
            if ($count > 0) {
                $byType[$type] = $count;
            }
        }

        return [
            'total_missing' => $items->count(),
            'by_type'       => $byType,
        ];
    }

    private function parseTypes(?string $param): array
    {
        $valid = array_keys(self::MODEL_MAP);

        if (blank($param)) {
            return $valid;
        }

        return array_values(array_intersect(
            array_map('trim', explode(',', $param)),
            $valid
        )) ?: $valid;
    }

    // ── Entity List ───────────────────────────────────────────────────────────

    public function entityList(string $modelType, array $filters): array
    {
        if (! array_key_exists($modelType, self::MODEL_MAP)) {
            abort(404, "Unknown model type: {$modelType}");
        }

        [$modelClass, $relationName, $descField] = self::MODEL_MAP[$modelType];
        $hasTranslation = $relationName !== null;

        $locales            = $this->parseLocales($filters['locale'] ?? 'vi,en');
        $perPage            = min((int) ($filters['per_page'] ?? 20), 100);
        $page               = max((int) ($filters['page'] ?? 1), 1);
        $hasDescFilter      = isset($filters['has_description'])
            ? filter_var($filters['has_description'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;
        $hasSeoFilter       = isset($filters['has_seo'])
            ? filter_var($filters['has_seo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;
        $filterLocale       = $locales[0]; // apply has_* filters on the first locale only

        $eagerLoads = $hasTranslation ? ['translations'] : [];
        if ($modelType !== 'category') {
            $eagerLoads[] = 'seoMetas';
        }

        $all = $modelClass::query()
            ->with($eagerLoads)
            ->get()
            ->map(function ($entity) use ($modelType, $locales, $descField, $hasTranslation) {
                $contentStatus = [];
                foreach ($locales as $locale) {
                    [$hasMeta, $hasMetaDesc] = $this->hasSeo($entity, $locale, $modelType);
                    $contentStatus[$locale] = [
                        'has_description' => $this->hasDescription($entity, $locale, $descField, $hasTranslation),
                        'has_seo'         => $hasMeta && $hasMetaDesc,
                    ];
                }

                return [
                    'slug'           => $this->entitySlug($entity, $modelType),
                    'name'           => $this->entityName($entity, $modelType),
                    'is_active'      => $this->entityIsActive($entity, $modelType),
                    'mcp_drafted_at' => $entity->mcp_drafted_at?->toIso8601String(),
                    'content_status' => $contentStatus,
                ];
            });

        // Apply optional content filters on the primary locale
        if ($hasDescFilter !== null) {
            $all = $all->filter(fn ($item) => $item['content_status'][$filterLocale]['has_description'] === $hasDescFilter);
        }
        if ($hasSeoFilter !== null) {
            $all = $all->filter(fn ($item) => $item['content_status'][$filterLocale]['has_seo'] === $hasSeoFilter);
        }

        $total = $all->count();
        $paged = $all->forPage($page, $perPage)->values();

        return [
            'data' => $paged->all(),
            'meta' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / $perPage),
            ],
        ];
    }

    // ── Readiness score (shared with ReviewQueueService) ──────────────────────

    public function readinessScore(mixed $entity, string $modelType): array
    {
        [$modelClass, $relationName, $descField] = self::MODEL_MAP[$modelType] ?? [null, null, null];

        if (! $modelClass) {
            return ['score' => 0, 'issues' => ['unknown model type']];
        }

        $hasTranslation = $relationName !== null;
        $issues         = [];
        $points         = 0;

        foreach (['vi', 'en'] as $locale) {
            if (! $this->hasDescription($entity, $locale, $descField, $hasTranslation)) {
                $issues[] = "description_{$locale} missing";
            } else {
                $points += 25;
            }

            [$hasMeta, $hasMetaDesc] = $this->hasSeo($entity, $locale, $modelType);
            if (! $hasMeta || ! $hasMetaDesc) {
                $issues[] = "seo_{$locale} missing";
            } else {
                $points += 25;
            }
        }

        return ['score' => $points, 'issues' => $issues];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function parseLocales(string $param): array
    {
        $locales = array_map('trim', explode(',', $param));

        return array_values(array_intersect($locales, ['vi', 'en'])) ?: ['vi', 'en'];
    }

    private function parseMissingFields(?string $param): array
    {
        $valid = ['description', 'meta_title', 'meta_description'];

        if (blank($param)) {
            return $valid;
        }

        $fields = array_map('trim', explode(',', $param));

        return array_values(array_intersect($fields, $valid)) ?: $valid;
    }
}
