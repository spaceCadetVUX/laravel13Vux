<?php

namespace App\Observers;

use App\Enums\BlogPostStatus;
use App\Enums\RedirectType;
use App\Jobs\Seo\SyncJsonldSchema;
use App\Jobs\Seo\SyncLlmsEntry;
use App\Jobs\Seo\SyncSitemapEntry;
use App\Models\BlogPost;
use App\Models\Seo\GeoEntityProfile;
use App\Models\Seo\JsonldSchema;
use App\Models\Seo\LlmsEntry;
use App\Models\Seo\Redirect;
use App\Models\Seo\SeoMeta;
use App\Models\Seo\SitemapEntry;
use App\Support\LocaleUrl;

class BlogPostObserver
{
    /**
     * Fire BEFORE the UPDATE SQL — getOriginal() still holds the old slug.
     * Creates a 301 redirect when the slug changes on a published post.
     */
    public function updating(BlogPost $blogPost): void
    {
        if (! $blogPost->isDirty('slug')) {
            return;
        }

        $oldSlug = $blogPost->getOriginal('slug');
        $newSlug = $blogPost->slug;

        if (! $oldSlug || ! $newSlug || $oldSlug === $newSlug) {
            return;
        }

        $defaultLocale = config('app.fallback_locale', 'vi');

        $blogPost->loadMissing(['blogCategory.translations']);
        $catTrans = $blogPost->blogCategory?->translations->where('locale', $defaultLocale)->first();
        $catSlug  = $catTrans?->slug ?? $blogPost->blogCategory?->slug;

        if ($catSlug) {
            $fromPath = "/vi/bai-viet/{$catSlug}/{$oldSlug}";
            $toPath   = "/vi/bai-viet/{$catSlug}/{$newSlug}";
        } else {
            $fromPath = parse_url(LocaleUrl::for('blog_post', $oldSlug, $defaultLocale), PHP_URL_PATH);
            $toPath   = parse_url(LocaleUrl::for('blog_post', $newSlug, $defaultLocale), PHP_URL_PATH);
        }

        Redirect::updateOrCreate(
            ['from_path' => $fromPath],
            [
                'to_path'   => $toPath,
                'type'      => RedirectType::Permanent,
                'locale'    => $defaultLocale,
                'is_active' => true,
            ]
        );
    }

    /**
     * Dispatch SEO sync jobs only when the post is published.
     * Draft and archived posts must not appear in sitemaps or LLMs docs.
     */
    public function saved(BlogPost $blogPost): void
    {
        if ($blogPost->status !== BlogPostStatus::Published) {
            // Post is no longer published — deactivate ALL SEO entries.
            $morphClass = $blogPost->getMorphClass();

            SitemapEntry::where('model_type', $morphClass)
                ->where('model_id', $blogPost->getKey())
                ->update(['is_active' => false]);

            LlmsEntry::where('model_type', $morphClass)
                ->where('model_id', $blogPost->getKey())
                ->update(['is_active' => false]);

            // Deactivate JSON-LD schemas so the API does not serve stale
            // structured data for archived / draft posts.
            JsonldSchema::where('model_type', $morphClass)
                ->where('model_id', $blogPost->getKey())
                ->update(['is_active' => false]);

            return;
        }

        $blogPost->loadMissing('translations');
        $loadedLocales = $blogPost->translations->pluck('locale')->all();

        foreach (config('app.supported_locales') as $locale) {
            if (in_array($locale, $loadedLocales, true)) {
                dispatch(new SyncJsonldSchema($blogPost, $locale))->onQueue('seo');
                dispatch(new SyncSitemapEntry($blogPost, $locale))->onQueue('seo');
                dispatch(new SyncLlmsEntry($blogPost, $locale))->onQueue('seo');
            }
        }
    }

    /**
     * Soft-delete deactivates SEO entries — never removes them.
     * Also fires when a published post is soft-deleted.
     */
    public function deleted(BlogPost $blogPost): void
    {
        $morphClass = $blogPost->getMorphClass(); // 'blog_post' via morphMap

        SitemapEntry::where('model_type', $morphClass)
            ->where('model_id', $blogPost->getKey())
            ->update(['is_active' => false]);

        LlmsEntry::where('model_type', $morphClass)
            ->where('model_id', $blogPost->getKey())
            ->update(['is_active' => false]);

        // Deactivate JSON-LD schemas — soft-deleted posts must not serve
        // structured data. The rows are kept for potential restore.
        JsonldSchema::where('model_type', $morphClass)
            ->where('model_id', $blogPost->getKey())
            ->update(['is_active' => false]);
    }

    public function restored(BlogPost $blogPost): void
    {
        if ($blogPost->status !== BlogPostStatus::Published) {
            return;
        }

        $blogPost->loadMissing('translations');
        $loadedLocales = $blogPost->translations->pluck('locale')->all();

        foreach (config('app.supported_locales') as $locale) {
            if (in_array($locale, $loadedLocales, true)) {
                dispatch(new SyncJsonldSchema($blogPost, $locale))->onQueue('seo');
                dispatch(new SyncSitemapEntry($blogPost, $locale))->onQueue('seo');
                dispatch(new SyncLlmsEntry($blogPost, $locale))->onQueue('seo');
            }
        }
    }

    /**
     * Force delete: remove all polymorphic SEO records from DB.
     * Runs BEFORE the SQL DELETE so model_id is still resolvable.
     */
    public function forceDeleting(BlogPost $blogPost): void
    {
        $morphClass = $blogPost->getMorphClass();

        SeoMeta::where('model_type', $morphClass)->where('model_id', $blogPost->getKey())->delete();
        GeoEntityProfile::where('model_type', $morphClass)->where('model_id', $blogPost->getKey())->delete();
        JsonldSchema::where('model_type', $morphClass)->where('model_id', $blogPost->getKey())->delete();
        SitemapEntry::where('model_type', $morphClass)->where('model_id', $blogPost->getKey())->delete();
        LlmsEntry::where('model_type', $morphClass)->where('model_id', $blogPost->getKey())->delete();
    }
}
