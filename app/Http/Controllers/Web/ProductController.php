<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ProductTranslation;
use Illuminate\Http\JsonResponse;
use App\Services\Seo\JsonldService;
use App\Services\Seo\SeoService;
use App\Support\LocaleUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProductController extends Controller
{
    public function index(string $locale): View
    {
        $keyword       = (string) request()->query('q', '');
        $brandSlug     = (string) request()->query('brand', '');
        $activeFilters = [];
        $activeSlugs   = [];

        $reserved = ['q', 'brand', 'page', 'sort'];
        foreach (request()->query() as $key => $value) {
            if (! in_array($key, $reserved, true) && $value) {
                $slugs = array_values(array_filter(array_map('trim', explode(',', (string) $value))));
                if ($slugs) {
                    $activeFilters[$key] = $slugs;
                    array_push($activeSlugs, ...$slugs);
                }
            }
        }

        $query = ProductTranslation::where('locale', $locale)
            ->whereHas('product', fn ($q) => $q->active())
            ->with(['product.thumbnail', 'product.brand']);

        if (! empty($activeSlugs)) {
            $query->whereHas(
                'product.categories',
                fn ($q) => $q->whereIn('slug', $activeSlugs)
            );
        }

        if ($brandSlug) {
            $query->whereHas('product.brand', fn ($q) => $q->where('slug', $brandSlug));
            if (! isset($activeFilters['brand'])) {
                $activeFilters['brand'] = [$brandSlug];
            }
        }

        if ($keyword) {
            $query->where(fn ($q) =>
                $q->where('name', 'ilike', "%{$keyword}%")
                  ->orWhere('short_description', 'ilike', "%{$keyword}%")
            );
        }

        $products = $query->orderBy('id', 'desc')->paginate(24)->withQueryString();

        $categories = Category::active()
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->active()->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $brands = Brand::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        view()->share('alternateUrls', [
            'vi' => route('vi.product.shop'),
            'en' => route('en.product.shop'),
        ]);

        return view('pages.product.index', compact(
            'locale', 'products', 'categories', 'brands',
            'activeFilters', 'activeSlugs', 'keyword'
        ));
    }

    public function autocomplete(string $locale): JsonResponse
    {
        return response()->json([]);
    }

    public function show(string $locale, string $slug): View|RedirectResponse
    {
        $translation = ProductTranslation::where('locale', $locale)
            ->where('slug', $slug)
            ->with([
                'product.categories',
                'product.thumbnail',
                'product.images',
                'product.brand',
                'product.manufacturer',
                'product.attributes',
                'product.videos',
            ])
            ->first();

        if (! $translation) {
            $altTranslation = ProductTranslation::where('slug', $slug)
                ->whereIn('locale', config('app.supported_locales'))
                ->where('locale', '!=', $locale)
                ->first();

            if ($altTranslation) {
                return redirect(
                    LocaleUrl::for('product', $altTranslation->slug, $altTranslation->locale),
                    302
                );
            }

            abort(404);
        }

        $product = $translation->product;
        if (! $product || ! $product->is_active) {
            abort(404);
        }

        // Related products: same category, same locale, exclude self, limit 8
        $firstCategory   = $product->categories->first();
        $relatedProducts = collect();
        if ($firstCategory) {
            $relatedIds = ProductTranslation::where('locale', $locale)
                ->where('id', '!=', $translation->id)
                ->whereHas('product', fn ($q) =>
                    $q->active()
                      ->whereHas('categories', fn ($q2) => $q2->where('categories.id', $firstCategory->id))
                )
                ->with(['product.thumbnail'])
                ->limit(8)
                ->get();
            $relatedProducts = $relatedIds;
        }

        $alternateUrls       = app(SeoService::class)->alternateUrls($product);
        $seoMeta             = $product->seoMeta($locale);
        $jsonldSchemas = app(JsonldService::class)->getActiveSchemas($product, $locale)
            ->pluck('payload')
            ->toArray();

        // Strip price fields at read-time so the JSON-LD always reflects
        // show_price instantly — regardless of whether the queue job has run.
        if (! $product->show_price) {
            $jsonldSchemas = array_map(function (array $schema) {
                if (($schema['@type'] ?? '') !== 'Product' || ! isset($schema['offers'])) {
                    return $schema;
                }
                $offers = $schema['offers'];
                unset($offers['price'], $offers['priceCurrency'], $offers['lowPrice'], $offers['highPrice']);
                if (isset($offers['offers'])) {
                    $offers['offers'] = array_map(
                        fn ($o) => array_diff_key($o, array_flip(['price', 'priceCurrency'])),
                        $offers['offers']
                    );
                }
                $schema['offers'] = $offers;
                return $schema;
            }, $jsonldSchemas);
        }
        $fallbackTitle       = $translation->name;
        $fallbackDescription = $translation->short_description ?? '';
        $fallbackImage       = $product->thumbnail
            ? url($product->thumbnail->url)
            : null;
        $ogType              = 'product';

        view()->share('alternateUrls', $alternateUrls);

        return view('pages.product.show', compact(
            'product', 'translation', 'alternateUrls', 'seoMeta', 'jsonldSchemas', 'locale',
            'fallbackTitle', 'fallbackDescription', 'fallbackImage', 'ogType',
            'relatedProducts'
        ));
    }
}
