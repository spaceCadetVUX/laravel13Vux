<?php

namespace App\Observers;

use App\Jobs\Seo\SyncLlmsEntry;
use App\Jobs\Seo\SyncSitemapEntry;
use App\Models\Category;
use App\Models\Seo\LlmsEntry;
use App\Models\Seo\SitemapEntry;
use App\Services\Category\CategoryService;

class CategoryObserver
{
    /**
     * Dispatch sitemap + llms sync on every create or update.
     * Note: No JSON-LD for categories at launch — only sitemap + llms.
     */
    public function saved(Category $category): void
    {
        dispatch(new SyncSitemapEntry($category))->onQueue('seo');
        dispatch(new SyncLlmsEntry($category))->onQueue('seo');

        // Bust the cached category tree so the API reflects the change immediately.
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

    /**
     * Restore: reactivate SEO entries and re-sync sitemap/llms.
     */
    public function restored(Category $category): void
    {
        dispatch(new SyncSitemapEntry($category))->onQueue('seo');
        dispatch(new SyncLlmsEntry($category))->onQueue('seo');

        app(CategoryService::class)->bustTreeCache();
    }
}
