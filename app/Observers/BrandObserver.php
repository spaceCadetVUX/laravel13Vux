<?php

namespace App\Observers;

use App\Enums\RedirectType;
use App\Jobs\Seo\SyncJsonldSchema;
use App\Jobs\Seo\SyncLlmsEntry;
use App\Jobs\Seo\SyncSitemapEntry;
use App\Models\Brand;
use App\Models\Seo\GeoEntityProfile;
use App\Models\Seo\JsonldSchema;
use App\Models\Seo\LlmsEntry;
use App\Models\Seo\Redirect;
use App\Models\Seo\SeoMeta;
use App\Models\Seo\SitemapEntry;
use App\Services\Catalog\BrandService;
use App\Support\LocaleUrl;

class BrandObserver
{
    /**
     * Fires BEFORE the UPDATE SQL — old slug still in getOriginal().
     * Brand slug is shared across locales → create redirects for all supported locales.
     */
    public function updating(Brand $brand): void
    {
        if (! $brand->isDirty('slug')) {
            return;
        }

        $oldSlug = $brand->getOriginal('slug');
        $newSlug = $brand->slug;

        if (! $oldSlug || ! $newSlug || $oldSlug === $newSlug) {
            return;
        }

        foreach (config('app.supported_locales', ['vi', 'en']) as $locale) {
            Redirect::updateOrCreate(
                ['from_path' => parse_url(LocaleUrl::for('brand', $oldSlug, $locale), PHP_URL_PATH)],
                [
                    'to_path'   => parse_url(LocaleUrl::for('brand', $newSlug, $locale), PHP_URL_PATH),
                    'type'      => RedirectType::Permanent,
                    'locale'    => $locale,
                    'is_active' => true,
                ]
            );
        }
    }

    public function saved(Brand $brand): void
    {
        app(BrandService::class)->bustListCache();

        if (! $brand->is_active) {
            $morphClass = $brand->getMorphClass();

            SitemapEntry::where('model_type', $morphClass)
                ->where('model_id', $brand->getKey())
                ->update(['is_active' => false]);

            LlmsEntry::where('model_type', $morphClass)
                ->where('model_id', $brand->getKey())
                ->update(['is_active' => false]);

            JsonldSchema::where('model_type', $morphClass)
                ->where('model_id', $brand->getKey())
                ->update(['is_active' => false]);

            return;
        }

        foreach (config('app.supported_locales', ['vi', 'en']) as $locale) {
            dispatch(new SyncJsonldSchema($brand, $locale))->onQueue('seo');
            dispatch(new SyncSitemapEntry($brand, $locale))->onQueue('seo');
            dispatch(new SyncLlmsEntry($brand, $locale))->onQueue('seo');
        }
    }

    /**
     * Brand has no SoftDeletes — this is a hard delete.
     * Clean up all polymorphic SEO rows from DB.
     */
    public function deleted(Brand $brand): void
    {
        $morphClass = $brand->getMorphClass();
        $modelId    = $brand->getKey();

        SeoMeta::where('model_type', $morphClass)->where('model_id', $modelId)->delete();
        GeoEntityProfile::where('model_type', $morphClass)->where('model_id', $modelId)->delete();
        JsonldSchema::where('model_type', $morphClass)->where('model_id', $modelId)->delete();
        SitemapEntry::where('model_type', $morphClass)->where('model_id', $modelId)->delete();
        LlmsEntry::where('model_type', $morphClass)->where('model_id', $modelId)->delete();

        app(BrandService::class)->bustListCache();
    }
}
