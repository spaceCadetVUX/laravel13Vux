<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CategoryTranslation;
use App\Services\Seo\JsonldService;
use App\Services\Seo\SeoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CategoryController extends Controller
{
    public function show(string $locale, string $slug): View|RedirectResponse
    {
        $translation = CategoryTranslation::where('locale', $locale)
            ->where('slug', $slug)
            ->with('category')
            ->first();

        if (! $translation) {
            $viTranslation = CategoryTranslation::where('locale', config('app.fallback_locale'))
                ->where('slug', $slug)
                ->first();

            if ($viTranslation) {
                return redirect(
                    route('category.show', ['locale' => config('app.fallback_locale'), 'slug' => $viTranslation->slug]),
                    302
                );
            }

            abort(404);
        }

        $category = $translation->category;
        if (! $category || ! $category->is_active) {
            abort(404);
        }

        $alternateUrls = app(SeoService::class)->alternateUrls($category, 'category.show');
        $seoMeta       = $translation;
        $jsonldSchemas = [
            app(JsonldService::class)->buildBreadcrumb([
                ['name' => __('common.home', [], $locale), 'url' => route('home', ['locale' => $locale])],
                ['name' => $translation->name, 'url' => url()->current()],
            ]),
        ];

        return view('pages.category.show', compact(
            'category', 'translation', 'alternateUrls', 'seoMeta', 'jsonldSchemas', 'locale'
        ));
    }
}
