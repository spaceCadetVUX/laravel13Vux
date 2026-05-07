<?php

namespace App\Observers;

use App\Jobs\Seo\SyncJsonldSchema;
use App\Jobs\Seo\SyncLlmsEntry;
use App\Jobs\Seo\SyncSitemapEntry;
use App\Models\Category;
use App\Models\Seo\GeoEntityProfile;
use App\Models\Seo\JsonldSchema;
use App\Models\Seo\LlmsEntry;
use App\Models\Seo\SeoMeta;
use App\Models\Seo\SitemapEntry;
use App\Services\Category\CategoryService;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        $morphClass = $category->getMorphClass();

        if (! $category->is_active) {
            // Inactive category must not appear in sitemap, LLMs docs, or page <head>.
            SitemapEntry::where('model_type', $morphClass)
                ->where('model_id', $category->getKey())
                ->update(['is_active' => false]);

            LlmsEntry::where('model_type', $morphClass)
                ->where('model_id', $category->getKey())
                ->update(['is_active' => false]);

            JsonldSchema::where('model_type', $morphClass)
                ->where('model_id', $category->getKey())
                ->update(['is_active' => false]);

            app(CategoryService::class)->bustTreeCache();

            return;
        }

        foreach (config('app.supported_locales') as $locale) {
            if ($category->translations()->where('locale', $locale)->exists()) {
                dispatch(new SyncJsonldSchema($category, $locale))->onQueue('seo');
                dispatch(new SyncSitemapEntry($category, $locale))->onQueue('seo');
                dispatch(new SyncLlmsEntry($category, $locale))->onQueue('seo');
            }
        }

        app(CategoryService::class)->bustTreeCache();
    }

    /**
     * Soft-delete deactivates all SEO entries — rows are kept for potential restore.
     */
    public function deleted(Category $category): void
    {
        $morphClass = $category->getMorphClass();

        SitemapEntry::where('model_type', $morphClass)
            ->where('model_id', $category->getKey())
            ->update(['is_active' => false]);

        LlmsEntry::where('model_type', $morphClass)
            ->where('model_id', $category->getKey())
            ->update(['is_active' => false]);

        JsonldSchema::where('model_type', $morphClass)
            ->where('model_id', $category->getKey())
            ->update(['is_active' => false]);

        app(CategoryService::class)->bustTreeCache();
    }

    public function restored(Category $category): void
    {
        if (! $category->is_active) {
            return;
        }

        foreach (config('app.supported_locales') as $locale) {
            if ($category->translations()->where('locale', $locale)->exists()) {
                dispatch(new SyncJsonldSchema($category, $locale))->onQueue('seo');
                dispatch(new SyncSitemapEntry($category, $locale))->onQueue('seo');
                dispatch(new SyncLlmsEntry($category, $locale))->onQueue('seo');
            }
        }

        app(CategoryService::class)->bustTreeCache();
    }

    /**
     * Force delete: remove all polymorphic SEO rows from DB.
     * Runs BEFORE the SQL DELETE so model_id is still resolvable.
     */
    public function forceDeleting(Category $category): void
    {
        $morphClass = $category->getMorphClass();
        $modelId    = $category->getKey();

        SeoMeta::where('model_type', $morphClass)->where('model_id', $modelId)->delete();
        GeoEntityProfile::where('model_type', $morphClass)->where('model_id', $modelId)->delete();
        JsonldSchema::where('model_type', $morphClass)->where('model_id', $modelId)->delete();
        SitemapEntry::where('model_type', $morphClass)->where('model_id', $modelId)->delete();
        LlmsEntry::where('model_type', $morphClass)->where('model_id', $modelId)->delete();

        app(CategoryService::class)->bustTreeCache();
    }
}
