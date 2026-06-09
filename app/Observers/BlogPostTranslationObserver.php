<?php

namespace App\Observers;

use App\Enums\RedirectType;
use App\Jobs\Seo\SyncJsonldSchema;
use App\Jobs\Seo\SyncLlmsEntry;
use App\Jobs\Seo\SyncSitemapEntry;
use App\Models\BlogPostTranslation;
use App\Models\Seo\Redirect;
use App\Support\LocaleUrl;

class BlogPostTranslationObserver
{
    public function saved(BlogPostTranslation $translation): void
    {
        $blogPost = $translation->blogPost;
        $locale   = $translation->locale;

        dispatch(new SyncJsonldSchema($blogPost, $locale))->onQueue('seo');
        dispatch(new SyncSitemapEntry($blogPost, $locale))->onQueue('seo');
        dispatch(new SyncLlmsEntry($blogPost, $locale))->onQueue('seo');
    }

    public function updating(BlogPostTranslation $translation): void
    {
        if (! $translation->isDirty('slug')) {
            return;
        }

        $oldSlug = $translation->getOriginal('slug');
        $newSlug = $translation->slug;
        $locale  = $translation->locale;

        if (! $oldSlug || ! $newSlug || $oldSlug === $newSlug) {
            return;
        }

        $blogPost = $translation->blogPost->load(['blogCategory.translations']);
        $catTrans = $blogPost->blogCategory?->translations->where('locale', $locale)->first();
        $catSlug  = $catTrans?->slug ?? $blogPost->blogCategory?->slug;

        if ($catSlug) {
            $localePath = $locale === 'vi' ? 'vi/bai-viet' : 'en/blog';
            $fromPath   = "/{$localePath}/{$catSlug}/{$oldSlug}";
            $toPath     = "/{$localePath}/{$catSlug}/{$newSlug}";
        } else {
            $fromPath = parse_url(LocaleUrl::for('blog_post', $oldSlug, $locale), PHP_URL_PATH);
            $toPath   = parse_url(LocaleUrl::for('blog_post', $newSlug, $locale), PHP_URL_PATH);
        }

        Redirect::updateOrCreate(
            ['from_path' => $fromPath],
            [
                'to_path'   => $toPath,
                'type'      => RedirectType::Permanent,
                'locale'    => $locale,
                'is_active' => true,
            ]
        );
    }
}
