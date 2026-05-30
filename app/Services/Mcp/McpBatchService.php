<?php

namespace App\Services\Mcp;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\Seo\SeoMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class McpBatchService
{
    // ── Batch: SEO Meta auto-generation ───────────────────────────────────────

    public function seoMeta(array $data): array
    {
        $items     = $data['items'] ?? [];
        $locales   = $data['locales'] ?? ['vi', 'en'];
        $overwrite = (bool) ($data['overwrite_existing'] ?? false);

        if (count($items) > 50) {
            abort(422, 'Max 50 items per request.');
        }

        $filled  = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($items as $item) {
            $modelType = $item['model_type'] ?? null;
            $slug      = $item['slug'] ?? null;

            if (! $modelType || ! $slug) {
                $errors[] = [
                    'slug'       => $slug,
                    'model_type' => $modelType,
                    'error'      => 'model_type and slug are required.',
                ];
                continue;
            }

            try {
                [$itemFilled, $itemSkipped] = $this->processSeoMetaItem($modelType, $slug, $locales, $overwrite);
                $filled  += $itemFilled;
                $skipped += $itemSkipped;
            } catch (\Throwable $e) {
                $errors[] = [
                    'slug'       => $slug,
                    'model_type' => $modelType,
                    'error'      => $e->getMessage(),
                ];
            }
        }

        return [
            'data' => [
                'filled'  => $filled,
                'skipped' => $skipped,
                'errors'  => $errors,
            ],
        ];
    }

    // ── Batch: Translate (copy from_locale → to_locale) ───────────────────────

