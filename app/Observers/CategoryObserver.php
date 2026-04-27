<?php

namespace App\Observers;

use App\Jobs\Seo\SyncJsonldSchema;
use App\Jobs\Seo\SyncLlmsEntry;
use App\Jobs\Seo\SyncSitemapEntry;
use App\Models\Category;
use App\Models\Seo\LlmsEntry;
use App\Models\Seo\SitemapEntry;
use App\Services\Category\CategoryService;

class CategoryObserver
{
    public function saved(Category $category): void
    {
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
     * Soft-delete deactivates SEO entries — never removes them.
     */
    public function deleted(Category $category): void
    {
        $morphClass = $category->getMorphClass(); // 'category' via morphMap

        SitemapEntry::where('model_type', $morphClass)
            ->where('model_id', $category->getKey())
            ->update(['is_active' => false]);

        LlmsEntry::where('model_type', $morphClass)
            ->where('model_id', $category->getKey())
            ->update(['is_active' => false]);

        app(CategoryService::class)->bustTreeCache();
    }

    public function restored(Category $category): void
    {
        foreach (config('app.supported_locales') as $locale) {
            if ($category->translations()->where('locale', $locale)->exists()) {
                dispatch(new SyncJsonldSchema($category, $locale))->onQueue('seo');
                dispatch(new SyncSitemapEntry($category, $locale))->onQueue('seo');
                dispatch(new SyncLlmsEntry($category, $locale))->onQueue('seo');
            }
        }

        app(CategoryService::class)->bustTreeCache();
    }
}
