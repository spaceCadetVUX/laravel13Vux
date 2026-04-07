<?php

namespace App\Observers;

use App\Models\BlogPost;

/**
 * Stub — filled in S34.
 * Dispatches SyncJsonldSchema, SyncSitemapEntry, SyncLlmsEntry on save/delete.
 */
class BlogPostObserver
{
    public function saved(BlogPost $blogPost): void
    {
        // TODO S34: dispatch SEO sync jobs on 'seo' queue
    }

    public function deleted(BlogPost $blogPost): void
    {
        // TODO S34: mark sitemap entry / llms entry inactive
    }
}
