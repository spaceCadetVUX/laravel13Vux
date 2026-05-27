<?php

namespace App\Observers;

use App\Enums\BlogPostStatus;
use App\Jobs\Seo\SyncJsonldSchema;
use App\Models\Author;

class AuthorObserver
{
    /**
     * Re-sync Article JSON-LD for every published post by this author.
     * Only JSON-LD is affected — sitemap/llms don't include author data.
     */
    public function saved(Author $author): void
    {
        $author->blogPosts()
            ->where('status', BlogPostStatus::Published)
            ->with('translations')
            ->chunkById(50, function ($posts): void {
                foreach ($posts as $post) {
                    $loadedLocales = $post->translations->pluck('locale')->all();

                    foreach (config('app.supported_locales') as $locale) {
                        if (in_array($locale, $loadedLocales, true)) {
                            dispatch(new SyncJsonldSchema($post, $locale))->onQueue('seo');
                        }
                    }
                }
            });
    }
}
