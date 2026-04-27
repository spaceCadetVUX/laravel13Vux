<?php

namespace App\Observers;

use App\Enums\RedirectType;
use App\Jobs\Seo\SyncJsonldSchema;
use App\Jobs\Seo\SyncLlmsEntry;
use App\Jobs\Seo\SyncSitemapEntry;
use App\Models\CategoryTranslation;
use App\Models\Seo\Redirect;

class CategoryTranslationObserver
{
    public function saved(CategoryTranslation $translation): void
    {
        $category = $translation->category;
        $locale   = $translation->locale;

        dispatch(new SyncJsonldSchema($category, $locale))->onQueue('seo');
        dispatch(new SyncSitemapEntry($category, $locale))->onQueue('seo');
        dispatch(new SyncLlmsEntry($category, $locale))->onQueue('seo');
    }

    public function updating(CategoryTranslation $translation): void
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
            ['from_path' => "/{$locale}/categories/{$oldSlug}"],
            [
                'to_path'   => "/{$locale}/categories/{$newSlug}",
                'type'      => RedirectType::Permanent,
                'locale'    => $locale,
                'is_active' => true,
            ]
        );
    }
}
