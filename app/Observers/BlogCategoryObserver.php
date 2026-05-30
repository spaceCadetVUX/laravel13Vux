<?php

namespace App\Observers;

use App\Enums\RedirectType;
use App\Jobs\Seo\SyncJsonldSchema;
use App\Jobs\Seo\SyncLlmsEntry;
use App\Jobs\Seo\SyncSitemapEntry;
use App\Models\BlogCategory;
use App\Models\Seo\GeoEntityProfile;
use App\Models\Seo\JsonldSchema;
use App\Models\Seo\LlmsEntry;
use App\Models\Seo\Redirect;
use App\Models\Seo\SeoMeta;
use App\Models\Seo\SitemapEntry;
use App\Support\LocaleUrl;

class BlogCategoryObserver
{
    /**
     * Fire BEFORE the UPDATE SQL — getOriginal() still holds the old slug.
     * Creates a 301 redirect when the slug changes on an active category.
     */
    public function updating(BlogCategory $blogCategory): void
    {
        if (! $blogCategory->isDirty('slug')) {
            return;
        }

        $oldSlug = $blogCategory->getOriginal('slug');
        $newSlug = $blogCategory->slug;

        if (! $oldSlug || ! $newSlug || $oldSlug === $newSlug) {
            return;
        }

        // Main slug = vi/default locale slug. Use LocaleUrl to get the correct vi path.
        $defaultLocale = config('app.fallback_locale', 'vi');

        Redirect::updateOrCreate(
            ['from_path' => parse_url(LocaleUrl::for('blog_category', $oldSlug, $defaultLocale), PHP_URL_PATH)],
            [
                'to_path'   => parse_url(LocaleUrl::for('blog_category', $newSlug, $defaultLocale), PHP_URL_PATH),
                'type'      => RedirectType::Permanent,
                'locale'    => $defaultLocale,
                'is_active' => true,
            ]
        );
    }

    /**
     * Sync JSON-LD, sitemap, and LLMs for active categories.
     * Deactivates all SEO entries when a category is inactive.
     */
    public function saved(BlogCategory $blogCategory): void
    {
        $morphClass = $blogCategory->getMorphClass(); // 'blog_category' via morphMap

        if (! $blogCategory->is_active) {
            SitemapEntry::where('model_type', $morphClass)
                ->where('model_id', $blogCategory->getKey())
                ->update(['is_active' => false]);

            LlmsEntry::where('model_type', $morphClass)
                ->where('model_id', $blogCategory->getKey())
                ->update(['is_active' => false]);

            JsonldSchema::where('model_type', $morphClass)
                ->where('model_id', $blogCategory->getKey())
                ->update(['is_active' => false]);

            return;
        }

        $blogCategory->loadMissing('translations');
        $loadedLocales = $blogCategory->translations->pluck('locale')->all();

        foreach (config('app.supported_locales') as $locale) {
            if (in_array($locale, $loadedLocales, true)) {
                dispatch(new SyncJsonldSchema($blogCategory, $locale))->onQueue('seo');
                dispatch(new SyncSitemapEntry($blogCategory, $locale))->onQueue('seo');
                dispatch(new SyncLlmsEntry($blogCategory, $locale))->onQueue('seo');
            }
        }
    }

    /**
     * Hard-delete all polymorphic SEO rows on delete.
     * BlogCategory has no SoftDeletes — deleted() fires once on the actual DB DELETE.
     */
    public function deleted(BlogCategory $blogCategory): void
    {
        $morphClass = $blogCategory->getMorphClass();
        $modelId    = $blogCategory->getKey();

        SeoMeta::where('model_type', $morphClass)->where('model_id', $modelId)->delete();
        GeoEntityProfile::where('model_type', $morphClass)->where('model_id', $modelId)->delete();
        JsonldSchema::where('model_type', $morphClass)->where('model_id', $modelId)->delete();
        SitemapEntry::where('model_type', $morphClass)->where('model_id', $modelId)->delete();
        LlmsEntry::where('model_type', $morphClass)->where('model_id', $modelId)->delete();
    }
}
