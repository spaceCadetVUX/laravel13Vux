<?php

namespace App\Services\Mcp;

use App\Models\BlogCategory;
use App\Models\Seo\GeoEntityProfile;
use App\Models\Seo\JsonldSchema;
use App\Models\Seo\SeoMeta;
use Illuminate\Support\Facades\DB;

class McpBlogCategoryService
{
    // ── Public API ─────────────────────────────────────────────────────────────

    public function context(string $slug): array
    {
        $bc = BlogCategory::with(['translations', 'seoMetas', 'geoProfiles', 'parent.translations', 'children.translations', 'jsonldSchemas'])
            ->withCount('posts')
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->buildContextResponse($bc);
    }

    public function upsert(string $slug, array $data, int $tokenId, bool $dryRun): array
    {
        $preview = null;

        try {
            DB::transaction(function () use ($slug, $data, $tokenId, $dryRun, &$preview) {
                $overwrite = (bool) ($data['overwrite_existing'] ?? false);

                // ── Find or create ────────────────────────────────────────────
                $bc = BlogCategory::where('slug', $slug)->first();

                if (!$bc) {
                    $name = $data['name']
                        ?? $data['translations']['vi']['name']
                        ?? $data['translations']['en']['name']
                        ?? $slug;

                    $bc = new BlogCategory([
                        'slug'      => $slug,
                        'name'      => $name,
                        'is_active' => false,
                    ]);

                    if (!empty($data['parent_slug'])) {
                        $parent = BlogCategory::where('slug', $data['parent_slug'])->first();
                        if ($parent) $bc->parent_id = $parent->id;
                    }

                    $bc->save();
                }

                // ── Top-level fields ──────────────────────────────────────────
                if (array_key_exists('name', $data)) {
                    if ($overwrite || empty($bc->name) || $bc->name === $slug) {
                        $bc->name = $data['name'];
                    }
                }

                if (array_key_exists('sort_order', $data)) {
                    $bc->sort_order = $data['sort_order'];
                }

                $bc->mcp_drafted_at = now();
                $bc->mcp_token_id   = $tokenId;
                $bc->save();

                // ── Translations ──────────────────────────────────────────────
                $this->writeTranslations($bc, $data['translations'] ?? [], $overwrite);

                // ── SEO meta ──────────────────────────────────────────────────
                $this->writeSeoMeta($bc, $data['seo'] ?? [], $overwrite);

                // ── GEO/AI profile ────────────────────────────────────────────
                if (!empty($data['geo'])) {
                    $this->writeGeoProfiles($bc, $data['geo'], $overwrite);
                }

                $bc->load(['translations', 'seoMetas', 'geoProfiles', 'parent.translations', 'children.translations', 'jsonldSchemas']);
                $bc->loadCount('posts');
                $preview = $this->buildContextResponse($bc);

                if ($dryRun) throw new \RuntimeException('__mcp_dry_run__');
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== '__mcp_dry_run__') throw $e;
        }

        return ['data' => $preview];
    }

    public function readiness(string $slug): array
    {
        $bc = BlogCategory::with(['translations', 'seoMetas', 'geoProfiles'])
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->computeReadiness($bc);
    }

    public function activate(string $slug): array
    {
        $bc = BlogCategory::with(['translations', 'seoMetas', 'geoProfiles'])
            ->where('slug', $slug)
            ->firstOrFail();

        $readiness = $this->computeReadiness($bc);

        if (!empty($readiness['blocking_issues'])) {
            abort(422, 'Blog category chưa sẵn sàng để activate: ' . implode('; ', $readiness['blocking_issues']));
        }

        $bc->update([
            'is_active'      => true,
            'mcp_drafted_at' => null,
            'mcp_token_id'   => null,
        ]);

        return [
            'data' => $this->buildContextResponse(
                $bc->fresh(['translations', 'seoMetas', 'geoProfiles', 'parent.translations', 'children.translations', 'jsonldSchemas'])
                   ->loadCount('posts'),
            ),
        ];
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function computeReadiness(BlogCategory $bc): array
    {
        $checks   = [];
        $blocking = [];
        $warnings = [];
        $score    = 0;
        $total    = 0;

        foreach (['vi', 'en'] as $locale) {
            $tr      = $bc->translations->firstWhere('locale', $locale);
            $seoMeta = $bc->seoMetas->firstWhere('locale', $locale);

            // has_description (blocking)
            $hasDesc = !empty($tr?->description);
            $checks[$locale]['has_description'] = ['pass' => $hasDesc];
            $total++; if ($hasDesc) $score++;
            if (!$hasDesc) $blocking[] = "{$locale}.description missing";

            // description_min_length (warning)
            $descLen   = mb_strlen($tr?->description ?? '');
            $descLenOk = $descLen >= 100;
            $checks[$locale]['description_min_length'] = ['pass' => $descLenOk, 'min' => 100, 'value' => $descLen];
            $total++; if ($descLenOk) $score++;
            if ($hasDesc && !$descLenOk) $warnings[] = "{$locale}.description quá ngắn ({$descLen}/100 ký tự)";

            // has_meta_title (blocking)
            $hasMetaTitle = !empty($seoMeta?->meta_title);
            $checks[$locale]['has_meta_title'] = ['pass' => $hasMetaTitle];
            $total++; if ($hasMetaTitle) $score++;
            if (!$hasMetaTitle) $blocking[] = "{$locale}.meta_title missing";

            // meta_title_length (warning)
            $metaTitleLen = mb_strlen($seoMeta?->meta_title ?? '');
            $metaTitleOk  = $metaTitleLen <= 70;
            $checks[$locale]['meta_title_length'] = ['pass' => $metaTitleOk, 'value' => $metaTitleLen, 'max' => 70];
            $total++; if ($metaTitleOk) $score++;
            if ($hasMetaTitle && !$metaTitleOk) $warnings[] = "{$locale}.meta_title quá dài ({$metaTitleLen}/70 ký tự)";

            // has_meta_description (blocking)
            $hasMetaDesc = !empty($seoMeta?->meta_description);
            $checks[$locale]['has_meta_description'] = ['pass' => $hasMetaDesc];
            $total++; if ($hasMetaDesc) $score++;
            if (!$hasMetaDesc) $blocking[] = "{$locale}.meta_description missing";

            // has_faq (warning)
            $geoProfile = $bc->geoProfiles->firstWhere('locale', $locale);
            $faqItems   = $geoProfile?->faq ?? [];
            $hasFaq     = !empty($faqItems);
            $checks[$locale]['has_faq'] = ['pass' => $hasFaq, 'count' => count((array) $faqItems)];
            $total++; if ($hasFaq) $score++;
            if (!$hasFaq) $warnings[] = "{$locale}.faq chưa có — nên thêm ít nhất 3 câu hỏi (geo.{$locale}.faq)";
        }

        // has_geo vi (warning)
        $geoVi    = $bc->geoProfiles->firstWhere('locale', 'vi');
        $hasGeoVi = filled($geoVi?->ai_summary);
        $checks['has_geo_vi'] = ['pass' => $hasGeoVi];
        $total++; if ($hasGeoVi) $score++;
        if (!$hasGeoVi) $warnings[] = 'geo.vi.ai_summary chưa có';

        // has_slug
        $hasSlug = $bc->translations->filter(fn ($t) => !empty($t->slug))->count() >= 1;
        $checks['general']['has_slug'] = ['pass' => $hasSlug];
        $total++; if ($hasSlug) $score++;
        if (!$hasSlug) $blocking[] = 'general.slug missing';

        $scorePercent = $total > 0 ? (int) round(($score / $total) * 100) : 0;

        return [
            'slug'            => $bc->slug,
            'score'           => $scorePercent,
            'ready'           => empty($blocking),
            'checks'          => $checks,
            'blocking_issues' => $blocking,
            'warnings'        => $warnings,
        ];
    }

    private function writeTranslations(BlogCategory $bc, array $translations, bool $overwrite): void
    {
        foreach ($translations as $locale => $trans) {
            if (! in_array($locale, ['vi', 'en'], true)) continue;

            $existing = $bc->translations()->where('locale', $locale)->first();

            if ($existing?->is_mcp_protected) continue;

            $name = filled($trans['name'] ?? null) ? $trans['name'] : null;

            // Need at least a name to create
            if (! $existing && empty($name)) continue;

            $slug = filled($trans['slug'] ?? null)
                ? $trans['slug']
                : (filled($name) ? \Illuminate\Support\Str::slug($name) : null);

            if (empty($slug)) continue;

            if (! $existing) {
                $row = ['locale' => $locale, 'name' => $name, 'slug' => $slug];
                if (array_key_exists('description', $trans))  $row['description']  = $trans['description'];
                if (array_key_exists('rich_content', $trans)) $row['rich_content'] = $trans['rich_content'];
                $bc->translations()->create($row);
            } else {
                $update = [];
                foreach (['name' => $name, 'slug' => $slug] as $field => $value) {
                    if (! $overwrite && filled($existing->$field)) continue;
                    $update[$field] = $value;
                }
                foreach (['description', 'rich_content'] as $field) {
                    if (! array_key_exists($field, $trans)) continue;
                    if (! $overwrite && filled($existing->$field)) continue;
                    $update[$field] = $trans[$field];
                }
                if (! empty($update)) $existing->update($update);
            }
        }
    }

    private function writeSeoMeta(BlogCategory $bc, array $seo, bool $overwrite): void
    {
        $writeable = ['meta_title', 'meta_description', 'meta_keywords', 'canonical_url', 'og_title', 'og_description', 'og_image', 'robots'];

        foreach ($seo as $locale => $data) {
            if (!in_array($locale, ['vi', 'en'], true)) continue;

            $seoMeta = SeoMeta::firstOrNew([
                'model_type' => 'blog_category',
                'model_id'   => (string) $bc->id,
                'locale'     => $locale,
            ]);

            if ($seoMeta->exists && $seoMeta->is_mcp_protected) continue;

            foreach ($writeable as $field) {
                if (!array_key_exists($field, $data)) continue;
                if (!$overwrite && $seoMeta->exists && filled($seoMeta->$field)) continue;
                $seoMeta->$field = $data[$field];
            }

            if (filled($seoMeta->robots)) {
                $seoMeta->robots = str_replace(', ', ',', $seoMeta->robots);
            }
            if (blank($seoMeta->robots)) $seoMeta->robots = 'index,follow';

            $seoMeta->model_type = 'blog_category';
            $seoMeta->model_id   = (string) $bc->id;
            $seoMeta->locale     = $locale;
            $seoMeta->save();
        }
    }

    private function writeGeoProfiles(BlogCategory $bc, array $geo, bool $overwrite): void
    {
        $morphType     = $bc->getMorphClass();
        $modelId       = $bc->getKey();
        $writeable     = ['ai_summary', 'use_cases', 'target_audience', 'llm_context_hint'];
        $writeableJson = ['key_facts', 'faq'];

        foreach (['vi', 'en'] as $locale) {
            if (!array_key_exists($locale, $geo)) continue;

            $input = $geo[$locale];

            $profile = GeoEntityProfile::firstOrNew([
                'model_type' => $morphType,
                'model_id'   => $modelId,
                'locale'     => $locale,
            ]);

            foreach ($writeable as $field) {
                if (!array_key_exists($field, $input)) continue;
                if (!$overwrite && $profile->exists && filled($profile->$field)) continue;
                $profile->$field = $input[$field];
            }

            foreach ($writeableJson as $field) {
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

    private function buildContextResponse(BlogCategory $bc): array
    {
        $translations = [];
        foreach ($bc->translations as $tr) {
            $translations[$tr->locale] = [
                'name'             => $tr->name,
                'slug'             => $tr->slug,
                'description'      => $tr->description,
                'rich_content'     => $tr->rich_content,
                'is_mcp_protected' => $tr->is_mcp_protected,
            ];
        }

        $seo = [];
        foreach ($bc->seoMetas as $meta) {
            $seo[$meta->locale] = [
                'meta_title'       => $meta->meta_title,
                'meta_description' => $meta->meta_description,
                'meta_keywords'    => $meta->meta_keywords,
                'canonical_url'    => $meta->canonical_url,
                'og_title'         => $meta->og_title,
                'og_description'   => $meta->og_description,
                'og_image'         => $meta->og_image,
                'robots'           => $meta->robots,
                'is_mcp_protected' => $meta->is_mcp_protected,
            ];
        }

        $geo = [];
        foreach (['vi', 'en'] as $locale) {
            $profile = $bc->geoProfiles->firstWhere('locale', $locale);
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

        $parent = null;
        if ($bc->parent) {
            $parentTr = $bc->parent->translations->firstWhere('locale', 'vi')
                ?? $bc->parent->translations->first();
            $parent = [
                'slug' => $bc->parent->slug,
                'name' => $parentTr?->name ?? $bc->parent->name,
            ];
        }

        $children = $bc->children->map(function (BlogCategory $child) {
            $childTr = $child->translations->firstWhere('locale', 'vi')
                ?? $child->translations->first();
            return [
                'slug'      => $child->slug,
                'name'      => $childTr?->name ?? $child->name,
                'is_active' => $child->is_active,
            ];
        })->values()->all();

        $jsonldOut = [];
        foreach (($bc->jsonldSchemas ?? collect()) as $schema) {
            $jsonldOut[$schema->locale][] = [
                'type'              => $schema->schema_type?->value,
                'label'             => $schema->label,
                'is_auto_generated' => (bool) $schema->is_auto_generated,
                'is_active'         => (bool) $schema->is_active,
                'payload'           => $schema->payload,
            ];
        }

        return [
            'slug'           => $bc->slug,
            'name'           => $bc->name,
            'is_active'      => $bc->is_active,
            'sort_order'     => $bc->sort_order,
            'parent'         => $parent,
            'children'       => $children,
            'post_count'     => $bc->posts_count ?? $bc->loadCount('posts')->posts_count,
            'translations'   => $translations,
            'seo'            => $seo,
            'geo'            => $geo,
            'jsonld_schemas' => $jsonldOut,
            'mcp_drafted_at' => $bc->mcp_drafted_at?->toIso8601String(),
        ];
    }
}
