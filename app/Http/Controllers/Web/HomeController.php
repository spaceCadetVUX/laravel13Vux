<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
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

        $businessSchemas = $this->jsonld->getSchemas();
        $profile         = BusinessProfile::instance();

        // FAQ items for the visible FAQ section on the page
        $faqItems = collect((array) ($profile->extra['faq'] ?? []))
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

        $ogRaw         = Setting::get('default_og_image');
        $fallbackImage = $ogRaw
            ? (str_starts_with($ogRaw, 'http') ? $ogRaw : asset('storage/' . ltrim($ogRaw, '/')))
            : null;

        $seoMeta = null;
        $ogType  = 'website';

        return view('pages.home.index', compact(
            'locale', 'businessSchemas', 'faqItems',
            'seoMeta', 'fallbackTitle', 'fallbackDescription', 'fallbackImage', 'ogType'
        ));
    }
}
