<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Enums\BlogPostStatus;
use App\Models\Author;
use App\Models\BlogPostTranslation;
use Illuminate\Contracts\View\View;

class AuthorController extends Controller
{
    public function show(string $locale, string $slug): View
    {
        $author = Author::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $posts = BlogPostTranslation::where('locale', $locale)
            ->join('blog_posts', 'blog_posts.id', '=', 'blog_post_translations.blog_post_id')
            ->where('blog_posts.author_id', $author->id)
            ->where('blog_posts.status', BlogPostStatus::Published)
            ->where('blog_posts.published_at', '<=', now())
            ->whereNull('blog_posts.deleted_at')
            ->select('blog_post_translations.*', 'blog_posts.published_at', 'blog_posts.featured_image')
            ->with([
                'blogPost.blogCategory.translations' => fn ($q) => $q->where('locale', $locale),
            ])
            ->orderByDesc('blog_posts.published_at')
            ->paginate(12);

        view()->share('alternateUrls', [
            'vi' => route('vi.author.show', $author->slug),
            'en' => route('en.author.show', $author->slug),
        ]);

        $baseUrl    = rtrim((string) config('app.url'), '/');
        $authorUrl  = $baseUrl . ($locale === 'vi' ? '/vi/tac-gia/' : '/en/authors/') . $author->slug;

        $personSchema = [
            '@context' => 'https://schema.org',
            '@type'    => 'Person',
            '@id'      => $baseUrl . '/authors/' . $author->slug . '#person',
            'name'     => $author->name,
            'url'      => $authorUrl,
        ];

        if (filled($author->title))    $personSchema['jobTitle']    = $author->title;
        if (filled($author->bio))      $personSchema['description'] = $author->bio;
        if ($avatarUrl = $author->avatar_url) $personSchema['image'] = $avatarUrl;

        $sameAs = $author->same_as;
        if (! empty($sameAs)) {
            $personSchema['sameAs'] = count($sameAs) === 1 ? $sameAs[0] : $sameAs;
        }

        $expertise = array_values(array_filter((array) ($author->expertise ?? [])));
        if (! empty($expertise)) {
            $personSchema['knowsAbout'] = $expertise;
        }

        $fallbackTitle = $locale === 'vi'
            ? 'Tác giả: ' . $author->name
            : 'Author: ' . $author->name;
        $fallbackDescription = $author->bio
            ? \Str::limit(strip_tags($author->bio), 160)
            : $fallbackTitle;

        return view('pages.blog.author', compact(
            'locale', 'author', 'posts', 'personSchema',
            'fallbackTitle', 'fallbackDescription'
        ) + [
            'seoMeta'      => null,
            'fallbackImage' => $author->avatar_url,
            'ogType'       => 'profile',
            'jsonldSchemas' => [],
        ]);
    }
}
