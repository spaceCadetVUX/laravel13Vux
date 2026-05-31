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
        $post = $this->findBySlug($slug, ['translations', 'seoMetas', 'blogCategory.translations', 'author', 'tags']);

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
                    ->whereHas('translations', fn($q) => $q->where('slug', $slug))
                    ->first();

                if ($post) {
                    if ($post->trashed()) $post->restore();
                } else {
                    $post = new BlogPost(['status' => BlogPostStatus::Draft]);
                    $post->save(); // HasUuids auto-generates the UUID
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

                // ── Status (default draft, never override to non-draft via upsert) ──
                if (isset($data['status'])) {
                    $requestedStatus = BlogPostStatus::tryFrom($data['status']);
                    // Upsert only allows draft — publish goes through /publish endpoint
                    if ($requestedStatus === BlogPostStatus::Draft || $requestedStatus === null) {
                        $post->status = BlogPostStatus::Draft;
                    }
                }

                // ── FAQ ───────────────────────────────────────────────────────
                $faqChanged = false;
                foreach (['faq_items_vi', 'faq_items_en'] as $field) {
                    if (!array_key_exists($field, $data)) continue;
                    if ($overwrite || empty($post->$field)) {
                        $post->$field = $data[$field];
                        $faqChanged   = true;
                    }
                }

                $post->mcp_drafted_at = now();
                $post->mcp_token_id   = $tokenId;
                $post->save();

                // ── Sync FAQ → geo_entity_profiles (required by syncFaqPage JSON-LD) ──
                if ($faqChanged) {
                    $this->writeGeoFaq($post, [
                        'vi' => $data['faq_items_vi'] ?? $post->faq_items_vi ?? [],
                        'en' => $data['faq_items_en'] ?? $post->faq_items_en ?? [],
                    ], $overwrite);
                }

                // ── Translations ──────────────────────────────────────────────
                $this->writeTranslations($post, $data['translations'] ?? [], $overwrite, $slug);

                // ── SEO meta ──────────────────────────────────────────────────
                $this->writeSeoMeta($post, $data['seo'] ?? [], $overwrite);

                // ── Tags ──────────────────────────────────────────────────────
                if (array_key_exists('tags', $data)) {
                    $this->syncTags($post, (array) $data['tags']);
                }

                $post->load(['translations', 'seoMetas', 'blogCategory.translations', 'author', 'tags']);
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
        $post = $this->findBySlug($slug, ['translations', 'seoMetas', 'blogCategory.translations', 'author', 'tags']);

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
                $post->fresh(['translations', 'seoMetas', 'blogCategory.translations', 'author', 'tags']),
            ),
        ];
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function findBySlug(string $slug, array $with = []): BlogPost
    {
        $post = BlogPost::withTrashed()
            ->with($with)
            ->whereHas('translations', fn($q) => $q->where('slug', $slug))
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

            // Auto-fill slug if not provided and creating new row:
            // vi → use route slug; en → generate from title
            if (!$tr->exists && empty($tr->slug)) {
                if ($locale === 'vi' && filled($routeSlug)) {
                    $tr->slug = $routeSlug;
                } elseif (filled($tr->title)) {
                    $tr->slug = \Illuminate\Support\Str::slug($tr->title);
                }
            }

            if ($tr->isDirty()) {
                // New translation requires title + slug (both NOT NULL in schema)
                if (!$tr->exists && (empty($tr->title) || empty($tr->slug))) continue;
                $tr->blog_post_id = $post->id;
                $tr->locale       = $locale;
                $tr->save();
            }
        }
    }

    private function writeSeoMeta(BlogPost $post, array $seo, bool $overwrite): void
    {
        foreach ($seo as $locale => $data) {
            if (!in_array($locale, ['vi', 'en'], true)) continue;

            $seoMeta = SeoMeta::firstOrNew([
                'model_type' => 'blog_post',
                'model_id'   => $post->id,
                'locale'     => $locale,
            ]);

            if ($seoMeta->exists && $seoMeta->is_mcp_protected) continue;

            foreach (['meta_title', 'meta_description', 'og_title', 'og_description', 'robots'] as $field) {
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

    private function writeGeoFaq(BlogPost $post, array $faqPerLocale, bool $overwrite): void
    {
        $morphType = $post->getMorphClass();
        $modelId   = $post->getKey();

        $normalize = fn (array $items): array => collect($items)
            ->filter(fn (array $item): bool => filled($item['question'] ?? null))
            ->map(fn (array $item): array => [
                'question' => trim($item['question']),
                'answer'   => trim($item['answer'] ?? ''),
            ])
            ->values()
            ->toArray();

        foreach (['vi', 'en'] as $locale) {
            if (!array_key_exists($locale, $faqPerLocale)) continue;

            $normalized = $normalize((array) $faqPerLocale[$locale]);

            $profile = GeoEntityProfile::firstOrNew([
                'model_type' => $morphType,
                'model_id'   => $modelId,
                'locale'     => $locale,
            ]);

            if ($profile->exists && !$overwrite && !empty($profile->faq)) continue;

            $profile->faq = $normalized;
            $profile->save();
        }
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
                'og_title'         => $meta->og_title,
                'og_description'   => $meta->og_description,
                'robots'           => $meta->robots,
                'is_mcp_protected' => $meta->is_mcp_protected,
            ];
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

        // Guard: null blog_category_id would generate IS NULL → return all uncategorized posts
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

        return [
            'id'             => $post->id,
            'status'         => $post->status?->value,
            'published_at'   => $post->published_at?->toIso8601String(),
            'blog_category'  => $blogCategory,
            'author'         => $post->author ? ['name' => $post->author->name, 'slug' => $post->author->slug] : null,
            'tags'           => $post->tags->map(fn($t) => ['slug' => $t->slug, 'name' => $t->name])->all(),
            'translations'   => $translations,
            'seo'            => $seo,
            'faq_items_vi'   => $post->faq_items_vi ?? [],
            'faq_items_en'   => $post->faq_items_en ?? [],
            'related_posts'  => $relatedPosts,
            'mcp_drafted_at' => $post->mcp_drafted_at?->toIso8601String(),
        ];
    }
}
