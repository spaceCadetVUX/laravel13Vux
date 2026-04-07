<?php

namespace App\Observers;

use App\Models\Category;

/**
 * Stub — filled in S33.
 * Dispatches SyncJsonldSchema, SyncSitemapEntry, SyncLlmsEntry on save/delete.
 */
class CategoryObserver
{
    public function saved(Category $category): void
    {
        // TODO S33: dispatch SEO sync jobs on 'seo' queue
    }

    public function deleted(Category $category): void
    {
        // TODO S33: mark sitemap entry / llms entry inactive
    }
}
