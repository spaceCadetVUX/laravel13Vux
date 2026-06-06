<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Contracts\View\View;

class AboutController extends Controller
{
    public function show(string $locale): View
    {
        $data = [
            'seoTitle'       => __('about.seo.title', [], $locale),
            'seoDescription' => __('about.seo.description', [], $locale),
            'description'    => Setting::get('about_description') ?? '',
            'foundedYear'    => Setting::get('company_founded_year'),
            'certifications' => array_values(array_filter(
                explode("\n", Setting::get('company_certifications') ?? '')
            )),
            'ogImage'        => Setting::get('about_og_image'),
        ];

        view()->share('alternateUrls', [
            'vi' => route('vi.about'),
            'en' => route('en.about'),
        ]);

        return view('pages.page.about', compact('data', 'locale'));
    }
}
