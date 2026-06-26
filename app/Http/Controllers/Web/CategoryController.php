<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\CategoryTranslation;
use App\Models\FilterGroup;
use App\Models\ProductTranslation;
use App\Services\Seo\JsonldService;
use App\Services\Seo\SeoService;
use App\Support\LocaleUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Tiptap\Editor;
use Tiptap\Extensions\StarterKit;
use Tiptap\Nodes\Image as TiptapImage;

class CategoryController extends Controller
{
    public function index(string $locale): View
    {
        view()->share('alternateUrls', [
            'vi' => route('vi.product.category'),
            'en' => route('en.product.category'),
        ]);

        $categories = CategoryTranslation::where('locale', $locale)
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->with(['category' => fn ($q) => $q->withCount([
                'products as product_count' => fn ($q2) => $q2->where('is_active', true),
            ])])
            ->orderBy('name')
            ->get()
            ->filter(fn ($tr) => $tr->category !== null);

        $fallbackTitle = $locale === 'vi' ? 'Danh mục sản phẩm' : 'Product Categories';
        $fallbackDescription = $locale === 'vi'
            ? 'Khám phá tất cả danh mục sản phẩm chiếu sáng thông minh Casambi.'
            : 'Browse all Casambi smart lighting product categories.';

        return view('pages.category.index', compact(
            'locale', 'categories', 'fallbackTitle', 'fallbackDescription'
        ));
    }

    public function show(string $locale, string $slug): View|RedirectResponse
    {
        $translation = CategoryTranslation::where('locale', $locale)
            ->where('slug', $slug)
            ->with('category')
            ->first();

        if (! $translation) {
            $alt = CategoryTranslation::where('slug', $slug)
                ->whereIn('locale', config('app.supported_locales'))
                ->where('locale', '!=', $locale)
                ->first();

            if ($alt) {
                return redirect(LocaleUrl::for('category', $alt->slug, $alt->locale), 302);
            }

            abort(404);
        }

        $category = $translation->category;
        if (! $category || ! $category->is_active) {
            abort(404);
        }

        // ── Products in this category ─────────────────────────────────────────
        $keyword   = (string) request()->query('q', '');
        $brandSlug = (string) request()->query('brand', '');

        $filterGroups = FilterGroup::active()
            ->with('activeValues')
            ->orderBy('sort_order')
            ->get();

        $activeValueSlugs = [];
        foreach ($filterGroups as $group) {
            $raw = (string) request()->query($group->slug, '');
            if ($raw) {
                $slugs = array_values(array_filter(array_map('trim', explode(',', $raw))));
                if ($slugs) $activeValueSlugs[$group->slug] = $slugs;
            }
        }

        $productQuery = ProductTranslation::where('locale', $locale)
            ->whereHas('product', fn ($q) => $q->active()
                ->whereHas('categories', fn ($q2) => $q2->where('categories.id', $category->id))
            )
            ->with([
                'product.thumbnail',
                'product.brand',
                'product.categories' => fn ($q) => $q->orderBy('sort_order'),
                'product.categories.translations' => fn ($q) => $q->where('locale', $locale),
            ]);

        foreach ($filterGroups as $group) {
            if (empty($activeValueSlugs[$group->slug])) continue;
            $valueSlugs = $activeValueSlugs[$group->slug];
            $productQuery->whereHas(
                'product.filterValues',
                fn ($q) => $q->where('filter_group_id', $group->id)
                             ->whereIn('filter_values.slug', $valueSlugs)
            );
        }

        if ($brandSlug) {
            $productQuery->whereHas('product.brand', fn ($q) => $q->where('slug', $brandSlug));
        }

        if ($keyword) {
            $productQuery->where(fn ($q) =>
                $q->where('name', 'ilike', "%{$keyword}%")
                  ->orWhere('short_description', 'ilike', "%{$keyword}%")
            );
        }

        $products = $productQuery->orderBy('id', 'desc')->paginate(24)->withQueryString();

        $brands = Brand::active()->orderBy('sort_order')->orderBy('name')->get();

        // ── FAQ ───────────────────────────────────────────────────────────────
        $faqField    = 'faq_items_' . $locale;
        $faqItems    = is_array($category->$faqField ?? null) ? $category->$faqField : [];
        $faqEntities = array_filter(array_map(
            fn($f) => (trim($f['question'] ?? '') && trim($f['answer'] ?? '')) ? $f : null,
            $faqItems
        ));

        // ── Rich content HTML ─────────────────────────────────────────────────
        $richContentHtml = null;
        $rawContent      = $translation->rich_content;
        if (! empty($rawContent) && is_array($rawContent)) {
            try {
                $richContentHtml = (new Editor(['extensions' => [
                    new StarterKit,
                    new TiptapImage,
                    new \Tiptap\Nodes\Table,
                    new \Tiptap\Nodes\TableRow,
                    new \Tiptap\Nodes\TableHeader,
                    new \Tiptap\Nodes\TableCell,
                ]]))->setContent($rawContent)->getHTML();
                // Strip empty paragraphs only
                if (trim(strip_tags($richContentHtml)) === '') {
                    $richContentHtml = null;
                }
            } catch (\Throwable) {
                $richContentHtml = null;
            }
        }

        // ── SEO ───────────────────────────────────────────────────────────────
        $alternateUrls  = app(SeoService::class)->alternateUrls($category);
        $category->loadMissing('seoMetas');
        $seoMeta        = $category->seoMeta($locale);
        $jsonldSchemas  = app(JsonldService::class)->getActiveSchemas($category, $locale)
            ->pluck('payload')->toArray();
        $fallbackTitle       = $translation->name;
        $fallbackDescription = $translation->description ?? '';
        $fallbackImage       = $category->image_path
            ? asset('storage/' . $category->image_path)
            : null;
        $ogType     = 'website';
        $currentUrl = request()->fullUrl();

        view()->share('alternateUrls', $alternateUrls);

        return view('pages.category.show', compact(
            'category', 'translation', 'locale',
            'products', 'filterGroups', 'brands', 'activeValueSlugs', 'brandSlug', 'keyword',
            'faqItems', 'faqEntities',
            'richContentHtml',
            'alternateUrls', 'seoMeta', 'jsonldSchemas',
            'fallbackTitle', 'fallbackDescription', 'fallbackImage', 'ogType', 'currentUrl'
        ));
    }
}

