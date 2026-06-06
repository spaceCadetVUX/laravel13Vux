<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BusinessProfile;
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

        return view('pages.home.index', compact('locale', 'businessSchemas', 'faqItems'));
    }
}
