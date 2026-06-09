<?php

namespace App\Services\Mcp;

use App\Enums\BlogPostStatus;
use App\Models\Author;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Seo\GeoEntityProfile;
use App\Models\Seo\SeoMeta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class McpBlogPostService
{
    // ── Public API ─────────────────────────────────────────────────────────────

    public function context(string $slug): array
    {
        $post = $this->findBySlug($slug, ['translations', 'seoMetas', 'geoProfiles', 'blogCategory.translations', 'author', 'tags', 'jsonldSchemas']);

        return $this->buildContextResponse($post);
    }

    public function upsert(string $slug, array $data, int $tokenId, bool $dryRun): array
    {
        $preview = null;

        try {
            DB::transaction(function () use ($slug, $data, $tokenId, $dryRun, &$preview) {
                $overwrite = (bool) ($data['overwrite_existing'] ?? false);

                // ── Find or create ────────────────────────────────────────────
                $post = BlogPost::withTrashed()
                    ->whereHas('translations', fn ($q) => $q->where('slug', $slug))
                    ->first();

                if ($post) {
                    if ($post->trashed()) $post->restore();
                } else {
                    $post = new BlogPost(['status' => BlogPostStatus::Draft]);
                    $post->save();
                }

                // ── Blog category ─────────────────────────────────────────────
                if (!empty($data['blog_category_slug'])) {
                    $cat = BlogCategory::where('slug', $data['blog_category_slug'])->first();
                    if (!$cat) abort(422, "Blog category '{$data['blog_category_slug']}' not found.");
                    $post->blog_category_id = $cat->id;
                }

                // ── Author ────────────────────────────────────────────────────
                if (!empty($data['author_slug'])) {
                    $author = Author::where('slug', $data['author_slug'])->first();
                    if ($author) $post->author_id = $author->id;
                }

                // ── Featured image ────────────────────────────────────────────
                if (array_key_exists('featured_image', $data)) {
                    if ($overwrite || empty($post->featured_image)) {
                        $post->featured_image = $data['featured_image'];
                    }
                }

                // ── Status ────────────────────────────────────────────────────
                if (isset($data['status'])) {
                    $requestedStatus = BlogPostStatus::tryFrom($data['status']);
                    if ($requestedStatus === BlogPostStatus::Draft || $requestedStatus === null) {
                        $post->status = BlogPostStatus::Draft;
                    }
                }

                // ── FAQ (legacy faq_items_vi/en) ──────────────────────────────
                foreach (['faq_items_vi', 'faq_items_en'] as $field) {
                    if (!array_key_exists($field, $data)) continue;
                    if ($overwrite || empty($post->$field)) {
                        $post->$field = $data[$field];
                    }
                }

                $post->mcp_drafted_at = now();
                $post->mcp_token_id   = $tokenId;
                $post->save();

                // ── GEO profiles (AI context + FAQ) ───────────────────────────
                // geo[locale].faq takes priority; fallback to faq_items_vi/en
                $geoData = $data['geo'] ?? [];
                foreach (['vi' => 'faq_items_vi', 'en' => 'faq_items_en'] as $locale => $field) {
                    if (array_key_exists($field, $data) && !array_key_exists('faq', $geoData[$locale] ?? [])) {
                        $geoData[$locale]['faq'] = $data[$field];
                    }
                }
                if (!empty($geoData)) {
                    $this->writeGeoProfiles($post, $geoData, $overwrite);

                    // Sync geo[locale].faq → faq_items_vi/en so Filament FAQ tab stays in sync.
                    $faqSynced = false;
                    foreach (['vi' => 'faq_items_vi', 'en' => 'faq_items_en'] as $locale => $field) {
                        if (isset($geoData[$locale]['faq']) && ($overwrite || empty($post->$field))) {
                            $post->$field = $geoData[$locale]['faq'];
                            $faqSynced    = true;
                        }
                    }
                    if ($faqSynced) {
                        $post->save();
                    }
                }

                // ── Translations ──────────────────────────────────────────────
                $this->writeTranslations($post, $data['translations'] ?? [], $overwrite, $slug);

                // ── SEO meta ──────────────────────────────────────────────────
                $this->writeSeoMeta($post, $data['seo'] ?? [], $overwrite);

                // ── Tags ──────────────────────────────────────────────────────
                if (array_key_exists('tags', $data)) {
                    $this->syncTags($post, (array) $data['tags']);
                }

                $post->load(['translations', 'seoMetas', 'geoProfiles', 'blogCategory.translations', 'author', 'tags', 'jsonldSchemas']);
                $preview = $this->buildContextResponse($post);

                if ($dryRun) throw new \RuntimeException('__mcp_dry_run__');
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== '__mcp_dry_run__') throw $e;
        }

        return ['data' => $preview];
    }

    public function readiness(string $slug): array
    {
        $post = $this->findBySlug($slug, ['translations', 'seoMetas', 'geoProfiles']);

        return $this->computeReadiness($post);
    }

    public function publish(string $slug, array $data): array
    {
        $post = $this->findBySlug($slug, ['translations', 'seoMetas', 'geoProfiles', 'blogCategory.translations', 'author', 'tags', 'jsonldSchemas']);

        $publishedAt = isset($data['published_at'])
            ? Carbon::parse($data['published_at'])
            : now();

        $post->update([
            'status'         => BlogPostStatus::Published,
            'published_at'   => $publishedAt,
            'mcp_drafted_at' => null,
            'mcp_token_id'   => null,
        ]);

        return [
            'data' => $this->buildContextResponse(
                $post->fresh(['translations', 'seoMetas', 'geoProfiles', 'blogCategory.translations', 'author', 'tags', 'jsonldSchemas']),
            ),
        ];
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function computeReadiness(BlogPost $post): array
    {
        $checks   = [];
        $blocking = [];
        $warnings = [];
        $score    = 0;
        $total    = 0;

        foreach (['vi', 'en'] as $locale) {
            $isBlocking = $locale === 'vi';
            $tr         = $post->translations->firstWhere('locale', $locale);
            $seoMeta    = $post->seoMetas->firstWhere('locale', $locale);
            $geoProfile = $post->geoProfiles->firstWhere('locale', $locale);

            // has_title (blocking for vi)
            $hasTitle = filled($tr?->title);
            $checks[$locale]['has_title'] = ['pass' => $hasTitle];
            $total++; if ($hasTitle) $score++;
            if (!$hasTitle) {
                $isBlocking ? $blocking[] = "{$locale}.title missing" : $warnings[] = "{$locale}.title missing";
            }

            // has_body (blocking for vi)
            $hasBody = filled($tr?->body);
            $checks[$locale]['has_body'] = ['pass' => $hasBody];
            $total++; if ($hasBody) $score++;
            if (!$hasBody) {
                $isBlocking ? $blocking[] = "{$locale}.body missing" : $warnings[] = "{$locale}.body missing";
            }

            // body_min_length (warning only)
            $bodyLen   = mb_strlen($tr?->body ?? '');
            $bodyLenOk = $bodyLen >= 200;
            $checks[$locale]['body_min_length'] = ['pass' => $bodyLenOk, 'min' => 200, 'value' => $bodyLen];
            $total++; if ($bodyLenOk) $score++;
            if ($hasBody && !$bodyLenOk) $warnings[] = "{$locale}.body quá ngắn ({$bodyLen}/200 ký tự)";

            // has_excerpt (warning)
            $hasExcerpt = filled($tr?->excerpt);
            $checks[$locale]['has_excerpt'] = ['pass' => $hasExcerpt];
            $total++; if ($hasExcerpt) $score++;
            if (!$hasExcerpt) $warnings[] = "{$locale}.excerpt chưa có";

            // has_slug (blocking for vi)
            $hasSlug = filled($tr?->slug);
            $checks[$locale]['has_slug'] = ['pass' => $hasSlug];
            $total++; if ($hasSlug) $score++;
            if (!$hasSlug) {
                $isBlocking ? $blocking[] = "{$locale}.slug missing" : $warnings[] = "{$locale}.slug missing";
            }

            // has_meta_title (blocking for vi, warning for en)
            $hasMetaTitle = filled($seoMeta?->meta_title);
            $checks[$locale]['has_meta_title'] = ['pass' => $hasMetaTitle];
            $total++; if ($hasMetaTitle) $score++;
            if (!$hasMetaTitle) {
                $isBlocking ? $blocking[] = "{$locale}.meta_title missing" : $warnings[] = "{$locale}.meta_title missing";
            }

            // meta_title_length (warning)
            $metaTitleLen = mb_strlen($seoMeta?->meta_title ?? '');
            $metaTitleOk  = $metaTitleLen <= 70;
            $checks[$locale]['meta_title_length'] = ['pass' => $metaTitleOk, 'value' => $metaTitleLen, 'max' => 70];
            $total++; if ($metaTitleOk) $score++;
            if ($hasMetaTitle && !$metaTitleOk) $warnings[] = "{$locale}.meta_title quá dài ({$metaTitleLen}/70 ký tự)";

            // has_meta_description (blocking for vi, warning for en)
            $hasMetaDesc = filled($seoMeta?->meta_description);
            $checks[$locale]['has_meta_description'] = ['pass' => $hasMetaDesc];
            $total++; if ($hasMetaDesc) $score++;
            if (!$hasMetaDesc) {
                $isBlocking ? $blocking[] = "{$locale}.meta_description missing" : $warnings[] = "{$locale}.meta_description missing";
            }

            // has_faq (warning)
            $faqItems = $geoProfile?->faq ?? $post->{"faq_items_{$locale}"} ?? [];
            $hasFaq   = !empty($faqItems);
            $checks[$locale]['has_faq'] = ['pass' => $hasFaq, 'count' => count((array) $faqItems)];
            $total++; if ($hasFaq) $score++;
            if (!$hasFaq) $warnings[] = "{$locale}.faq chưa có — nên thêm ít nhất 3 câu hỏi (geo.{$locale}.faq)";
        }

        // has_featured_image (warning)
        $hasFeaturedImage = filled($post->featured_image);
        $checks['general']['has_featured_image'] = ['pass' => $hasFeaturedImage];
        $total++; if ($hasFeaturedImage) $score++;
        if (!$hasFeaturedImage) $warnings[] = 'featured_image chưa có';

        // has_blog_category (warning)
        $hasBlogCategory = filled($post->blog_category_id);
        $checks['general']['has_blog_category'] = ['pass' => $hasBlogCategory];
        $total++; if ($hasBlogCategory) $score++;
        if (!$hasBlogCategory) $warnings[] = 'blog_category chưa có';

        $scorePercent = $total > 0 ? (int) round(($score / $total) * 100) : 0;

        return [
            'slug'            => $post->translations->firstWhere('locale', 'vi')?->slug ?? '',
            'score'           => $scorePercent,
            'ready'           => empty($blocking),
            'checks'          => $checks,
            'blocking_issues' => $blocking,
            'warnings'        => $warnings,
        ];
    }

    private function findBySlug(string $slug, array $with = []): BlogPost
    {
        $post = BlogPost::withTrashed()
            ->with($with)
            ->whereHas('translations', fn ($q) => $q->where('slug', $slug))
            ->first();

        if (!$post) abort(404, "Blog post with slug '{$slug}' not found.");

        return $post;
    }

    private function writeTranslations(BlogPost $post, array $translations, bool $overwrite, string $routeSlug = ''): void
    {
        foreach ($translations as $locale => $trans) {
            if (! in_array($locale, ['vi', 'en'], true)) continue;

            $existing = $post->translations()->where('locale', $locale)->first();

            if ($existing?->is_mcp_protected) continue;

            $title = filled($trans['title'] ?? null) ? $trans['title'] : null;

            // Resolve slug
            $slug = filled($trans['slug'] ?? null) ? $trans['slug'] : null;
            if (! $slug && $locale === 'vi' && filled($routeSlug)) $slug = $routeSlug;
            if (! $slug && filled($title))                          $slug = Str::slug($title);

            if (! $existing) {
                if (empty($title) || empty($slug)) continue;
                $row = ['locale' => $locale, 'title' => $title, 'slug' => $slug];
                if (array_key_exists('excerpt', $trans)) $row['excerpt'] = $trans['excerpt'];
                if (array_key_exists('body', $trans))    $row['body']    = $trans['body'];
                $post->translations()->create($row);
            } else {
                $update = [];
                foreach (['title' => $title, 'slug' => $slug] as $field => $value) {
                    if ($value === null) continue;
                    if (! $overwrite && filled($existing->$field)) continue;
                    $update[$field] = $value;
                }
                foreach (['excerpt', 'body'] as $field) {
                    if (! array_key_exists($field, $trans)) continue;
                    if (! $overwrite && filled($existing->$field)) continue;
                    $update[$field] = $trans[$field];
                }
                if (! empty($update)) $existing->update($update);
            }
        }
    }

    private function writeSeoMeta(BlogPost $post, array $seo, bool $overwrite): void
    {
        $writeable = ['meta_title', 'meta_description', 'meta_keywords', 'canonical_url', 'og_title', 'og_description', 'og_image', 'robots'];

        foreach ($seo as $locale => $data) {
            if (!in_array($locale, ['vi', 'en'], true)) continue;

            $seoMeta = SeoMeta::firstOrNew([
                'model_type' => 'blog_post',
                'model_id'   => $post->id,
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

            $seoMeta->model_type = 'blog_post';
            $seoMeta->model_id   = $post->id;
            $seoMeta->locale     = $locale;
            $seoMeta->save();
        }
    }

    private function writeGeoProfiles(BlogPost $post, array $geoPerLocale, bool $overwrite): void
    {
        $morphType     = $post->getMorphClass();
        $modelId       = $post->getKey();
        $writeable     = ['ai_summary', 'use_cases', 'target_audience', 'llm_context_hint'];

        $normalize = fn (array $items): array => collect($items)
            ->filter(fn (array $item): bool => filled($item['question'] ?? null))
            ->map(fn (array $item): array => [
                'question' => trim($item['question']),
                'answer'   => trim($item['answer'] ?? ''),
            ])
            ->values()
            ->toArray();

        foreach (['vi', 'en'] as $locale) {
            if (!array_key_exists($locale, $geoPerLocale)) continue;

            $input = $geoPerLocale[$locale];

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

            if (array_key_exists('key_facts', $input)) {
                if ($overwrite || empty($profile->key_facts)) {
                    $profile->key_facts = $input['key_facts'];
                }
            }

            if (array_key_exists('faq', $input)) {
                $normalized = $normalize((array) $input['faq']);
                if ($overwrite || empty($profile->faq)) {
                    $profile->faq = $normalized;
                }
            }

            if ($profile->isDirty() || !$profile->exists) {
                $profile->model_type = $morphType;
                $profile->model_id   = $modelId;
                $profile->locale     = $locale;
                $profile->save();
            }
        }
    }

    private function syncTags(BlogPost $post, array $tagSlugs): void
    {
        $ids = collect($tagSlugs)->map(function (string $slug) {
            return BlogTag::firstOrCreate(
                ['slug' => $slug],
                ['name' => Str::title(str_replace('-', ' ', $slug))],
            )->id;
        })->all();

        $post->tags()->sync($ids);
    }

    private function buildContextResponse(BlogPost $post): array
    {
        $translations = [];
        foreach ($post->translations as $tr) {
            $translations[$tr->locale] = [
                'title'            => $tr->title,
                'slug'             => $tr->slug,
                'excerpt'          => $tr->excerpt,
                'body'             => $tr->body,
                'is_mcp_protected' => $tr->is_mcp_protected,
            ];
        }

        $seo = [];
        foreach ($post->seoMetas as $meta) {
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
            $profile = $post->geoProfiles->firstWhere('locale', $locale);
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

        $blogCategory = null;
        if ($post->blogCategory) {
            $catTr = $post->blogCategory->translations->firstWhere('locale', 'vi')
                ?? $post->blogCategory->translations->first();
            $blogCategory = [
                'slug' => $post->blogCategory->slug,
                'name' => $catTr?->name ?? $post->blogCategory->name,
            ];
        }

        $relatedPosts = [];
        if ($post->blog_category_id) {
            $relatedPosts = BlogPost::with('translations')
                ->where('blog_category_id', $post->blog_category_id)
                ->where('id', '!=', $post->id)
                ->whereNull('deleted_at')
                ->orderByDesc('published_at')
                ->limit(5)
                ->get()
                ->map(function (BlogPost $p) {
                    $viTr = $p->translations->firstWhere('locale', 'vi')
                        ?? $p->translations->first();
                    return [
                        'slug'   => $viTr?->slug,
                        'title'  => $viTr?->title,
                        'status' => $p->status?->value,
                    ];
                })
                ->all();
        }

        $jsonldOut = [];
        foreach (($post->jsonldSchemas ?? collect()) as $schema) {
            $jsonldOut[$schema->locale][] = [
                'type'              => $schema->schema_type?->value,
                'label'             => $schema->label,
                'is_auto_generated' => (bool) $schema->is_auto_generated,
                'is_active'         => (bool) $schema->is_active,
                'payload'           => $schema->payload,
            ];
        }

        return [
            'id'             => $post->id,
            'status'         => $post->status?->value,
            'published_at'   => $post->published_at?->toIso8601String(),
            'featured_image' => $post->featured_image,
            'blog_category'  => $blogCategory,
            'author'         => $post->author ? ['name' => $post->author->name, 'slug' => $post->author->slug] : null,
            'tags'           => $post->tags->map(fn ($t) => ['slug' => $t->slug, 'name' => $t->name])->all(),
            'translations'   => $translations,
            'seo'            => $seo,
            'geo'            => $geo,
            'faq_items_vi'   => $post->faq_items_vi ?? [],
            'faq_items_en'   => $post->faq_items_en ?? [],
            'jsonld_schemas' => $jsonldOut,
            'related_posts'  => $relatedPosts,
            'mcp_drafted_at' => $post->mcp_drafted_at?->toIso8601String(),
        ];
    }
}
