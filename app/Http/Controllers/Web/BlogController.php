<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Enums\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogCategoryTranslation;
use App\Models\BlogPost;
use App\Models\BlogPostTranslation;
use App\Models\BlogTag;
use App\Models\Setting;
use App\Models\Seo\GeoEntityProfile;
use App\Services\Seo\JsonldService;
use App\Services\Seo\SeoService;
use App\Support\LocaleUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class BlogController extends Controller
{
    public function index(string $locale): View
    {
        $search         = request()->string('q')->toString() ?: null;
        $categoryFilter = array_filter((array) request('blog_category', []));

        // ── Category filter pills ──────────────────────────────────────────────
        $blogCategories = BlogCategory::active()
            ->whereNull('parent_id')
            ->with([
                'translations'          => fn ($q) => $q->where('locale', $locale),
                'children'              => fn ($q) => $q->active()
                    ->withCount(['posts as blog_count' => fn ($q) => $q->published()])
                    ->with(['translations' => fn ($q) => $q->where('locale', $locale)]),
            ])
            ->withCount(['posts as root_count' => fn ($q) => $q->published()])
            ->orderBy('sort_order')
            ->get()
            ->each(function ($cat) use ($locale) {
                $tr        = $cat->translations->first();
                $cat->name = $tr?->name ?? $cat->name;
                $cat->slug = $tr?->slug ?? $cat->slug;
                $cat->children->each(function ($child) use ($locale) {
                    $tr          = $child->translations->first();
                    $child->name = $tr?->name ?? $child->name;
                    $child->slug = $tr?->slug ?? $child->slug;
                });
                $cat->total_blog_count = $cat->root_count + $cat->children->sum('blog_count');
            });

        // ── Blog posts query ───────────────────────────────────────────────────
        $postsQuery = BlogPostTranslation::where('locale', $locale)
            ->join('blog_posts', 'blog_posts.id', '=', 'blog_post_translations.blog_post_id')
            ->where('blog_posts.status', BlogPostStatus::Published)
            ->where('blog_posts.published_at', '<=', now())
            ->whereNull('blog_posts.deleted_at')
            ->select('blog_post_translations.*')
            ->with([
                'blogPost' => fn ($q) => $q->with([
                    'blogCategory.translations' => fn ($q) => $q->where('locale', $locale),
                ]),
            ])
            ->orderByDesc('blog_posts.published_at');

        if ($search) {
            $postsQuery->where(fn ($q) => $q
                ->where('blog_post_translations.title', 'ilike', "%{$search}%")
                ->orWhere('blog_post_translations.excerpt', 'ilike', "%{$search}%")
            );
        }

        if ($categoryFilter) {
            $postsQuery->whereExists(fn ($q) => $q
                ->from('blog_category_translations')
                ->whereColumn('blog_category_translations.blog_category_id', 'blog_posts.blog_category_id')
                ->where('blog_category_translations.locale', $locale)
                ->whereIn('blog_category_translations.slug', $categoryFilter)
            );
        }

        $rawBlogs = $postsQuery->paginate(12)->withQueryString();

        $blogs = $rawBlogs->through(function ($tr) {
            $post                           = $tr->blogPost;
            $catTr                          = $post?->blogCategory?->translations->first();
            $rawImage                       = $post?->featured_image;
            $post->slug                     = $tr->slug;
            $post->title                    = $tr->title;
            $post->excerpt                  = $tr->excerpt;
            $post->category                 = $catTr?->name ?? $post?->blogCategory?->name;
            $post->category_slug            = $catTr?->slug ?? $post?->blogCategory?->slug;
            $post->featured_image           = $rawImage ? 'storage/' . ltrim($rawImage, '/') : null;
            $post->formatted_published_date = $post?->published_at?->translatedFormat('d M, Y');
            return $post;
        });

        // Resolve display name for the active category filter label
        $categoryName = null;
        if ($categoryFilter) {
            foreach ($blogCategories as $root) {
                if (in_array($root->slug, $categoryFilter)) {
                    $categoryName = $root->name;
                    break;
                }
                $matched = $root->children->first(fn ($c) => in_array($c->slug, $categoryFilter));
                if ($matched) {
                    $categoryName = $matched->name;
                    break;
                }
            }
        }

        view()->share('alternateUrls', [
            'vi' => route('vi.blog.index'),
            'en' => route('en.blog.index'),
        ]);

        return view('pages.blog.index', [
            'blogs'               => $blogs,
            'blogCategories'      => $blogCategories,
            'searchTerm'          => $search,
            'category'            => $categoryName,
            'locale'              => $locale,
            'seoMeta'             => null,
            'jsonldSchemas'       => [],
            'fallbackTitle'       => $locale === 'vi' ? 'Blog — Tin tức & Bài viết' : 'Blog — News & Articles',
            'fallbackDescription' => $locale === 'vi' ? 'Cập nhật kiến thức, xu hướng và câu chuyện từ chúng tôi.' : 'Insights, trends and stories from our team.',
            'fallbackImage'       => (($ogRaw = Setting::get('default_og_image')) && filled($ogRaw))
                                        ? (str_starts_with($ogRaw, 'http') ? $ogRaw : asset('storage/' . ltrim($ogRaw, '/')))
                                        : null,
            'ogType'              => 'website',
        ]);
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
        $fallbackImage       = (($ogRaw = Setting::get('default_og_image')) && filled($ogRaw))
                                    ? (str_starts_with($ogRaw, 'http') ? $ogRaw : asset('storage/' . ltrim($ogRaw, '/')))
                                    : null;
        $ogType              = 'website';

        // ── Subcategory pills ──────────────────────────────────────────────────
        $blogCategory->loadMissing([
            'children' => fn ($q) => $q->active()
                ->withCount(['posts as blog_count' => fn ($q) => $q->published()])
                ->with(['translations' => fn ($q) => $q->where('locale', $locale)])
                ->orderBy('sort_order'),
        ]);
        $blogCategory->children->each(function ($child) use ($locale) {
            $tr          = $child->translations->first();
            $child->name = $tr?->name ?? $child->name;
            $child->slug = $tr?->slug ?? $child->slug;
        });

        // ── Posts query (this category + direct children) ──────────────────────
        $categoryIds = collect([$blogCategory->id])
            ->merge($blogCategory->children->pluck('id'))
            ->unique();

        $rawPosts = BlogPostTranslation::where('blog_post_translations.locale', $locale)
            ->join('blog_posts', 'blog_posts.id', '=', 'blog_post_translations.blog_post_id')
            ->where('blog_posts.status', BlogPostStatus::Published)
            ->where('blog_posts.published_at', '<=', now())
            ->whereNull('blog_posts.deleted_at')
            ->whereIn('blog_posts.blog_category_id', $categoryIds)
            ->select('blog_post_translations.*')
            ->with([
                'blogPost' => fn ($q) => $q->with([
                    'blogCategory.translations' => fn ($q) => $q->where('locale', $locale),
                ]),
            ])
            ->orderByDesc('blog_posts.published_at')
            ->paginate(12)
            ->withQueryString();

        $blogs = $rawPosts->through(function ($tr) {
            $post                           = $tr->blogPost;
            $catTr                          = $post?->blogCategory?->translations->first();
            $rawImage                       = $post?->featured_image;
            $post->slug                     = $tr->slug;
            $post->title                    = $tr->title;
            $post->excerpt                  = $tr->excerpt;
            $post->category                 = $catTr?->name ?? $post?->blogCategory?->name;
            $post->category_slug            = $catTr?->slug ?? $post?->blogCategory?->slug;
            $post->featured_image           = $rawImage ? 'storage/' . ltrim($rawImage, '/') : null;
            $post->formatted_published_date = $post?->published_at?->translatedFormat('d M, Y');
            return $post;
        });

        $blogCategory->loadMissing('seoMetas');

        $geoProfile = GeoEntityProfile::where('model_type', 'blog_category')
            ->where('model_id', (string) $blogCategory->id)
            ->where('locale', $locale)
            ->first();
        $faqs = $geoProfile?->faq ?? [];

        view()->share('alternateUrls', $alternateUrls);

        return view('pages.blog.category', compact(
            'blogCategory', 'translation', 'blogs', 'alternateUrls', 'seoMeta', 'jsonldSchemas', 'locale',
            'fallbackTitle', 'fallbackDescription', 'fallbackImage', 'ogType', 'faqs'
        ) + ['noScrollSmoother' => true]);
    }

    public function show(string $locale, string $categorySlug, string $slug): View|RedirectResponse
    {
        $translation = BlogPostTranslation::where('locale', $locale)
            ->where('slug', $slug)
            ->with('blogPost')
            ->first();

        if (! $translation) {
            $viTranslation = BlogPostTranslation::where('slug', $slug)
                ->whereIn('locale', config('app.supported_locales'))
                ->where('locale', '!=', $locale)
                ->with(['blogPost.blogCategory.translations'])
                ->first();

            if ($viTranslation) {
                $post = $viTranslation->blogPost->load('blogCategory.translations');
                return redirect(LocaleUrl::forBlogPost($post, $viTranslation->locale), 302);
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

        $post->loadMissing([
            'author',
            'blogCategory.translations',
            'tags',
        ]);

        // Validate category slug — redirect to canonical if wrong
        $catTr              = $post->blogCategory?->translations->firstWhere('locale', $locale);
        $actualCategorySlug = $catTr?->slug ?? $post->blogCategory?->slug;

        if ($post->blog_category_id && $actualCategorySlug && $categorySlug !== $actualCategorySlug) {
            return redirect(LocaleUrl::forBlogPost($post, $locale), 301);
        }

        $alternateUrls = app(SeoService::class)->alternateUrls($post);
        $seoMeta       = $post->seoMeta($locale);
        $jsonldSchemas = app(JsonldService::class)->getActiveSchemas($post, $locale)
            ->pluck('payload')
            ->toArray();

        // ── GEO / FAQs ────────────────────────────────────────────────────────
        $geoProfile = GeoEntityProfile::where('model_type', 'blog_post')
            ->where('model_id', (string) $post->id)
            ->where('locale', $locale)
            ->first();
        $faqs = $geoProfile?->faq
            ?? ($locale === 'vi' ? ($post->faq_items_vi ?? []) : ($post->faq_items_en ?? []));

        // ── Blog DTO ───────────────────────────────────────────────────────────
        $catTr    = $post->blogCategory?->translations->firstWhere('locale', $locale);
        $rawImage = $post->featured_image;
        $rawBody  = $translation->body ?? '';

        // Convert Tiptap JSON → HTML if needed
        $bodyHtml = $rawBody;
        if (filled($rawBody)) {
            $decoded = json_decode($rawBody, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['type'])) {
                try {
                    $bodyHtml = (new \Tiptap\Editor([
                        'extensions' => [
                            new \Tiptap\Extensions\StarterKit,
                            new \Tiptap\Nodes\Image,
                            new \Tiptap\Nodes\Table,
                            new \Tiptap\Nodes\TableRow,
                            new \Tiptap\Nodes\TableHeader,
                            new \Tiptap\Nodes\TableCell,
                        ],
                    ]))->setContent($decoded)->getHTML();
                } catch (\Throwable) {
                    $bodyHtml = $rawBody;
                }
            }
        }

        $readMins = max(1, (int) ceil(str_word_count(strip_tags($bodyHtml)) / 200));

        $blog = (object) [
            'title'                    => $translation->title,
            'slug'                     => $translation->slug,
            'excerpt'                  => $translation->excerpt,
            'content'                  => $bodyHtml,
            'category'                 => $catTr?->name ?? $post->blogCategory?->name,
            'category_slug'            => $catTr?->slug ?? $post->blogCategory?->slug,
            'featured_image'           => $rawImage ? 'storage/' . ltrim($rawImage, '/') : null,
            'published_at'             => $post->published_at,
            'updated_at'               => $post->updated_at,
            'formatted_published_date' => $post->published_at?->translatedFormat('d M, Y'),
            'reading_time'             => $locale === 'vi' ? "{$readMins} phút đọc" : "{$readMins} min read",
            'author'                   => $post->author,
            'tags'                     => $post->tags->pluck('name')->all(),
            'faqs'                     => $faqs,
            'seo_description'          => $seoMeta?->meta_description ?? $translation->excerpt,
            'canonical_url'            => url()->current(),
        ];

        $fallbackTitle       = $translation->title;
        $fallbackDescription = $translation->excerpt ?? '';
        $fallbackImage       = $rawImage ? url('storage/' . ltrim($rawImage, '/')) : null;
        $ogType              = 'article';

        // ── Sidebar: categories ────────────────────────────────────────────────
        $categories = BlogCategory::active()
            ->with(['translations' => fn ($q) => $q->where('locale', $locale)])
            ->orderBy('sort_order')
            ->get()
            ->map(function ($cat) {
                $tr = $cat->translations->first();
                return (object) [
                    'name' => $tr?->name ?? $cat->name,
                    'slug' => $tr?->slug ?? $cat->slug,
                ];
            });

        // ── Sidebar: latest posts (excl. current) ─────────────────────────────
        $sidebarPostsBase = BlogPostTranslation::where('blog_post_translations.locale', $locale)
            ->join('blog_posts', 'blog_posts.id', '=', 'blog_post_translations.blog_post_id')
            ->where('blog_posts.status', BlogPostStatus::Published)
            ->where('blog_posts.published_at', '<=', now())
            ->whereNull('blog_posts.deleted_at')
            ->where('blog_post_translations.blog_post_id', '!=', $post->id)
            ->select('blog_post_translations.*', 'blog_posts.published_at as post_published_at', 'blog_posts.featured_image as post_featured_image')
            ->with(['blogPost.blogCategory.translations' => fn ($q) => $q->where('locale', $locale)])
            ->orderByDesc('blog_posts.published_at')
            ->limit(13)
            ->get()
            ->map(function ($tr) {
                $p      = $tr->blogPost;
                $cTr    = $p?->blogCategory?->translations->first();
                $rawImg = $p?->featured_image;
                $p->slug                     = $tr->slug;
                $p->title                    = $tr->title;
                $p->category                 = $cTr?->name ?? $p?->blogCategory?->name;
                $p->category_slug            = $cTr?->slug ?? $p?->blogCategory?->slug;
                $p->featured_image           = $rawImg ? 'storage/' . ltrim($rawImg, '/') : null;
                $p->formatted_published_date = $p?->published_at?->translatedFormat('d M, Y');
                return $p;
            });

        $latestPosts   = $sidebarPostsBase->take(3);
        $morePostsList = $sidebarPostsBase->slice(3);

        // ── Related posts (same category, excl. current) ──────────────────────
        $relatedPosts = collect();
        if ($post->blog_category_id) {
            $relatedPosts = BlogPostTranslation::where('blog_post_translations.locale', $locale)
                ->join('blog_posts', 'blog_posts.id', '=', 'blog_post_translations.blog_post_id')
                ->where('blog_posts.status', BlogPostStatus::Published)
                ->where('blog_posts.published_at', '<=', now())
                ->whereNull('blog_posts.deleted_at')
                ->where('blog_posts.blog_category_id', $post->blog_category_id)
                ->where('blog_post_translations.blog_post_id', '!=', $post->id)
                ->select('blog_post_translations.*')
                ->with(['blogPost.blogCategory.translations' => fn ($q) => $q->where('locale', $locale)])
                ->orderByDesc('blog_posts.published_at')
                ->limit(4)
                ->get()
                ->map(function ($tr) {
                    $p      = $tr->blogPost;
                    $cTr    = $p?->blogCategory?->translations->first();
                    $rawImg = $p?->featured_image;
                    $p->slug                     = $tr->slug;
                    $p->title                    = $tr->title;
                    $p->category                 = $cTr?->name ?? $p?->blogCategory?->name;
                    $p->category_slug            = $cTr?->slug ?? $p?->blogCategory?->slug;
                    $p->featured_image           = $rawImg ? 'storage/' . ltrim($rawImg, '/') : null;
                    $p->formatted_published_date = $p?->published_at?->translatedFormat('d M, Y');
                    return $p;
                });
        }

        // ── Sidebar: tags ─────────────────────────────────────────────────────
        $allTags = BlogTag::whereHas('posts', fn ($q) => $q->published())->pluck('name');

        view()->share('alternateUrls', $alternateUrls);

        return view('pages.blog.show', compact(
            'blog', 'alternateUrls', 'seoMeta', 'jsonldSchemas', 'locale',
            'fallbackTitle', 'fallbackDescription', 'fallbackImage', 'ogType',
            'categories', 'latestPosts', 'morePostsList', 'relatedPosts', 'allTags'
        ) + ['noScrollSmoother' => true]);
    }
}
