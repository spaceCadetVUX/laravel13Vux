<?php

namespace App\Observers;

use App\Jobs\Seo\SyncJsonldSchema;
use App\Jobs\Seo\SyncLlmsEntry;
use App\Jobs\Seo\SyncSitemapEntry;
use App\Models\Product;
use App\Models\Seo\GeoEntityProfile;
use App\Models\Seo\JsonldSchema;
use App\Models\Seo\LlmsEntry;
use App\Models\Seo\SeoMeta;
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
     * Soft-delete: deactivate SEO entries so they stop appearing in sitemap/llms.
     * Files and child records are intentionally kept — product may be restored.
     */
    public function deleted(Product $product): void
    {
        $morphClass = $product->getMorphClass();

        SitemapEntry::where('model_type', $morphClass)
            ->where('model_id', $product->getKey())
            ->update(['is_active' => false]);

        LlmsEntry::where('model_type', $morphClass)
            ->where('model_id', $product->getKey())
            ->update(['is_active' => false]);
    }

    /**
     * Runs BEFORE the SQL DELETE — must happen before DB CASCADE wipes
     * product_images / product_videos, otherwise images() returns empty
     * and physical files are never removed from storage.
     */
    public function forceDeleting(Product $product): void
    {
        $morphClass = $product->getMorphClass();

        // Delete images via Eloquent → triggers ProductImage::booted() → deletes files
        $product->images()->each(fn ($image) => $image->delete());

        // Delete videos via Eloquent → triggers ProductVideo::booted() → deletes files + thumbnails
        $product->videos()->each(fn ($video) => $video->delete());

        // Remove all polymorphic SEO records
        SeoMeta::where('model_type', $morphClass)->where('model_id', $product->getKey())->delete();
        GeoEntityProfile::where('model_type', $morphClass)->where('model_id', $product->getKey())->delete();
        JsonldSchema::where('model_type', $morphClass)->where('model_id', $product->getKey())->delete();
        SitemapEntry::where('model_type', $morphClass)->where('model_id', $product->getKey())->delete();
        LlmsEntry::where('model_type', $morphClass)->where('model_id', $product->getKey())->delete();
    }
}
