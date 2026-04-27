<?php

namespace App\Observers;

use App\Enums\RedirectType;
use App\Jobs\Seo\SyncJsonldSchema;
use App\Jobs\Seo\SyncLlmsEntry;
use App\Jobs\Seo\SyncSitemapEntry;
use App\Models\Product;
use App\Models\Seo\GeoEntityProfile;
use App\Models\Seo\JsonldSchema;
use App\Models\Seo\LlmsEntry;
use App\Models\Seo\Redirect;
use App\Models\Seo\SeoMeta;
use App\Models\Seo\SitemapEntry;

class ProductObserver
{
    /**
     * Fires BEFORE the UPDATE SQL — slug is still the old value here.
     * Creates a 301 redirect from old slug → new slug so indexed URLs never 404.
     */
    public function updating(Product $product): void
    {
        if (! $product->isDirty('slug')) {
            return;
        }

        $oldSlug = $product->getOriginal('slug');
        $newSlug = $product->slug;

        // Guard: both must be non-empty strings and actually different
        if (! $oldSlug || ! $newSlug || $oldSlug === $newSlug) {
            return;
        }

        Redirect::updateOrCreate(
            ['from_path' => '/products/' . $oldSlug],
            [
                'to_path'   => '/products/' . $newSlug,
                'type'      => RedirectType::Permanent,
                'is_active' => true,
            ]
        );
    }

    /**
     * Dispatch SEO sync jobs per locale on every create or update.
     * Only dispatches for locales that have an existing translation.
     */
    public function saved(Product $product): void
    {
        foreach (config('app.supported_locales') as $locale) {
            if ($product->translations()->where('locale', $locale)->exists()) {
                dispatch(new SyncJsonldSchema($product, $locale))->onQueue('seo');
                dispatch(new SyncSitemapEntry($product, $locale))->onQueue('seo');
                dispatch(new SyncLlmsEntry($product, $locale))->onQueue('seo');
            }
        }
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

        JsonldSchema::where('model_type', $morphClass)
            ->where('model_id', $product->getKey())
            ->update(['is_active' => false]);
    }

    public function restored(Product $product): void
    {
        foreach (config('app.supported_locales') as $locale) {
            if ($product->translations()->where('locale', $locale)->exists()) {
                dispatch(new SyncJsonldSchema($product, $locale))->onQueue('seo');
                dispatch(new SyncSitemapEntry($product, $locale))->onQueue('seo');
                dispatch(new SyncLlmsEntry($product, $locale))->onQueue('seo');
            }
        }
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
