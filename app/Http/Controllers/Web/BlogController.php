<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Enums\BlogPostStatus;
use App\Models\BlogCategoryTranslation;
use App\Models\BlogPostTranslation;
use App\Services\Seo\JsonldService;
use App\Services\Seo\SeoService;
use App\Support\LocaleUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class BlogController extends Controller
{
    public function index(string $locale): Response
    {
        return response("Blog index — {$locale}", 200);
    }

    public function category(string $locale, string $slug): View|RedirectResponse
    {
        $translation = BlogCategoryTranslation::where('locale', $locale)
            ->where('slug', $slug)
            ->with('blogCategory')
            ->first();

        if (! $translation) {
            $viTranslation = BlogCategoryTranslation::where('locale', config('app.fallback_locale'))
                ->where('slug', $slug)
                ->first();

            if ($viTranslation) {
                return redirect(
                    LocaleUrl::for('blog_category', $viTranslation->slug, config('app.fallback_locale')),
                    302
                );
            }

            abort(404);
        }

        $blogCategory = $translation->blogCategory;
        if (! $blogCategory || ! $blogCategory->is_active) {
            abort(404);
        }

        $alternateUrls       = app(SeoService::class)->alternateUrls($blogCategory);
        $seoMeta             = $blogCategory->seoMeta($locale);
        $jsonldSchemas       = app(JsonldService::class)->getActiveSchemas($blogCategory, $locale)
            ->pluck('payload')
            ->toArray();
        $fallbackTitle       = $translation->name;
        $fallbackDescription = $translation->description ?? '';
        $fallbackImage       = null;
        $ogType              = 'website';

        return view('pages.blog.category', compact(
            'blogCategory', 'translation', 'alternateUrls', 'seoMeta', 'jsonldSchemas', 'locale',
            'fallbackTitle', 'fallbackDescription', 'fallbackImage', 'ogType'
        ));
    }

    public function show(string $locale, string $slug): View|RedirectResponse
    {
        $translation = BlogPostTranslation::where('locale', $locale)
            ->where('slug', $slug)
            ->with('blogPost')
            ->first();

        if (! $translation) {
            $viTranslation = BlogPostTranslation::where('locale', config('app.fallback_locale'))
                ->where('slug', $slug)
                ->first();

            if ($viTranslation) {
                return redirect(
                    LocaleUrl::for('blog_post', $viTranslation->slug, config('app.fallback_locale')),
                    302
                );
            }

            abort(404);
        }

        $post = $translation->blogPost;
        if (! $post
            || $post->status !== BlogPostStatus::Published
            || ! $post->published_at
            || $post->published_at->gt(now())) {
            abort(404);
        }

        $alternateUrls       = app(SeoService::class)->alternateUrls($post);
        $seoMeta             = $post->seoMeta($locale);
        $jsonldSchemas       = app(JsonldService::class)->getActiveSchemas($post, $locale)
            ->pluck('payload')
            ->toArray();
        $fallbackTitle       = $translation->title;
        $fallbackDescription = $translation->excerpt ?? '';
        $fallbackImage       = $post->featured_image
            ? url('storage/' . ltrim($post->featured_image, '/'))
            : null;
        $ogType              = 'article';

        return view('pages.blog.show', compact(
            'post', 'translation', 'alternateUrls', 'seoMeta', 'jsonldSchemas', 'locale',
            'fallbackTitle', 'fallbackDescription', 'fallbackImage', 'ogType'
        ));
    }
}
