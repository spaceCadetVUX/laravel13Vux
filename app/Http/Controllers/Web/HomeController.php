<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Enums\BlogPostStatus;
use App\Models\BlogPostTranslation;
use App\Models\BusinessProfile;
use App\Models\Setting;
use App\Services\Seo\BusinessJsonldService;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __construct(private BusinessJsonldService $jsonld) {}

    public function index(string $locale): View
    {
        view()->share('alternateUrls', [
            'vi' => route('vi.index'),
            'en' => route('en.index'),
        ]);

        $businessSchemas = $this->jsonld->getSchemas($locale);
        $profile         = BusinessProfile::instance();

        // FAQ items for the visible FAQ section on the page
        $faqKey   = $locale === 'en' ? 'faq_en' : 'faq';
        $faqItems = collect((array) ($profile->extra[$faqKey] ?? []))
            ->map(fn ($f) => ['q' => $f['question'] ?? '', 'a' => $f['answer'] ?? ''])
            ->filter(fn ($f) => filled($f['q']))
            ->values()
            ->all();

        // ── SEO fallbacks ──────────────────────────────────────────────────────
        $siteName    = $profile->name ?: config('app.name');
        $tagline     = $profile->tagline ?? '';

        $enTagline = Setting::get('site_tagline_en') ?: 'Smart Lighting Solutions';
        $fallbackTitle = $locale === 'vi'
            ? ($tagline ?: $siteName)
            : $enTagline;

        $fallbackDescription = Setting::get('meta_description')
            ?? ($tagline ?: null)
            ?? ($locale === 'vi' ? 'Phân phối và tư vấn giải pháp chiếu sáng thông minh KNX, DALI-2, Casambi tại Việt Nam.'
                                 : 'Distributor and consultant for smart lighting solutions in Vietnam.');

        $ogRaw = $profile->extra['og_image'] ?? Setting::get('default_og_image');
        $fallbackImage = $ogRaw
            ? (str_starts_with($ogRaw, 'http') ? $ogRaw : asset('storage/' . ltrim($ogRaw, '/')))
            : null;

        $seoMeta = null;
        $ogType  = 'website';

        $latestBlogs = BlogPostTranslation::where('blog_post_translations.locale', $locale)
            ->join('blog_posts', 'blog_posts.id', '=', 'blog_post_translations.blog_post_id')
            ->where('blog_posts.status', BlogPostStatus::Published)
            ->where('blog_posts.published_at', '<=', now())
            ->whereNull('blog_posts.deleted_at')
            ->select('blog_post_translations.*')
            ->with(['blogPost.blogCategory.translations' => fn ($q) => $q->where('locale', $locale)])
            ->orderByDesc('blog_posts.published_at')
            ->limit(3)
            ->get()
            ->map(function ($tr) {
                $p    = $tr->blogPost;
                $cTr  = $p?->blogCategory?->translations->first();
                $img  = $p?->featured_image;
                return (object) [
                    'title'                  => $tr->title,
                    'slug'                   => $tr->slug,
                    'excerpt'                => $tr->excerpt,
                    'category'               => $cTr?->name ?? $p?->blogCategory?->name,
                    'category_slug'          => $cTr?->slug ?? $p?->blogCategory?->slug,
                    'featured_image'         => $img ? 'storage/' . ltrim($img, '/') : null,
                    'formatted_published_date' => $p?->published_at?->translatedFormat('d M, Y'),
                ];
            });

        return view('pages.home.index', compact(
            'locale', 'businessSchemas', 'faqItems', 'latestBlogs',
            'seoMeta', 'fallbackTitle', 'fallbackDescription', 'fallbackImage', 'ogType'
        ));
    }
}
