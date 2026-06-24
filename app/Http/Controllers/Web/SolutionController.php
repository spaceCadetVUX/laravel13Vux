<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Enums\BlogPostStatus;
use App\Models\BlogPostTranslation;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class SolutionController extends Controller
{
    private function ogImage(): ?string
    {
        $raw = Setting::get('default_og_image');
        return $raw ? (str_starts_with($raw, 'http') ? $raw : asset($raw)) : null;
    }

    private function latestBlogs(string $locale, int $limit = 3): Collection
    {
        return BlogPostTranslation::where('blog_post_translations.locale', $locale)
            ->join('blog_posts', 'blog_posts.id', '=', 'blog_post_translations.blog_post_id')
            ->where('blog_posts.status', BlogPostStatus::Published)
            ->where('blog_posts.published_at', '<=', now())
            ->whereNull('blog_posts.deleted_at')
            ->select('blog_post_translations.*')
            ->with(['blogPost.blogCategory.translations' => fn ($q) => $q->where('locale', $locale)])
            ->orderByDesc('blog_posts.published_at')
            ->limit($limit)
            ->get()
            ->map(function ($tr) {
                $p   = $tr->blogPost;
                $cTr = $p?->blogCategory?->translations->first();
                $img = $p?->featured_image;
                return (object) [
                    'title'                    => $tr->title,
                    'slug'                     => $tr->slug,
                    'excerpt'                  => $tr->excerpt,
                    'category'                 => $cTr?->name ?? $p?->blogCategory?->name,
                    'category_slug'            => $cTr?->slug ?? $p?->blogCategory?->slug,
                    'featured_image'           => $img ? 'storage/' . ltrim($img, '/') : null,
                    'formatted_published_date' => $p?->published_at?->translatedFormat('d M, Y'),
                ];
            });
    }

    public function dali(string $locale): View
    {
        view()->share('alternateUrls', [
            'vi' => route('vi.dali-casambi'),
            'en' => route('en.dali-casambi'),
        ]);

        return view('pages.solutions.dali', [
            'locale'             => $locale,
            'seoMeta'            => null,
            'fallbackTitle'      => $locale === 'vi'
                ? 'Giải pháp chiếu sáng DALI-2 & Casambi — Tự động hóa thông minh'
                : 'DALI-2 & Casambi Smart Lighting Solutions',
            'fallbackDescription'=> $locale === 'vi'
                ? 'Tư vấn và phân phối thiết bị chiếu sáng thông minh DALI-2 & Casambi tại Việt Nam. Giải pháp tự động hóa ánh sáng cho công trình dân dụng và thương mại.'
                : 'Consulting and distribution of DALI-2 & Casambi smart lighting systems in Vietnam for residential and commercial projects.',
            'fallbackImage'      => $this->ogImage(),
            'ogType'             => 'website',
            'jsonldSchemas'      => [],
            'latestBlogs'        => $this->latestBlogs($locale),
        ]);
    }

    public function wireless(string $locale): View
    {
        view()->share('alternateUrls', [
            'vi' => route('vi.wireless-casambi'),
            'en' => route('en.wireless-casambi'),
        ]);

        return view('pages.solutions.wireless', [
            'locale'             => $locale,
            'seoMeta'            => null,
            'fallbackTitle'      => $locale === 'vi'
                ? 'Chiếu sáng không dây Casambi — Bluetooth Mesh thông minh'
                : 'Casambi Wireless Smart Lighting — Bluetooth Mesh',
            'fallbackDescription'=> $locale === 'vi'
                ? 'Giải pháp chiếu sáng không dây Casambi dùng Bluetooth Mesh — không cần đi dây điều khiển, dễ lắp đặt và mở rộng.'
                : 'Casambi wireless lighting solutions using Bluetooth Mesh — no control wiring needed, easy to install and scale.',
            'fallbackImage'      => $this->ogImage(),
            'ogType'             => 'website',
            'jsonldSchemas'      => [],
            'latestBlogs'        => $this->latestBlogs($locale),
        ]);
    }

    public function byRole(string $locale): View
    {
        view()->share('alternateUrls', [
            'vi' => route('vi.solutions-by-role'),
            'en' => route('en.solutions-by-role'),
        ]);

        return view('pages.solutions.by-role', [
            'locale'             => $locale,
            'seoMeta'            => null,
            'fallbackTitle'      => $locale === 'vi'
                ? 'Giải pháp theo đối tượng — Chủ nhà, Kiến trúc sư, Kỹ sư ME'
                : 'Smart Lighting by Role — Homeowner, Architect, ME Engineer',
            'fallbackDescription'=> $locale === 'vi'
                ? 'Khám phá giải pháp chiếu sáng thông minh phù hợp với từng đối tượng: chủ nhà, kiến trúc sư, nhà thầu ME và system integrator.'
                : 'Explore smart lighting solutions tailored to each role: homeowner, architect, ME contractor, and system integrator.',
            'fallbackImage'      => $this->ogImage(),
            'ogType'             => 'website',
            'jsonldSchemas'      => [],
        ]);
    }
}