    public function translate(array $data): array
    {
        $items      = $data['items'] ?? [];
        $fromLocale = $data['from_locale'] ?? 'vi';
        $toLocale   = $data['to_locale'] ?? 'en';
        $fields     = isset($data['fields']) ? array_values($data['fields']) : null;
        $overwrite  = (bool) ($data['overwrite_existing'] ?? false);
        $dryRun     = (bool) ($data['dry_run'] ?? false);

        if (count($items) > 20) {
            abort(422, 'Max 20 items per request.');
        }

        if ($fromLocale === $toLocale) {
            abort(422, 'from_locale and to_locale must be different.');
        }

        $results = [];

        try {
            DB::transaction(function () use (
                $items, $fromLocale, $toLocale, $fields, $overwrite, $dryRun, &$results
            ) {
                foreach ($items as $item) {
                    $modelType = $item['model_type'] ?? null;
                    $slug      = $item['slug'] ?? null;

                    if (! $modelType || ! $slug) {
                        $results[] = [
                            'slug'       => $slug,
                            'model_type' => $modelType,
                            'status'     => 'error',
                            'error'      => 'model_type and slug are required.',
                        ];
                        continue;
                    }

                    try {
                        $result    = $this->processTranslateItem($modelType, $slug, $fromLocale, $toLocale, $fields, $overwrite);
                        $results[] = array_merge(['slug' => $slug, 'model_type' => $modelType], $result);
                    } catch (\Throwable $e) {
                        $results[] = [
                            'slug'       => $slug,
                            'model_type' => $modelType,
                            'status'     => 'error',
                            'error'      => $e->getMessage(),
                        ];
                    }
                }

                if ($dryRun) {
                    throw new \RuntimeException('__mcp_dry_run__');
                }
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== '__mcp_dry_run__') {
                throw $e;
            }
        }

        return [
            'data' => [
                'dry_run'     => $dryRun,
                'from_locale' => $fromLocale,
                'to_locale'   => $toLocale,
                'items'       => $results,
            ],
        ];
    }

    // ── Private: seo-meta helpers ─────────────────────────────────────────────

    /** @return array{int, int} [filled, skipped] */
    private function processSeoMetaItem(string $modelType, string $slug, array $locales, bool $overwrite): array
    {
        $entity = $this->resolveEntity($modelType, $slug);

        if (! $entity) {
            throw new \RuntimeException("Not found: {$modelType} '{$slug}'");
        }

        $filled  = 0;
        $skipped = 0;

        foreach ($locales as $locale) {
            if (! in_array($locale, ['vi', 'en'], true)) {
                continue;
            }

            [$metaTitle, $metaDesc] = $this->generateSeoText($entity, $modelType, $locale);

            if (! $metaTitle && ! $metaDesc) {
                $skipped++;
                continue;
            }

            $wrote = $modelType === 'category'
                ? $this->writeCategorySeo($entity, $locale, $metaTitle, $metaDesc, $overwrite) // @phpstan-ignore-line
                : $this->writeSeoMetaRow($modelType, (string) $entity->id, $locale, $metaTitle, $metaDesc, $overwrite);

            $wrote ? $filled++ : $skipped++;
        }

        return [$filled, $skipped];
    }

    /** @return array{string|null, string|null} [meta_title, meta_description] */
    private function generateSeoText(Model $entity, string $modelType, string $locale): array
    {
        [$rawName, $rawDesc] = match ($modelType) {
            'product' => [
                $entity->translations->firstWhere('locale', $locale)?->name ?? ($entity->name ?? ''), // @phpstan-ignore-line
                $entity->translations->firstWhere('locale', $locale)?->description ?? '',
            ],
            'category' => [
                $entity->translations->firstWhere('locale', $locale)?->name ?? ($entity->name ?? ''), // @phpstan-ignore-line
                $entity->translations->firstWhere('locale', $locale)?->description ?? '',
            ],
            'blog_post' => [
                $entity->translations->firstWhere('locale', $locale)?->title ?? '', // @phpstan-ignore-line
                ($entity->translations->firstWhere('locale', $locale)?->excerpt // @phpstan-ignore-line
                    ?: strip_tags($entity->translations->firstWhere('locale', $locale)?->body ?? '')), // @phpstan-ignore-line
            ],
            'blog_category' => [
                $entity->translations->firstWhere('locale', $locale)?->name ?? ($entity->name ?? ''), // @phpstan-ignore-line
                $entity->translations->firstWhere('locale', $locale)?->description ?? '',
            ],
            default => [$entity->name ?? '', $entity->description ?? ''], // brand, manufacturer
        };

        $metaTitle = filled($rawName) ? Str::limit(strip_tags((string) $rawName), 57, '...') : null;
        $metaDesc  = filled($rawDesc) ? Str::limit(strip_tags((string) $rawDesc), 152, '...') : null;

        return [$metaTitle, $metaDesc];
    }

    private function writeCategorySeo(
        Category $category,
        string $locale,
        ?string $metaTitle,
        ?string $metaDesc,
        bool $overwrite,
    ): bool {
        $tr = CategoryTranslation::where('category_id', $category->id)
            ->where('locale', $locale)
            ->first();

        if (! $tr || $tr->is_mcp_protected) {
            return false;
        }

        $changed = false;

        if ($metaTitle !== null && ($overwrite || empty($tr->meta_title))) {
            $tr->meta_title = $metaTitle;
            $changed        = true;
        }

        if ($metaDesc !== null && ($overwrite || empty($tr->meta_description))) {
            $tr->meta_description = $metaDesc;
            $changed              = true;
        }

        if ($changed) {
            $tr->save();
        }

        return $changed;
    }

    private function writeSeoMetaRow(
        string $modelType,
        string $modelId,
        string $locale,
        ?string $metaTitle,
        ?string $metaDesc,
        bool $overwrite,
    ): bool {
        $seoMeta = SeoMeta::firstOrNew([
            'model_type' => $modelType,
            'model_id'   => $modelId,
            'locale'     => $locale,
        ]);

        if ($seoMeta->exists && $seoMeta->is_mcp_protected) {
            return false;
        }

        $changed = false;

        if ($metaTitle !== null && ($overwrite || empty($seoMeta->meta_title))) {
            $seoMeta->meta_title = $metaTitle;
            $changed             = true;
        }

        if ($metaDesc !== null && ($overwrite || empty($seoMeta->meta_description))) {
            $seoMeta->meta_description = $metaDesc;
            $changed                   = true;
        }

        if ($changed) {
            if (blank($seoMeta->robots)) {
                $seoMeta->robots = 'index, follow';
            }
            $seoMeta->model_type = $modelType;
            $seoMeta->model_id   = $modelId;
            $seoMeta->locale     = $locale;
            $seoMeta->save();
        }

        return $changed;
    }

    // ── Private: translate helpers ─────────────────────────────────────────────

    private function processTranslateItem(
        string $modelType,
        string $slug,
        string $fromLocale,
        string $toLocale,
        ?array $requestedFields,
        bool $overwrite,
    ): array {
        $entity = $this->resolveEntity($modelType, $slug);

        if (! $entity) {
            throw new \RuntimeException("Not found: {$modelType} '{$slug}'");
        }

        $supportedFields = $this->supportedTranslateFields($modelType);
        $fieldsToProcess = $requestedFields !== null
            ? array_values(array_intersect($requestedFields, $supportedFields))
            : $supportedFields;

        if (empty($fieldsToProcess)) {
            return ['status' => 'skipped', 'reason' => 'No supported fields to process.'];
        }

        $copied  = [];
        $skipped = [];

        match ($modelType) {
            'product'       => $this->translateProductFields($entity, $fromLocale, $toLocale, $fieldsToProcess, $overwrite, $copied, $skipped), // @phpstan-ignore-line
            'category'      => $this->translateCategoryFields($entity, $fromLocale, $toLocale, $fieldsToProcess, $overwrite, $copied, $skipped), // @phpstan-ignore-line
            'blog_post'     => $this->translateBlogPostFields($entity, $fromLocale, $toLocale, $fieldsToProcess, $overwrite, $copied, $skipped), // @phpstan-ignore-line
            'blog_category' => $this->translateBlogCategoryFields($entity, $fromLocale, $toLocale, $fieldsToProcess, $overwrite, $copied, $skipped), // @phpstan-ignore-line
            default         => $this->translateSeoOnlyEntity($entity, $modelType, $fromLocale, $toLocale, $fieldsToProcess, $overwrite, $copied, $skipped),
        };

        return [
            'status'  => 'ok',
            'copied'  => $copied,
            'skipped' => $skipped,
        ];
    }

    private function supportedTranslateFields(string $modelType): array
    {
        return match ($modelType) {
            'product'       => ['name', 'description', 'short_description', 'meta_title', 'meta_description', 'og_title', 'og_description'],
            'category'      => ['name', 'description', 'rich_content', 'meta_title', 'meta_description', 'og_title', 'og_description'],
            'blog_post'     => ['title', 'excerpt', 'body', 'meta_title', 'meta_description'],
            'blog_category' => ['name', 'description', 'meta_title', 'meta_description'],
            default         => ['meta_title', 'meta_description'], // brand, manufacturer
        };
    }

    private function translateProductFields(
        Product $product,
        string $fromLocale,
        string $toLocale,
        array $fields,
        bool $overwrite,
        array &$copied,
        array &$skipped,
    ): void {
        $transFields = array_values(array_intersect($fields, ['name', 'description', 'short_description']));
        $seoFields   = array_values(array_intersect($fields, ['meta_title', 'meta_description', 'og_title', 'og_description']));

        if ($transFields) {
            $source = $product->translations->firstWhere('locale', $fromLocale);
            $target = $product->translations->firstWhere('locale', $toLocale);

            if (! $target) {
                foreach ($transFields as $f) {
                    $skipped[] = "{$f} (no {$toLocale} translation — use upsert to create it first)";
                }
            } elseif ($target->is_mcp_protected) {
                foreach ($transFields as $f) {
                    $skipped[] = "{$f} (mcp_protected)";
                }
            } else {
                $changed = $this->copyFields($transFields, $source, $target, $overwrite, $copied, $skipped);
                if ($changed > 0) {
                    $target->save();
                }
            }
        }

        if ($seoFields) {
            $sourceSeo = SeoMeta::where(['model_type' => 'product', 'model_id' => (string) $product->id, 'locale' => $fromLocale])->first();
            $targetSeo = SeoMeta::firstOrNew(['model_type' => 'product', 'model_id' => (string) $product->id, 'locale' => $toLocale]);

            if ($targetSeo->exists && $targetSeo->is_mcp_protected) {
                foreach ($seoFields as $f) {
                    $skipped[] = "{$f} (mcp_protected)";
                }
            } else {
                $changed = $this->copyFields($seoFields, $sourceSeo, $targetSeo, $overwrite, $copied, $skipped);
                if ($changed > 0) {
                    if (blank($targetSeo->robots)) $targetSeo->robots = 'index, follow';
                    $targetSeo->model_type = 'product';
                    $targetSeo->model_id   = (string) $product->id;
                    $targetSeo->locale     = $toLocale;
                    $targetSeo->save();
                }
            }
        }
    }

    private function translateCategoryFields(
        Category $category,
        string $fromLocale,
        string $toLocale,
        array $fields,
        bool $overwrite,
        array &$copied,
        array &$skipped,
    ): void {
        // Category has all fields (including SEO) in category_translations
        $source = $category->translations->firstWhere('locale', $fromLocale);
        $target = $category->translations->firstWhere('locale', $toLocale);

        if (! $target) {
            foreach ($fields as $f) {
                $skipped[] = "{$f} (no {$toLocale} translation — use upsert to create it first)";
            }
            return;
        }

        if ($target->is_mcp_protected) {
            foreach ($fields as $f) {
                $skipped[] = "{$f} (mcp_protected)";
            }
            return;
        }

        $changed = $this->copyFields($fields, $source, $target, $overwrite, $copied, $skipped);
        if ($changed > 0) {
            $target->save();
        }
    }

    private function translateBlogPostFields(
        BlogPost $post,
        string $fromLocale,
        string $toLocale,
        array $fields,
        bool $overwrite,
        array &$copied,
        array &$skipped,
    ): void {
        $transFields = array_values(array_intersect($fields, ['title', 'excerpt', 'body']));
        $seoFields   = array_values(array_intersect($fields, ['meta_title', 'meta_description']));

        if ($transFields) {
            $source = $post->translations->firstWhere('locale', $fromLocale);
            $target = $post->translations->firstWhere('locale', $toLocale);

            if (! $target) {
                foreach ($transFields as $f) {
                    $skipped[] = "{$f} (no {$toLocale} translation — use upsert to create it first)";
                }
            } elseif ($target->is_mcp_protected) {
                foreach ($transFields as $f) {
                    $skipped[] = "{$f} (mcp_protected)";
                }
            } else {
                $changed = $this->copyFields($transFields, $source, $target, $overwrite, $copied, $skipped);
                if ($changed > 0) {
                    $target->save();
                }
            }
        }

        if ($seoFields) {
            $sourceSeo = SeoMeta::where(['model_type' => 'blog_post', 'model_id' => (string) $post->id, 'locale' => $fromLocale])->first();
            $targetSeo = SeoMeta::firstOrNew(['model_type' => 'blog_post', 'model_id' => (string) $post->id, 'locale' => $toLocale]);

            if ($targetSeo->exists && $targetSeo->is_mcp_protected) {
                foreach ($seoFields as $f) {
                    $skipped[] = "{$f} (mcp_protected)";
                }
            } else {
                $changed = $this->copyFields($seoFields, $sourceSeo, $targetSeo, $overwrite, $copied, $skipped);
                if ($changed > 0) {
                    if (blank($targetSeo->robots)) $targetSeo->robots = 'index, follow';
                    $targetSeo->model_type = 'blog_post';
                    $targetSeo->model_id   = (string) $post->id;
                    $targetSeo->locale     = $toLocale;
                    $targetSeo->save();
                }
            }
        }
    }

    private function translateBlogCategoryFields(
        BlogCategory $bc,
        string $fromLocale,
        string $toLocale,
        array $fields,
        bool $overwrite,
        array &$copied,
        array &$skipped,
    ): void {
        $transFields = array_values(array_intersect($fields, ['name', 'description']));
        $seoFields   = array_values(array_intersect($fields, ['meta_title', 'meta_description']));

        if ($transFields) {
            $source = $bc->translations->firstWhere('locale', $fromLocale);
            $target = $bc->translations->firstWhere('locale', $toLocale);

            if (! $target) {
                foreach ($transFields as $f) {
                    $skipped[] = "{$f} (no {$toLocale} translation — use upsert to create it first)";
                }
            } elseif ($target->is_mcp_protected) {
                foreach ($transFields as $f) {
                    $skipped[] = "{$f} (mcp_protected)";
                }
            } else {
                $changed = $this->copyFields($transFields, $source, $target, $overwrite, $copied, $skipped);
                if ($changed > 0) {
                    $target->save();
                }
            }
        }

        if ($seoFields) {
            $sourceSeo = SeoMeta::where(['model_type' => 'blog_category', 'model_id' => (string) $bc->id, 'locale' => $fromLocale])->first();
            $targetSeo = SeoMeta::firstOrNew(['model_type' => 'blog_category', 'model_id' => (string) $bc->id, 'locale' => $toLocale]);

            if ($targetSeo->exists && $targetSeo->is_mcp_protected) {
                foreach ($seoFields as $f) {
                    $skipped[] = "{$f} (mcp_protected)";
                }
            } else {
                $changed = $this->copyFields($seoFields, $sourceSeo, $targetSeo, $overwrite, $copied, $skipped);
                if ($changed > 0) {
                    if (blank($targetSeo->robots)) $targetSeo->robots = 'index, follow';
                    $targetSeo->model_type = 'blog_category';
                    $targetSeo->model_id   = (string) $bc->id;
                    $targetSeo->locale     = $toLocale;
                    $targetSeo->save();
                }
            }
        }
    }

    private function translateSeoOnlyEntity(
        Model $entity,
        string $modelType,
        string $fromLocale,
        string $toLocale,
        array $fields,
        bool $overwrite,
        array &$copied,
        array &$skipped,
    ): void {
        $seoFields = array_values(array_intersect($fields, ['meta_title', 'meta_description']));

        if (empty($seoFields)) {
            return;
        }

        $sourceSeo = SeoMeta::where(['model_type' => $modelType, 'model_id' => (string) $entity->id, 'locale' => $fromLocale])->first();
        $targetSeo = SeoMeta::firstOrNew(['model_type' => $modelType, 'model_id' => (string) $entity->id, 'locale' => $toLocale]);

        if ($targetSeo->exists && $targetSeo->is_mcp_protected) {
            foreach ($seoFields as $f) {
                $skipped[] = "{$f} (mcp_protected)";
            }
            return;
        }

        $changed = $this->copyFields($seoFields, $sourceSeo, $targetSeo, $overwrite, $copied, $skipped);
        if ($changed > 0) {
            if (blank($targetSeo->robots)) $targetSeo->robots = 'index, follow';
            $targetSeo->model_type = $modelType;
            $targetSeo->model_id   = (string) $entity->id;
            $targetSeo->locale     = $toLocale;
            $targetSeo->save();
        }
    }

    // ── Private: shared helpers ────────────────────────────────────────────────

    private function resolveEntity(string $modelType, string $slug): ?Model
    {
        return match ($modelType) {
            'product'       => Product::with('translations')->where('slug', $slug)->first(),
            'category'      => Category::with('translations')->where('slug', $slug)->first(),
            'blog_post'     => BlogPost::with('translations')->withTrashed()
                                   ->whereHas('translations', fn ($q) => $q->where('slug', $slug))
                                   ->first(),
            'blog_category' => BlogCategory::with('translations')->where('slug', $slug)->first(),
            'brand'         => Brand::where('slug', $slug)->first(),
            'manufacturer'  => Manufacturer::where('slug', $slug)->first(),
            default         => null,
        };
    }

    /**
     * Copy field values from $source to $target, respecting overwrite flag.
     * Returns the number of fields actually written.
     */
    private function copyFields(
        array $fields,
        ?Model $source,
        Model $target,
        bool $overwrite,
        array &$copied,
        array &$skipped,
    ): int {
        $count = 0;

        foreach ($fields as $field) {
            $sourceValue = $source?->{$field};

            if (blank($sourceValue)) {
                $skipped[] = "{$field} (source empty)";
                continue;
            }

            if (! $overwrite && filled($target->{$field})) {
                $skipped[] = "{$field} (target exists)";
                continue;
            }

            $target->{$field} = $sourceValue;
            $copied[]          = $field;
            $count++;
        }

        return $count;
    }
}
