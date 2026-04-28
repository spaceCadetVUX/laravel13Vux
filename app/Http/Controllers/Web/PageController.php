<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PageTranslation;
use App\Services\Seo\JsonldService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PageController extends Controller
{
    public function show(string $locale, string $slug): View|RedirectResponse
    {
        $translation = PageTranslation::where('locale', $locale)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $translation) {
            $viTranslation = PageTranslation::where('locale', config('app.fallback_locale'))
                ->where('slug', $slug)
                ->where('is_active', true)
                ->first();

            if ($viTranslation) {
                return redirect(
                    route('page.show', ['locale' => config('app.fallback_locale'), 'slug' => $viTranslation->slug]),
                    302
                );
            }

            abort(404);
        }

        // PageTranslation is standalone — build alternate URLs from sibling translations
        $alternateUrls = PageTranslation::where('page_key', $translation->page_key)
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(fn ($t) => [
                $t->locale => route('page.show', ['locale' => $t->locale, 'slug' => $t->slug]),
            ])
            ->all();

        // PageTranslation carries its own meta fields; pass as $seoMeta for the layout
        $seoMeta       = $translation;
        $jsonldSchemas = [
            app(JsonldService::class)->buildBreadcrumb([
                ['name' => __('common.home', [], $locale), 'url' => route('home', ['locale' => $locale])],
                ['name' => $translation->title, 'url' => url()->current()],
            ]),
        ];

        return view('pages.page.show', compact(
            'translation', 'alternateUrls', 'seoMeta', 'jsonldSchemas', 'locale'
        ));
    }
}
