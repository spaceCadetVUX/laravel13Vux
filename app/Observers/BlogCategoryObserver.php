<?php

namespace App\Observers;

use App\Enums\RedirectType;
use App\Jobs\Seo\SyncJsonldSchema;
use App\Jobs\Seo\SyncLlmsEntry;
use App\Jobs\Seo\SyncSitemapEntry;
use App\Models\BlogCategory;
use App\Models\Seo\JsonldSchema;
use App\Models\Seo\LlmsEntry;
use App\Models\Seo\Redirect;
use App\Models\Seo\SitemapEntry;

class BlogCategoryObserver
{
    /**
     * Fire BEFORE the UPDATE SQL — getOriginal() still holds the old slug.
     * Creates a 301 redirect when the slug changes on an active category.
     */
    public function updating(BlogCategory $blogCategory): void
    {
        if (! $blogCategory->isDirty('slug')) {
            return;
        }

        $oldSlug = $blogCategory->getOriginal('slug');
        $newSlug = $blogCategory->slug;

        if (! $oldSlug || ! $newSlug || $oldSlug === $newSlug) {
            return;
        }

        Redirect::updateOrCreate(
            ['from_path' => '/blog/category/' . $oldSlug],
            [
                'to_path'   => '/blog/category/' . $newSlug,
                'type'      => RedirectType::Permanent,
                'is_active' => true,
            ]
        );
    }

    /**
     * Sync JSON-LD, sitemap, and LLMs for active categories.
     * Deactivates all SEO entries when a category is inactive.
     */
    public function saved(BlogCategory $blogCategory): void
    {
        $morphClass = $blogCategory->getMorphClass(); // 'blog_category' via morphMap

        if (! $blogCategory->is_active) {
            SitemapEntry::where('model_type', $morphClass)
                ->where('model_id', $blogCategory->getKey())
                ->update(['is_active' => false]);

            LlmsEntry::where('model_type', $morphClass)
                ->where('model_id', $blogCategory->getKey())
                ->update(['is_active' => false]);

            JsonldSchema::where('model_type', $morphClass)
                ->where('model_id', $blogCategory->getKey())
                ->update(['is_active' => false]);

            return;
        }

        dispatch(new SyncJsonldSchema($blogCategory))->onQueue('seo');
        dispatch(new SyncSitemapEntry($blogCategory))->onQueue('seo');
        dispatch(new SyncLlmsEntry($blogCategory))->onQueue('seo');
    }

    /**
     * Deactivate all SEO entries when a category is deleted.
     * Rows are kept for potential restore — never hard-deleted.
     */
    public function deleted(BlogCategory $blogCategory): void
    {
        $morphClass = $blogCategory->getMorphClass();

        SitemapEntry::where('model_type', $morphClass)
            ->where('model_id', $blogCategory->getKey())
            ->update(['is_active' => false]);

        LlmsEntry::where('model_type', $morphClass)
            ->where('model_id', $blogCategory->getKey())
            ->update(['is_active' => false]);

        JsonldSchema::where('model_type', $morphClass)
            ->where('model_id', $blogCategory->getKey())
            ->update(['is_active' => false]);
    }
}
