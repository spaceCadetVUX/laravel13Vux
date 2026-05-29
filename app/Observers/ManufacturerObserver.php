<?php

namespace App\Observers;

use App\Jobs\Seo\SyncJsonldSchema;
use App\Jobs\Seo\SyncLlmsEntry;
use App\Jobs\Seo\SyncSitemapEntry;
use App\Models\Manufacturer;
use App\Services\Catalog\ManufacturerService;
use App\Models\Seo\GeoEntityProfile;
use App\Models\Seo\JsonldSchema;
use App\Models\Seo\LlmsEntry;
use App\Models\Seo\SeoMeta;
use App\Models\Seo\SitemapEntry;

class ManufacturerObserver
{
    public function saved(Manufacturer $manufacturer): void
    {
        app(ManufacturerService::class)->bustListCache();

        if (! $manufacturer->is_active) {
            $morphClass = $manufacturer->getMorphClass();

            SitemapEntry::where('model_type', $morphClass)
                ->where('model_id', $manufacturer->getKey())
                ->update(['is_active' => false]);

            LlmsEntry::where('model_type', $morphClass)
                ->where('model_id', $manufacturer->getKey())
                ->update(['is_active' => false]);

            JsonldSchema::where('model_type', $morphClass)
                ->where('model_id', $manufacturer->getKey())
                ->update(['is_active' => false]);

            return;
        }

        foreach (config('app.supported_locales', ['vi', 'en']) as $locale) {
            dispatch(new SyncJsonldSchema($manufacturer, $locale))->onQueue('seo');
            dispatch(new SyncSitemapEntry($manufacturer, $locale))->onQueue('seo');
            dispatch(new SyncLlmsEntry($manufacturer, $locale))->onQueue('seo');
        }
    }

    /**
     * Manufacturer has no SoftDeletes — this is a hard delete.
     * Clean up all polymorphic SEO rows from DB.
     */
    public function deleted(Manufacturer $manufacturer): void
    {
        $morphClass = $manufacturer->getMorphClass();
        $modelId    = $manufacturer->getKey();

        SeoMeta::where('model_type', $morphClass)->where('model_id', $modelId)->delete();
        GeoEntityProfile::where('model_type', $morphClass)->where('model_id', $modelId)->delete();
        JsonldSchema::where('model_type', $morphClass)->where('model_id', $modelId)->delete();
        SitemapEntry::where('model_type', $morphClass)->where('model_id', $modelId)->delete();
        LlmsEntry::where('model_type', $morphClass)->where('model_id', $modelId)->delete();

        app(ManufacturerService::class)->bustListCache();
    }
}
