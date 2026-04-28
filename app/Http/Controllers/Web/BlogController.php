<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Enums\BlogPostStatus;
use App\Models\BlogCategoryTranslation;
use App\Models\BlogPostTranslation;
use App\Services\Seo\JsonldService;
use App\Services\Seo\SeoService;
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
                    route('blog.category', ['locale' => config('app.fallback_locale'), 'slug' => $viTranslation->slug]),
                    302
                );
            }

            abort(404);
        }

        $blogCategory = $translation->blogCategory;
        if (! $blogCategory || ! $blogCategory->is_active) {
            abort(404);
        }

        $alternateUrls = app(SeoService::class)->alternateUrls($blogCategory, 'blog.category');
        $seoMeta       = $blogCategory->seoMeta($locale);
        $jsonldSchemas = [
            app(JsonldService::class)->buildBreadcrumb([
                ['name' => __('common.home', [], $locale), 'url' => route('home', ['locale' => $locale])],
                ['name' => __('common.blog', [], $locale), 'url' => route('blog.index', ['locale' => $locale])],
                ['name' => $translation->name, 'url' => url()->current()],
            ]),
        ];

        return view('pages.blog.category', compact(
            'blogCategory', 'translation', 'alternateUrls', 'seoMeta', 'jsonldSchemas', 'locale'
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
                    route('blog.show', ['locale' => config('app.fallback_locale'), 'slug' => $viTranslation->slug]),
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

        $alternateUrls = app(SeoService::class)->alternateUrls($post, 'blog.show');
        $seoMeta       = $post->seoMeta($locale);
        $jsonldSchemas = [
            app(JsonldService::class)->buildBreadcrumb([
                ['name' => __('common.home', [], $locale), 'url' => route('home', ['locale' => $locale])],
                ['name' => __('common.blog', [], $locale), 'url' => route('blog.index', ['locale' => $locale])],
                ['name' => $translation->title, 'url' => url()->current()],
            ]),
        ];

        return view('pages.blog.show', compact(
            'post', 'translation', 'alternateUrls', 'seoMeta', 'jsonldSchemas', 'locale'
        ));
    }
}
