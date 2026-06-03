<?php

namespace App\Services\Mcp;

use App\Models\Category;
use App\Models\Seo\GeoEntityProfile;
use Illuminate\Support\Facades\DB;

class McpCategoryService
{
    // ── Public API ─────────────────────────────────────────────────────────────

    public function context(string $slug): array
    {
        $category = Category::with(['translations', 'parent.translations', 'children.translations', 'geoProfiles', 'jsonldSchemas'])
            ->withCount('products')
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->buildContextResponse($category);
    }

    public function readiness(string $slug): array
    {
        $category = Category::with(['translations', 'geoProfiles'])
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->computeReadiness($category);
    }

    public function upsert(string $slug, array $data, int $tokenId, bool $dryRun): array
    {
        $preview = null;

        try {
            DB::transaction(function () use ($slug, $data, $tokenId, $dryRun, &$preview) {
                $overwrite = (bool) ($data['overwrite_existing'] ?? false);

                // ── Find or create ────────────────────────────────────────────
                $category = Category::withTrashed()->where('slug', $slug)->first();

                if ($category) {
                    if ($category->trashed()) $category->restore();
                } else {
                    $name = $data['name']
                        ?? $data['translations']['vi']['name']
                        ?? $data['translations']['en']['name']
                        ?? $slug;

                    $category = new Category([
                        'slug'      => $slug,
                        'name'      => $name,
                        'is_active' => false,
                    ]);

                    if (!empty($data['parent_slug'])) {
                        $parent = Category::where('slug', $data['parent_slug'])->first();
                        if ($parent) $category->parent_id = $parent->id;
                    }

                    $category->save();
                }

                // ── Top-level fields ──────────────────────────────────────────
                if (array_key_exists('name', $data)) {
                    if ($overwrite || empty($category->name) || $category->name === $slug) {
                        $category->name = $data['name'];
                    }
                }

                if (array_key_exists('sort_order', $data)) {
                    $category->sort_order = $data['sort_order'];
                }

                // ── FAQ (legacy params → sync vào cả categories + geo_entity_profiles) ──
                foreach (['faq_items_vi', 'faq_items_en'] as $field) {
                    if (!array_key_exists($field, $data)) continue;
                    if ($overwrite || empty($category->$field)) {
                        $category->$field = $data[$field];
                    }
                }

                $category->mcp_drafted_at = now();
                $category->mcp_token_id   = $tokenId;
                $category->save();

                // ── GEO/AI profile ─────────────────────────────────────────────
                // Merge legacy faq_items_vi/en vào geo.vi.faq / geo.en.faq nếu chưa có
                $geoData = $data['geo'] ?? [];
                foreach (['vi' => 'faq_items_vi', 'en' => 'faq_items_en'] as $locale => $field) {
                    if (array_key_exists($field, $data) && !array_key_exists('faq', $geoData[$locale] ?? [])) {
                        $geoData[$locale]['faq'] = $data[$field];
                    }
                }
                if (!empty($geoData)) {
                    $this->writeGeoProfile($category, $geoData, $overwrite);

                    // Sync geo[locale].faq → faq_items_vi/en so Filament FAQ tab stays in sync.
                    $faqSynced = false;
                    foreach (['vi' => 'faq_items_vi', 'en' => 'faq_items_en'] as $locale => $field) {
                        if (isset($geoData[$locale]['faq']) && ($overwrite || empty($category->$field))) {
                            $category->$field = $geoData[$locale]['faq'];
                            $faqSynced        = true;
                        }
                    }
                    if ($faqSynced) {
                        $category->save();
                    }
                }

                // ── Translations + SEO (same table) ───────────────────────────
                $this->writeTranslations(
                    $category,
                    $data['translations'] ?? [],
                    $data['seo'] ?? [],
                    $overwrite,
                );

                $preview = $this->buildContextResponse(
                    $category->fresh(['translations', 'parent.translations', 'children.translations', 'geoProfiles']),
                );

                if ($dryRun) throw new \RuntimeException('__mcp_dry_run__');
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== '__mcp_dry_run__') throw $e;
        }

        return ['data' => $preview];
    }

    public function activate(string $slug): array
    {
        $category = Category::with(['translations', 'geoProfiles'])->where('slug', $slug)->firstOrFail();

        $readiness = $this->computeReadiness($category);

        if (!empty($readiness['blocking_issues'])) {
            abort(422, 'Category chưa sẵn sàng để activate: ' . implode('; ', $readiness['blocking_issues']));
        }

        $category->update([
            'is_active'      => true,
            'mcp_drafted_at' => null,
            'mcp_token_id'   => null,
        ]);

        return [
            'data' => $this->buildContextResponse(
                $category->fresh(['translations', 'parent.translations', 'children.translations', 'geoProfiles']),
            ),
        ];
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function computeReadiness(Category $category): array
    {
        $checks   = [];
        $blocking = [];
        $warnings = [];
        $score    = 0;
        $total    = 0;

        foreach (['vi', 'en'] as $locale) {
            $tr = $category->translations->firstWhere('locale', $locale);

            // has_description (blocking)
            $hasDesc = !empty($tr?->description);
            $checks[$locale]['has_description'] = ['pass' => $hasDesc];
            $total++; if ($hasDesc) $score++;
            if (!$hasDesc) $blocking[] = "{$locale}.description missing";

            // description_min_length (warning only)
            $descLen = mb_strlen($tr?->description ?? '');
            $descLenOk = $descLen >= 100;
            $checks[$locale]['description_min_length'] = ['pass' => $descLenOk, 'min' => 100, 'value' => $descLen];
            $total++; if ($descLenOk) $score++;
            if ($hasDesc && !$descLenOk) $warnings[] = "{$locale}.description quá ngắn ({$descLen}/100 ký tự)";

            // has_meta_title (blocking)
            $hasMetaTitle = !empty($tr?->meta_title);
            $checks[$locale]['has_meta_title'] = ['pass' => $hasMetaTitle];
            $total++; if ($hasMetaTitle) $score++;
            if (!$hasMetaTitle) $blocking[] = "{$locale}.meta_title missing";

            // meta_title_length (warning)
            $metaTitleLen = mb_strlen($tr?->meta_title ?? '');
            $metaTitleOk  = $metaTitleLen <= 70;
            $checks[$locale]['meta_title_length'] = ['pass' => $metaTitleOk, 'value' => $metaTitleLen, 'max' => 70];
            $total++; if ($metaTitleOk) $score++;
            if ($hasMetaTitle && !$metaTitleOk) $warnings[] = "{$locale}.meta_title quá dài ({$metaTitleLen}/70 ký tự)";

            // has_meta_description (blocking)
            $hasMetaDesc = !empty($tr?->meta_description);
            $checks[$locale]['has_meta_description'] = ['pass' => $hasMetaDesc];
            $total++; if ($hasMetaDesc) $score++;
            if (!$hasMetaDesc) $blocking[] = "{$locale}.meta_description missing";

            // has_faq (warning) — check geo_entity_profiles.faq (source of truth for JSON-LD)
            $geoProfile = $category->geoProfiles->firstWhere('locale', $locale);
            $faqItems   = $geoProfile?->faq ?? $category->{"faq_items_{$locale}"} ?? [];
            $hasFaq     = !empty($faqItems);
            $checks[$locale]['has_faq'] = ['pass' => $hasFaq, 'count' => count((array) $faqItems)];
            $total++; if ($hasFaq) $score++;
            if (!$hasFaq) $warnings[] = "{$locale}.faq chưa có — nên thêm ít nhất 3 câu hỏi (geo.{$locale}.faq)";
        }

        // General: must have at least one translation with a slug
        $hasSlug = $category->translations->filter(fn($t) => !empty($t->slug))->count() >= 1;
        $checks['general']['has_slug'] = ['pass' => $hasSlug];
        $total++; if ($hasSlug) $score++;
        if (!$hasSlug) $blocking[] = 'general.slug missing';

        $scorePercent = $total > 0 ? (int) round(($score / $total) * 100) : 0;

        return [
            'slug'            => $category->slug,
            'score'           => $scorePercent,
            'ready'           => empty($blocking),
            'checks'          => $checks,
            'blocking_issues' => $blocking,
            'warnings'        => $warnings,
        ];
    }

    private function writeGeoProfile(Category $category, array $geoPerLocale, bool $overwrite): void
    {
        $morphType = $category->getMorphClass();
        $modelId   = $category->getKey();

        foreach (['vi', 'en'] as $locale) {
            if (!array_key_exists($locale, $geoPerLocale)) continue;

            $input = $geoPerLocale[$locale];

            $profile = GeoEntityProfile::firstOrNew([
                'model_type' => $morphType,
                'model_id'   => $modelId,
                'locale'     => $locale,
            ]);

            foreach (['ai_summary', 'use_cases', 'target_audience', 'llm_context_hint'] as $field) {
                if (!array_key_exists($field, $input)) continue;
                if (!$overwrite && $profile->exists && filled($profile->$field)) continue;
                $profile->$field = $input[$field];
            }

            foreach (['key_facts', 'faq'] as $field) {
                if (!array_key_exists($field, $input)) continue;
                if (!$overwrite && $profile->exists && !empty($profile->$field)) continue;
                $profile->$field = $input[$field];
            }

            if ($profile->isDirty() || !$profile->exists) {
                $profile->model_type = $morphType;
                $profile->model_id   = $modelId;
                $profile->locale     = $locale;
                $profile->save();
            }
        }
    }

    private function writeTranslations(
        Category $category,
        array $translations,
        array $seo,
        bool $overwrite,
    ): void {
        $locales = array_unique(array_merge(array_keys($translations), array_keys($seo)));

        foreach ($locales as $locale) {
            $tr = $category->translations()->firstOrNew(['locale' => $locale]);

            if ($tr->is_mcp_protected) continue;

            $trans = $translations[$locale] ?? [];

            foreach (['name', 'slug', 'description', 'rich_content'] as $field) {
                if (!array_key_exists($field, $trans)) continue;
                if (!$overwrite && !empty($tr->$field)) continue;
                $tr->$field = $trans[$field];
            }

            $seoLocale = $seo[$locale] ?? [];
            foreach (['meta_title', 'meta_description', 'og_title', 'og_description', 'twitter_title', 'twitter_description'] as $field) {
                if (!array_key_exists($field, $seoLocale)) continue;
                if (!$overwrite && !empty($tr->$field)) continue;
                $tr->$field = $seoLocale[$field];
            }

            if ($tr->isDirty()) {
                // New translation row requires name + slug (both NOT NULL in schema)
                if (!$tr->exists && (empty($tr->name) || empty($tr->slug))) continue;
                $tr->category_id = $category->id;
                $tr->locale      = $locale;
                $tr->save();
            }
        }
    }

    private function buildContextResponse(Category $category): array
    {
        $translations = [];
        foreach ($category->translations as $tr) {
            $translations[$tr->locale] = [
                'name'                => $tr->name,
                'slug'                => $tr->slug,
                'description'         => $tr->description,
                'rich_content'        => $tr->rich_content,
                'meta_title'          => $tr->meta_title,
                'meta_description'    => $tr->meta_description,
                'og_title'            => $tr->og_title,
                'og_description'      => $tr->og_description,
                'twitter_title'       => $tr->twitter_title,
                'twitter_description' => $tr->twitter_description,
                'is_mcp_protected'    => $tr->is_mcp_protected,
            ];
        }

        $parent = null;
        if ($category->parent) {
            $parentTr = $category->parent->translations->firstWhere('locale', 'vi')
                ?? $category->parent->translations->first();
            $parent = [
                'slug' => $category->parent->slug,
                'name' => $parentTr?->name ?? $category->parent->name,
            ];
        }

        $children = $category->children->map(function (Category $child) {
            $childTr = $child->translations->firstWhere('locale', 'vi')
                ?? $child->translations->first();
            return [
                'slug'      => $child->slug,
                'name'      => $childTr?->name ?? $child->name,
                'is_active' => $child->is_active,
            ];
        })->values()->all();

        // ── GEO/AI profiles ───────────────────────────────────────────────────
        $geo = [];
        foreach (['vi', 'en'] as $locale) {
            $profile = $category->geoProfiles->firstWhere('locale', $locale);
            if ($profile) {
                $geo[$locale] = [
                    'ai_summary'       => $profile->ai_summary,
                    'use_cases'        => $profile->use_cases,
                    'target_audience'  => $profile->target_audience,
                    'llm_context_hint' => $profile->llm_context_hint,
                    'key_facts'        => $profile->key_facts ?? [],
                    'faq'              => $profile->faq ?? [],
                ];
            }
        }

        $jsonldOut = [];
        foreach (($category->jsonldSchemas ?? collect()) as $schema) {
            $jsonldOut[$schema->locale][] = [
                'type'             => $schema->schema_type?->value,
                'label'            => $schema->label,
                'is_auto_generated'=> (bool) $schema->is_auto_generated,
                'is_active'        => (bool) $schema->is_active,
                'payload'          => $schema->payload,
            ];
        }

        return [
            'slug'           => $category->slug,
            'name'           => $category->name,
            'is_active'      => $category->is_active,
            'sort_order'     => $category->sort_order,
            'parent'         => $parent,
            'children'       => $children,
            'product_count'  => $category->products_count ?? $category->loadCount('products')->products_count,
            'translations'   => $translations,
            'geo'            => $geo,
            'jsonld_schemas' => $jsonldOut,
            'faq_items_vi'   => $category->faq_items_vi ?? [],
            'faq_items_en'   => $category->faq_items_en ?? [],
            'mcp_drafted_at' => $category->mcp_drafted_at?->toIso8601String(),
        ];
    }
}
