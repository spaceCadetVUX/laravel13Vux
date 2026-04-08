<?php

namespace App\Observers;

use App\Jobs\Seo\SyncJsonldSchema;
use App\Jobs\Seo\SyncLlmsEntry;
use App\Jobs\Seo\SyncSitemapEntry;
use App\Models\Product;
use App\Models\Seo\LlmsEntry;
use App\Models\Seo\SitemapEntry;

class ProductObserver
{
    /**
     * Dispatch SEO sync jobs on every create or update.
     * Fires after both created() and updated() — covers all writes.
     */
    public function saved(Product $product): void
    {
        dispatch(new SyncJsonldSchema($product))->onQueue('seo');
        dispatch(new SyncSitemapEntry($product))->onQueue('seo');
        dispatch(new SyncLlmsEntry($product))->onQueue('seo');
    }

    /**
     * Soft-delete deactivates SEO entries — never removes them.
     * fired on soft-delete because Product uses SoftDeletes.
     */
    public function deleted(Product $product): void
    {
        $morphClass = $product->getMorphClass(); // 'product' via morphMap

        SitemapEntry::where('model_type', $morphClass)
            ->where('model_id', $product->getKey())
            ->update(['is_active' => false]);

        LlmsEntry::where('model_type', $morphClass)
            ->where('model_id', $product->getKey())
            ->update(['is_active' => false]);
    }
}
