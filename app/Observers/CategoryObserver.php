<?php

namespace App\Observers;

use App\Jobs\Seo\SyncLlmsEntry;
use App\Jobs\Seo\SyncSitemapEntry;
use App\Models\Category;
use App\Models\Seo\LlmsEntry;
use App\Models\Seo\SitemapEntry;

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
    }
}
