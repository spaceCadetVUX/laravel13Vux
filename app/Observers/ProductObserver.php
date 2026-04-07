<?php

namespace App\Observers;

use App\Models\Product;

/**
 * Stub — filled in S33.
 * Dispatches SyncJsonldSchema, SyncSitemapEntry, SyncLlmsEntry on save/delete.
 */
class ProductObserver
{
    public function saved(Product $product): void
    {
        // TODO S33: dispatch SEO sync jobs on 'seo' queue
    }

    public function deleted(Product $product): void
    {
        // TODO S33: mark sitemap entry / llms entry inactive
    }
}
