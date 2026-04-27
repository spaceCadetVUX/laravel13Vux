<?php

namespace App\Observers;

use App\Enums\RedirectType;
use App\Jobs\Seo\SyncJsonldSchema;
use App\Jobs\Seo\SyncLlmsEntry;
use App\Jobs\Seo\SyncSitemapEntry;
use App\Models\BlogCategoryTranslation;
use App\Models\Seo\Redirect;

class BlogCategoryTranslationObserver
{
    public function saved(BlogCategoryTranslation $translation): void
    {
        $blogCategory = $translation->blogCategory;
        $locale       = $translation->locale;

        dispatch(new SyncJsonldSchema($blogCategory, $locale))->onQueue('seo');
        dispatch(new SyncSitemapEntry($blogCategory, $locale))->onQueue('seo');
        dispatch(new SyncLlmsEntry($blogCategory, $locale))->onQueue('seo');
    }

    public function updating(BlogCategoryTranslation $translation): void
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

        Redirect::updateOrCreate(
            ['from_path' => "/{$locale}/blog/categories/{$oldSlug}"],
            [
                'to_path'   => "/{$locale}/blog/categories/{$newSlug}",
                'type'      => RedirectType::Permanent,
                'locale'    => $locale,
                'is_active' => true,
            ]
        );
    }
}
