<?php

namespace App\Services\Mcp;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;

class McpSearchService
{
    // Entity types that use Scout/Meilisearch
    private const SCOUT_TYPES = ['product', 'blog_post'];

    // Entity types that use DB LIKE (low record count, mostly exact brand names)
    private const DB_TYPES = ['category', 'blog_category', 'brand', 'manufacturer'];

    private const VALID_TYPES = ['product', 'category', 'blog_post', 'blog_category', 'brand', 'manufacturer'];

    public function search(string $query, array $types, string $locale, int $perPage): array
    {
        if (blank($query)) {
            return ['data' => []];
        }

        $results = collect();

        foreach ($types as $type) {
            if (in_array($type, self::SCOUT_TYPES, true)) {
                $results = $results->merge($this->searchScout($type, $query, $locale));
            } elseif (in_array($type, self::DB_TYPES, true)) {
                $results = $results->merge($this->searchDb($type, $query));
            }
        }

        $data = $results
            ->sortByDesc('score')
            ->take($perPage)
            ->values()
            ->map(fn ($item) => [
                'model_type' => $item['model_type'],
                'slug'       => $item['slug'],
                'name'       => $item['name'],
                'is_active'  => $item['is_active'],
                'score'      => round($item['score'], 4),
            ])
            ->all();

        return ['data' => $data];
    }

    private function searchScout(string $type, string $query, string $locale): array
    {
        $model = match ($type) {
            'product'   => Product::class,
            'blog_post' => BlogPost::class,
            default     => null,
        };

        if (! $model) {
            return [];
        }

        $hits = $model::search($query)->take(20)->get();

        return $hits->map(function ($entity) use ($type, $locale) {
            [$name, $slug] = $this->resolveScoutIdentifiers($entity, $type, $locale);

            return [
                'model_type' => $type,
                'slug'       => $slug,
                'name'       => $name,
                'is_active'  => $this->isActive($entity, $type),
                'score'      => 1.0, // Meilisearch relevance order preserved via take()
            ];
        })->all();
    }

    private function searchDb(string $type, string $query): array
    {
        $like = '%' . $query . '%';

        [$modelClass, $nameField, $localeField] = match ($type) {
            'category'      => [Category::class,      'translations',  'name'],
            'blog_category' => [BlogCategory::class,  'translations',  'name'],
            'brand'         => [Brand::class,          null,           'name'],
            'manufacturer'  => [Manufacturer::class,   null,           'name'],
            default         => [null, null, null],
        };

        if (! $modelClass) {
            return [];
        }

        $hasTranslation = $localeField !== null && $nameField === 'translations';

        if ($hasTranslation) {
            $results = $modelClass::query()
                ->whereHas('translations', fn ($q) => $q->where('name', 'ilike', $like)->orWhere('slug', 'ilike', $like))
                ->with('translations')
                ->limit(10)
                ->get();
        } else {
            $results = $modelClass::query()
                ->where('name', 'ilike', $like)
                ->orWhere('slug', 'ilike', $like)
                ->limit(10)
                ->get();
        }

        return $results->map(function ($entity) use ($type, $hasTranslation, $query) {
            $name = $hasTranslation
                ? ($entity->translations->firstWhere('locale', 'vi')?->name
                    ?? $entity->translations->firstWhere('locale', 'en')?->name
                    ?? $entity->slug)
                : $entity->name;

            return [
                'model_type' => $type,
                'slug'       => $entity->slug,
                'name'       => $name,
                'is_active'  => (bool) $entity->is_active,
                'score'      => $this->likeScore($name, $entity->slug, $query),
            ];
        })->all();
    }

    /** Returns [name, slug] tuple — combines both resolutions into one DB call for blog_post. */
    private function resolveScoutIdentifiers(mixed $entity, string $type, string $locale): array
    {
        if ($type === 'product') {
            return [$entity->name ?? $entity->slug, (string) $entity->slug];
        }

        if ($type === 'blog_post') {
            // blog_posts.slug was dropped — slug and title live on blog_post_translations
            $translation = $entity->translations()->where('locale', $locale)->first()
                ?? $entity->translations()->where('locale', 'vi')->first();

            $slug = $translation?->slug ?? (string) $entity->id;
            $name = $translation?->title ?? $slug;

            return [$name, $slug];
        }

        return [$entity->slug, (string) $entity->slug];
    }

    private function isActive(mixed $entity, string $type): bool
    {
        if ($type === 'blog_post') {
            return $entity->status === \App\Enums\BlogPostStatus::Published;
        }

        return (bool) $entity->is_active;
    }

    private function likeScore(string $name, string $slug, string $query): float
    {
        $query = strtolower($query);
        $name  = strtolower($name);
        $slug  = strtolower($slug);

        if ($name === $query || $slug === $query) {
            return 1.0;
        }

        if (str_starts_with($name, $query) || str_starts_with($slug, $query)) {
            return 0.8;
        }

        return 0.5;
    }

    public function parseTypes(?string $param): array
    {
        if (blank($param)) {
            return self::VALID_TYPES;
        }

        $requested = array_map('trim', explode(',', $param));

        return array_values(array_intersect($requested, self::VALID_TYPES)) ?: self::VALID_TYPES;
    }
}
