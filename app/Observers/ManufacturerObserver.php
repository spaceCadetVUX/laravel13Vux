<?php

namespace App\Observers;

use App\Jobs\Seo\SyncJsonldSchema;
use App\Jobs\Seo\SyncLlmsEntry;
use App\Jobs\Seo\SyncSitemapEntry;
use App\Models\Manufacturer;
use App\Models\Seo\GeoEntityProfile;
use App\Models\Seo\JsonldSchema;
use App\Models\Seo\LlmsEntry;
use App\Models\Seo\SeoMeta;
use App\Models\Seo\SitemapEntry;

class ManufacturerObserver
{
    public function saved(Manufacturer $manufacturer): void
    {
        dispatch(new SyncJsonldSchema($manufacturer))->onQueue('seo');
        dispatch(new SyncSitemapEntry($manufacturer))->onQueue('seo');
        dispatch(new SyncLlmsEntry($manufacturer))->onQueue('seo');
    }

    public function deleted(Manufacturer $manufacturer): void
    {
        $morphClass = $manufacturer->getMorphClass();

        SitemapEntry::where('model_type', $morphClass)
            ->where('model_id', $manufacturer->getKey())
            ->update(['is_active' => false]);

        LlmsEntry::where('model_type', $morphClass)
            ->where('model_id', $manufacturer->getKey())
            ->update(['is_active' => false]);
    }

    public function forceDeleting(Manufacturer $manufacturer): void
    {
        $morphClass = $manufacturer->getMorphClass();

        SeoMeta::where('model_type', $morphClass)->where('model_id', $manufacturer->getKey())->delete();
        GeoEntityProfile::where('model_type', $morphClass)->where('model_id', $manufacturer->getKey())->delete();
        JsonldSchema::where('model_type', $morphClass)->where('model_id', $manufacturer->getKey())->delete();
        SitemapEntry::where('model_type', $morphClass)->where('model_id', $manufacturer->getKey())->delete();
        LlmsEntry::where('model_type', $morphClass)->where('model_id', $manufacturer->getKey())->delete();
    }
}
