<?php

namespace App\Observers;

use App\Jobs\Seo\GenerateBusinessDocument;
use App\Models\BusinessProfile;
use App\Services\Seo\BusinessJsonldService;

class BusinessProfileObserver
{
    public function saved(BusinessProfile $profile): void
    {
        app(BusinessJsonldService::class)->flushCache();
        dispatch(new GenerateBusinessDocument())->onQueue('seo');
    }
}
