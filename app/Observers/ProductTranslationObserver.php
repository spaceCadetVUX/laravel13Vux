<?php

namespace App\Observers;

use App\Enums\RedirectType;
use App\Jobs\Seo\SyncJsonldSchema;
use App\Jobs\Seo\SyncLlmsEntry;
use App\Jobs\Seo\SyncSitemapEntry;
use App\Models\ProductTranslation;
use App\Models\Seo\Redirect;

class ProductTranslationObserver
{
    public function saved(ProductTranslation $translation): void
    {
        $product = $translation->product;
        $locale  = $translation->locale;

        dispatch(new SyncJsonldSchema($product, $locale))->onQueue('seo');
        dispatch(new SyncSitemapEntry($product, $locale))->onQueue('seo');
        dispatch(new SyncLlmsEntry($product, $locale))->onQueue('seo');
    }

    public function updating(ProductTranslation $translation): void
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
            ['from_path' => "/{$locale}/products/{$oldSlug}"],
            [
                'to_path'     => "/{$locale}/products/{$newSlug}",
                'type'        => RedirectType::Permanent,
                'locale'      => $locale,
                'is_active'   => true,
            ]
        );
    }
}
