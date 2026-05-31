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
            if (!in_array($locale, ['vi', 'en'], true)) continue;

            $tr = $post->translations()->firstOrNew(['locale' => $locale]);

            if ($tr->exists && $tr->is_mcp_protected) continue;

            foreach (['title', 'slug', 'excerpt', 'body'] as $field) {
                if (!array_key_exists($field, $trans)) continue;
                if (!$overwrite && $tr->exists && filled($tr->$field)) continue;
                $tr->$field = $trans[$field];
            }

            if (!$tr->exists && empty($tr->slug)) {
                if ($locale === 'vi' && filled($routeSlug)) {
                    $tr->slug = $routeSlug;
                } elseif (filled($tr->title)) {
                    $tr->slug = Str::slug($tr->title);
                }
            }

            if ($tr->isDirty()) {
                if (!$tr->exists && (empty($tr->title) || empty($tr->slug))) continue;
                $tr->blog_post_id = $post->id;
                $tr->locale       = $locale;
                $tr->save();
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

            if (blank($seoMeta->robots)) $seoMeta->robots = 'index, follow';

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
