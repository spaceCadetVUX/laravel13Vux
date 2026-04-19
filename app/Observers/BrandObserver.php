<?php

namespace App\Observers;

use App\Jobs\Seo\SyncJsonldSchema;
use App\Jobs\Seo\SyncLlmsEntry;
use App\Jobs\Seo\SyncSitemapEntry;
use App\Models\Brand;
use App\Models\Seo\GeoEntityProfile;
use App\Models\Seo\JsonldSchema;
use App\Models\Seo\LlmsEntry;
use App\Models\Seo\SeoMeta;
use App\Models\Seo\SitemapEntry;

class BrandObserver
{
    public function saved(Brand $brand): void
    {
        dispatch(new SyncJsonldSchema($brand))->onQueue('seo');
        dispatch(new SyncSitemapEntry($brand))->onQueue('seo');
        dispatch(new SyncLlmsEntry($brand))->onQueue('seo');
    }

    public function deleted(Brand $brand): void
    {
        $morphClass = $brand->getMorphClass();

        SitemapEntry::where('model_type', $morphClass)
            ->where('model_id', $brand->getKey())
            ->update(['is_active' => false]);

        LlmsEntry::where('model_type', $morphClass)
            ->where('model_id', $brand->getKey())
            ->update(['is_active' => false]);
    }

    public function forceDeleting(Brand $brand): void
    {
        $morphClass = $brand->getMorphClass();

        SeoMeta::where('model_type', $morphClass)->where('model_id', $brand->getKey())->delete();
        GeoEntityProfile::where('model_type', $morphClass)->where('model_id', $brand->getKey())->delete();
        JsonldSchema::where('model_type', $morphClass)->where('model_id', $brand->getKey())->delete();
        SitemapEntry::where('model_type', $morphClass)->where('model_id', $brand->getKey())->delete();
        LlmsEntry::where('model_type', $morphClass)->where('model_id', $brand->getKey())->delete();
    }
}
