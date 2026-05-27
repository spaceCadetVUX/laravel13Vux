<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ProductTranslation;
use App\Services\Seo\JsonldService;
use App\Services\Seo\SeoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProductController extends Controller
{
    public function show(string $locale, string $slug): View|RedirectResponse
    {
        $translation = ProductTranslation::where('locale', $locale)
            ->where('slug', $slug)
            ->with(['product.categories', 'product.thumbnail'])
            ->first();

        if (! $translation) {
            $viTranslation = ProductTranslation::where('locale', config('app.fallback_locale'))
                ->where('slug', $slug)
                ->first();

            if ($viTranslation) {
                return redirect(
                    route('product.show', ['locale' => config('app.fallback_locale'), 'slug' => $viTranslation->slug]),
                    302
                );
            }

            abort(404);
        }

        $product = $translation->product;
        if (! $product || ! $product->is_active) {
            abort(404);
        }

        $primaryCategory   = $product->categories->first();
        $catTranslation    = $primaryCategory?->translation($locale);
        $catUrl            = $primaryCategory && $catTranslation
            ? route('category.show', ['locale' => $locale, 'slug' => $catTranslation->slug])
            : '';

        $alternateUrls       = app(SeoService::class)->alternateUrls($product, 'product.show');
        $seoMeta             = $product->seoMeta($locale);
        $jsonldSchemas       = app(JsonldService::class)->getActiveSchemas($product, $locale)
            ->pluck('payload')
            ->toArray();
        $fallbackTitle       = $translation->name;
        $fallbackDescription = $translation->short_description ?? '';
        $fallbackImage       = $product->thumbnail
            ? url($product->thumbnail->url)
            : null;
        $ogType              = 'product';

        return view('pages.product.show', compact(
            'product', 'translation', 'alternateUrls', 'seoMeta', 'jsonldSchemas', 'locale',
            'fallbackTitle', 'fallbackDescription', 'fallbackImage', 'ogType'
        ));
    }
}
