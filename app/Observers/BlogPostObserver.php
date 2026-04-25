<?php

namespace App\Observers;

use App\Enums\BlogPostStatus;
use App\Jobs\Seo\SyncJsonldSchema;
use App\Jobs\Seo\SyncLlmsEntry;
use App\Jobs\Seo\SyncSitemapEntry;
use App\Models\BlogPost;
use App\Models\Seo\JsonldSchema;
use App\Models\Seo\LlmsEntry;
use App\Models\Seo\SitemapEntry;

class BlogPostObserver
{
    /**
     * Dispatch SEO sync jobs only when the post is published.
     * Draft and archived posts must not appear in sitemaps or LLMs docs.
     */
    public function saved(BlogPost $blogPost): void
    {
        if ($blogPost->status !== BlogPostStatus::Published) {
            // Post is no longer published — deactivate ALL SEO entries.
            $morphClass = $blogPost->getMorphClass();

            SitemapEntry::where('model_type', $morphClass)
                ->where('model_id', $blogPost->getKey())
                ->update(['is_active' => false]);

            LlmsEntry::where('model_type', $morphClass)
                ->where('model_id', $blogPost->getKey())
                ->update(['is_active' => false]);

            // Deactivate JSON-LD schemas so the API does not serve stale
            // structured data for archived / draft posts.
            JsonldSchema::where('model_type', $morphClass)
                ->where('model_id', $blogPost->getKey())
                ->update(['is_active' => false]);

            return;
        }

        dispatch(new SyncJsonldSchema($blogPost))->onQueue('seo');
        dispatch(new SyncSitemapEntry($blogPost))->onQueue('seo');
        dispatch(new SyncLlmsEntry($blogPost))->onQueue('seo');
    }

    /**
     * Soft-delete deactivates SEO entries — never removes them.
     * Also fires when a published post is soft-deleted.
     */
    public function deleted(BlogPost $blogPost): void
    {
        $morphClass = $blogPost->getMorphClass(); // 'blog_post' via morphMap

        SitemapEntry::where('model_type', $morphClass)
            ->where('model_id', $blogPost->getKey())
            ->update(['is_active' => false]);

        LlmsEntry::where('model_type', $morphClass)
            ->where('model_id', $blogPost->getKey())
            ->update(['is_active' => false]);

        // Deactivate JSON-LD schemas — soft-deleted posts must not serve
        // structured data. The rows are kept for potential restore.
        JsonldSchema::where('model_type', $morphClass)
            ->where('model_id', $blogPost->getKey())
            ->update(['is_active' => false]);
    }
}
