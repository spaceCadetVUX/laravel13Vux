@php
    $locale  = app()->getLocale();
    $appName = config('app.name');

    // OG image: first product that has an image, else site default
    $firstWithImg = $products->first(fn($p) => !empty($p->image_url));
    $ogImage = $firstWithImg
        ? (str_starts_with($firstWithImg->image_url, 'http') ? $firstWithImg->image_url : asset($firstWithImg->image_url))
        : asset('assets/images/casambi/casambiblack.svg');

    if (!empty($keyword)) {
        // ── Search results ─────────────────────────────────────
        $seoTitle    = ($locale === 'vi' ? 'Tìm kiếm: ' : 'Search: ') . $keyword . ' — ' . $appName;
        $seoDesc     = $locale === 'vi'
            ? "Tìm thấy {$products->total()} sản phẩm cho \"{$keyword}\"."
            : "Found {$products->total()} products matching \"{$keyword}\".";
        $seoKeywords = $keyword;
        $seoRobots   = 'noindex, follow';   // search pages must not be indexed

    } elseif (!empty($brand)) {
        // ── Brand page ─────────────────────────────────────────
        $brandModel  = $brands->firstWhere('slug', $brand) ?? $brands->firstWhere('name', $brand);
        $brandName   = $brandModel ? $brandModel->name : ucfirst(str_replace('-', ' ', $brand));
        $seoTitle    = ($locale === 'vi' ? "Sản phẩm $brandName" : "$brandName Products") . ' — ' . $appName;
        $seoDesc     = $brandModel?->description
            ? \Illuminate\Support\Str::limit(strip_tags($brandModel->description), 155)
            : ($locale === 'vi'
                ? "Khám phá toàn bộ sản phẩm {$brandName} chính hãng tại {$appName}."
                : "Explore the complete range of {$brandName} products at {$appName}.");
        $seoKeywords = $brandName . ', ' . ($locale === 'vi' ? 'chiếu sáng thông minh, Casambi' : 'smart lighting, Casambi');
        $seoRobots   = 'index, follow';
        if (!empty($brandModel?->logo_url)) {
            $ogImage = str_starts_with($brandModel->logo_url, 'http') ? $brandModel->logo_url : asset($brandModel->logo_url);
        }

    } elseif (!empty($activeFilters)) {
        // ── Filtered / category page ────────────────────────────
        $seoTitle    = ($locale === 'vi' ? 'Danh mục sản phẩm' : 'Product Category') . ' — ' . $appName;
        $seoDesc     = $locale === 'vi'
            ? 'Khám phá danh mục sản phẩm chiếu sáng thông minh Casambi.'
            : 'Browse our smart lighting product categories.';
        $seoKeywords = __('shop.keywords');
        $seoRobots   = 'noindex, follow';   // filter combos must not be indexed

    } else {
        // ── All products listing ────────────────────────────────
        $seoTitle    = __('shop.title');
        $seoDesc     = __('shop.description');
        $seoKeywords = __('shop.keywords');
        $seoOgTitle  = __('shop.og_title');
        $seoOgDesc   = __('shop.og_description');
        $seoRobots   = 'index, follow';
    }

    $seo = [
        'title'          => $seoTitle,
        'description'    => $seoDesc,
        'keywords'       => $seoKeywords,
        'og_title'       => $seoOgTitle ?? $seoTitle,
        'og_description' => $seoOgDesc  ?? $seoDesc,
        'image'          => $ogImage,
        'canonical'      => (!empty($activeFilters) || !empty($keyword))
            ? route($locale . '.product.shop')   // filter/search pages → canonical to main listing
            : url(request()->path()),
        'robots'         => $seoRobots,
        'type'           => 'website',
        'hreflangs'      => [
            'en' => switch_locale_url('en'),
            'vi' => switch_locale_url('vi'),
        ],
    ];
@endphp

@extends('front.layouts.frontend', ['seo' => $seo])

{{-- BreadcrumbList Schema --}}
@push('head')
@php
    $bcLocale = app()->getLocale();
    $bcItems  = [
        ['name' => $bcLocale === 'vi' ? 'Trang chủ' : 'Home', 'url' => route($bcLocale . '.index')],
        ['name' => $bcLocale === 'vi' ? 'Sản phẩm' : 'Shop',  'url' => route($bcLocale . '.product.shop')],
    ];
    // Thêm danh mục đang lọc (nếu có)
    if (isset($activeSlugs) && is_array($activeSlugs) && count($activeSlugs)) {
        $bcCategoryNames = [];
        if (isset($categories)) {
            foreach ($categories as $parent) {
                if (in_array($parent->slug, $activeSlugs)) $bcCategoryNames[] = $parent->name;
                if ($parent->children) {
                    foreach ($parent->children as $child) {
                        if (in_array($child->slug, $activeSlugs)) $bcCategoryNames[] = $child->name;
                    }
                }
            }
        }
        $bcCategoryNames = $bcCategoryNames ?: $activeSlugs;
        $bcItems[] = ['name' => implode(', ', $bcCategoryNames)]; // trang hiện tại — không cần url
    }
@endphp
<x-breadcrumb-schema :items="$bcItems" />
@endpush

@section('content')

{{-- ==========================================
         SHOP HERO
     ========================================== --}}

<section class="shop-hero position-relative overflow-hidden">
    <img src="{{ asset('images/casambi/bbc.jpg') }}" alt="Shop Hero Background" class="shop-hero-bg w-100 h-100 position-absolute top-0 start-0 object-fit-cover" style="z-index: 0; filter: brightness(1);">
    <div class="container position-relative h-100 d-flex align-items-center" style="z-index: 2;">
        <div>
            <p class="font-xs text-white fw-bold letter-wide m-0" style="margin-bottom: 6px !important;">CASAMBI LIGHTING CONTROL</p>
            <p class="font-xs text-white-50 m-0" style="margin-bottom: 20px !important;">Bluetooth Mesh / Không Hub / Không Gateway</p>
            <h1 class="shop-hero-title text-white fw-black m-0" style="font-size: clamp(3rem, 8vw, 8rem); line-height: 0.9; letter-spacing: 0.05em; text-transform: uppercase;">
                <span class="d-block" style="font-size: clamp(0.9rem, 1.5vw, 1.4rem); letter-spacing: 0.2em; font-weight: 500; margin-bottom: 4px; opacity: 0.75;">GIẢI PHÁP ĐIỀU KHIỂN CHIẾU SÁNG</span>
                 <img src="{{ asset('images/casambi/casambiwhite.svg') }}" alt="Casambi" class="responsive-img" style="max-width: 400px; width: 80%; height: auto; padding-top: 20px;">
            </h1>
        </div>
    </div>
</section>

{{-- ==========================================
        TITLE & BREADCRUMBS
    ========================================== --}}
<div class="shop-header text-center py-5 mt-3">
    <h2 class="fw-bold fs-3 mb-2">Sản Phẩm Casambi</h2>
    <p class="text-muted text-uppercase mb-0" style="font-size: 0.75rem; letter-spacing: 0.25em;">
        Home <span class="mx-1">&gt;</span> Shop
        @if(isset($activeSlugs) && count($activeSlugs))
            @php
                // Resolve names from the already-loaded $categories tree
                $breadcrumbNames = [];
                if (isset($categories)) {
                    foreach ($categories as $parent) {
                        if (in_array($parent->slug, $activeSlugs)) {
                            $breadcrumbNames[] = $parent->name;
                        }
                        if ($parent->children) {
                            foreach ($parent->children as $child) {
                                if (in_array($child->slug, $activeSlugs)) {
                                    $breadcrumbNames[] = $child->name;
                                }
                            }
                        }
                    }
                }
                $breadcrumbNames = $breadcrumbNames ?: $activeSlugs;
            @endphp
            <span class="mx-1">&gt;</span> {{ implode(', ', $breadcrumbNames) }}
        @endif
    </p>
</div>

<!-- MAIN SHOP CONTENT -->
<div class="container pb-5 mb-5">
    <div class="row gx-lg-5">
        <!-- SIDEBAR (Offcanvas on Mobile) -->
        <aside class="col-lg-2 shop-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="shopSidebar" aria-labelledby="shopSidebarLabel">
            <div class="offcanvas-header d-lg-none border-bottom mb-3 px-4 pt-4">
                <h5 class="offcanvas-title fw-bold" id="shopSidebarLabel">Filters</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#shopSidebar" aria-label="Close"></button>
            </div>

            <div class="offcanvas-body flex-column px-4 px-lg-0 pb-4">
                <h5 class="fw-bold mb-4 fs-6 d-none d-lg-block">Filters</h5>

            {{-- -------------------------------------------------------
                    Determine active child slugs:
                    $activeSlugs is injected by byCategory() controller when
                    the user is on /danh-muc?brand=zara,hm&size=m
                    On the main shop index, $activeSlugs is not set → no filters.
                    ------------------------------------------------------- --}}
                @php
                    // $activeSlugs is set by byCategory controller (flat array of selected child slugs)
                    // On the shop index page it's not set — read nothing (no filter active)
                    $selectedSlugs = isset($activeSlugs) && is_array($activeSlugs) ? $activeSlugs : [];

                    // The fixed category filter page URL (no wildcard)
                    $categoryPageUrl = route(current_locale() . '.product.category');
                    // Base shop URL for Clear All
                    $shopBaseUrl = route(current_locale() . '.product.shop');
                @endphp

                <!-- Size (dynamic from categories) - rendered as checkboxes but styled as size buttons -->
                @php
                    $sizeCategory = null;
                    if (isset($categories) && $categories instanceof \Illuminate\Support\Collection) {
                        $sizeCategory = $categories->firstWhere('slug', 'size') ?: $categories->firstWhere('name', 'Size');
                    }
                @endphp

                @if($sizeCategory && $sizeCategory->children && $sizeCategory->children->count())
                <div class="filter-group mb-4">
                    <h6 class="filter-title font-xs fw-bold text-uppercase letter-wide text-muted mb-3">{{ $sizeCategory->name }}</h6>
                    <div class="d-flex flex-wrap gap-2 size-filters">
                        @foreach($sizeCategory->children as $child)
                            @php $isChecked = in_array($child->slug, $selectedSlugs); @endphp
                            <div>
                                <input class="category-checkbox visually-hidden"
                                    type="checkbox"
                                    id="size-{{ $child->id }}"
                                    data-slug="{{ $child->slug }}"
                                    data-parent-slug="{{ $sizeCategory->slug }}"
                                    {{ $isChecked ? 'checked' : '' }}>
                                <label for="size-{{ $child->id }}" class="size-btn{{ $isChecked ? ' active' : '' }}">{{ $child->name }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif


                <!-- Dynamic category filters: color-type parents → swatches, others → checkboxes -->
                @if(isset($categories) && $categories->count())
                    @foreach($categories as $parent)
                        {{-- Skip size — already shown as size-button filter --}}
                        @if($sizeCategory && $parent->id === $sizeCategory->id)
                            @continue
                        @endif

                        @php
                            $isColorParent = ($parent->type === 'color')
                                || $parent->children->filter(fn($c) => !empty($c->color_code))->count() > 0;
                        @endphp

                        <div class="filter-group mb-4">
                            <h6 class="filter-title font-xs fw-bold text-uppercase letter-wide text-muted mb-3">
                                {{ $parent->name }}
                            </h6>

                            @if($parent->children && $parent->children->count())
                                @if($isColorParent)
                                    {{-- ── Color swatches — exact same pattern as hardcoded Colors section ── --}}
                                    <div class="d-flex flex-wrap gap-2 color-filters">
                                        @foreach($parent->children as $child)
                                            @php
                                                $isChecked  = in_array($child->slug, $selectedSlugs);
                                                $colorCode  = $child->color_code ?: '#cccccc';
                                                $isLight    = $colorCode === '#ffffff' || strtolower($colorCode) === 'white';
                                            @endphp
                                            {{-- Hidden checkbox so JS tracks checked state --}}
                                        <input class="category-checkbox visually-hidden"
                                                type="checkbox"
                                                id="color-cat-{{ $child->id }}"
                                                data-slug="{{ $child->slug }}"
                                                data-parent-slug="{{ $parent->slug }}"
                                                {{ $isChecked ? 'checked' : '' }}>
                                        {{-- Label styled exactly like the hardcoded color-btn buttons --}}
                                        <label for="color-cat-{{ $child->id }}"
                                                class="color-btn{{ $isChecked ? ' active' : '' }}"
                                                title="{{ $child->name }} ({{ $colorCode }})"
                                                @style([
                                                    'background-color: ' . $colorCode,
                                                    'display: block',
                                                    'cursor: pointer',
                                                    'border: 1px solid #ccc' => $isLight
                                                ])>
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    {{-- ── Standard checkboxes ── --}}
                                    @foreach($parent->children as $child)
                                        @php $isChecked = in_array($child->slug, $selectedSlugs); @endphp
                                        <div class="form-check filter-check">
                                            <input class="form-check-input category-checkbox"
                                                type="checkbox"
                                                id="cat-{{ $child->id }}"
                                                data-slug="{{ $child->slug }}"
                                                data-parent-slug="{{ $parent->slug }}"
                                                {{ $isChecked ? 'checked' : '' }}>
                                            <label class="form-check-label" for="cat-{{ $child->id }}">{{ $child->name }}</label>
                                        </div>
                                    @endforeach
                                @endif
                            @endif
                        </div>
                    @endforeach
                @else
                    <p class="text-muted small">No categories found.</p>
                @endif

                {{-- ===================================================
                     BRAND FILTER
                     Checkboxes — integrated into unified filter system.
                     Activated on "Apply Filters" click.
                     URL output: /danh-muc?brand=nike,adidas&women=ao-thun
                     Brand landing pages (/brand/{slug}) kept for SEO.
                =================================================== --}}
                @if(isset($brands) && $brands->count())
                @php
                    // Active brand slugs come from $activeSlugs (controller reads ?brand=)
                    $activeBrandSlugsFromQuery = isset($activeFilters['brand'])
                        ? $activeFilters['brand'] : [];
                @endphp
                <div class="filter-group mb-4">
                    <h6 class="filter-title font-xs fw-bold text-uppercase letter-wide text-muted mb-3">Brand</h6>
                    <div class="d-flex flex-column gap-1">
                        @foreach($brands as $b)
                        @php
                            $isBrandChecked = in_array($b->slug, $activeBrandSlugsFromQuery);
                            $logoSrc = $b->logo_url
                                ? (str_starts_with($b->logo_url, 'http') ? $b->logo_url : asset(ltrim($b->logo_url, '/')))
                                : null;
                        @endphp
                        <div class="form-check filter-check d-flex align-items-center gap-2">
                            <input class="form-check-input category-checkbox"
                                type="checkbox"
                                id="brand-{{ $b->id }}"
                                data-slug="{{ $b->slug }}"
                                data-parent-slug="brand"
                                {{ $isBrandChecked ? 'checked' : '' }}>
                            <label class="form-check-label d-flex align-items-center gap-2 w-100" for="brand-{{ $b->id }}">
                                @if($logoSrc)
                                    <img src="{{ $logoSrc }}" alt="{{ $b->name }}"
                                         style="height:16px;width:auto;max-width:40px;object-fit:contain;"
                                         loading="lazy" onerror="this.style.display='none'">
                                @else
                                    <span class="brand-filter-dot"></span>
                                @endif
                                <span>{{ $b->name }}</span>
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif



                <!-- Filter Actions -->
                <div class="filter-actions mt-5 d-flex flex-column gap-2">
                    <button type="button" class="btn-dark-custom w-100 text-center" id="applyFiltersBtn">Apply Filters</button>
                    <button type="button" class="btn-outline-custom w-100 text-center" id="clearFiltersBtn">Clear All</button>
                </div>
            </div>
        </aside>

        <!-- PRODUCT GRID -->
        <main class="col-lg-10">
            <!-- Mobile Filter Toggle Bar -->
            <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom d-lg-none">
                <span class="font-xs fw-bold text-uppercase letter-wide text-muted">{{ $products->total() }} Results</span>
                <button class="btn btn-outline-dark btn-sm rounded-0 text-uppercase letter-wide fw-bold font-xs px-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#shopSidebar" aria-controls="shopSidebar">
                    <i class="bi bi-sliders me-2"></i> Filter
                </button>
            </div>

            @if($products->isEmpty())
            <div class="d-flex flex-column align-items-center justify-content-center py-5 w-100 text-center">
                <i class="fal fa-box-open fs-1 text-muted mb-3"></i>
                <p class="text-muted mb-0">{{ __('shop.labels.no_products_message') }}</p>
            </div>
            @else
            <div class="row row-cols-2 row-cols-md-3 g-3 g-lg-4 gx-lg-5">
                @foreach($products as $product)
                <div class="col">
                    @include('front.components.product-card', [
                        'url' => route(current_locale() . '.product.show', $product->slug),
                        'image' => $product->image_url ? (str_starts_with($product->image_url, 'http') ? $product->image_url : asset($product->image_url)) : asset('assets/img/product/default.jpg'),
                        'name' => $product->name,
                        'price' => (($product->sale_price > 0 && $product->sale_price < $product->price) ? number_format($product->sale_price, 0, ",", ".") . "đ" : ($product->price > 0 ? number_format($product->price, 0, ",", ".") . "đ" : null)),
                        'oldPrice' => ($product->sale_price > 0 && $product->price > 0 && $product->sale_price < $product->price) ? number_format($product->price, 0, ",", ".") . "đ" : null,
                        'tag' => $product->featured ? 'badge-featured' : ($product->sale_price && $product->sale_price < $product->price ? 'badge-sale' : null),
                        'tagLabel' => $product->featured ? __('shop.labels.badge_featured') : ($product->sale_price && $product->sale_price < $product->price ? __('shop.labels.badge_sale') : null)
                    ])
                </div>
                @endforeach
            </div>
            @endif

            <!-- Pagination -->
            @if($products->hasPages())
            <div class="blog-pagination" style="margin-top:40px; margin-bottom:24px;">
                @if($products->onFirstPage())
                    <span class="disabled">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                    </span>
                @else
                    <a href="{{ $products->previousPageUrl() }}" rel="prev">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                    </a>
                @endif

                @foreach($products->getUrlRange(1, $products->lastPage()) as $page => $url)
                    @if($page == $products->currentPage())
                        <span class="current">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach

                @if($products->hasMorePages())
                    <a href="{{ $products->nextPageUrl() }}" rel="next">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                    </a>
                @else
                    <span class="disabled">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                    </span>
                @endif
            </div>
            @endif

        </main>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var shopBaseUrl     = '{{ $shopBaseUrl }}';
    var categoryPageUrl = '{{ $categoryPageUrl }}';

    /**
     * Collect all checked checkboxes, grouped by their data-parent-slug.
     * Category checkboxes use parent category slugs (e.g. 'women', 'size').
     * Brand checkboxes use the reserved key 'brand'.
     * Returns: { women: ['ao-thun'], size: ['m'], brand: ['nike', 'adidas'] }
     */
    function getCheckedGroups() {
        var groups = {};
        document.querySelectorAll('.category-checkbox:checked').forEach(function (el) {
            var parentSlug = el.dataset.parentSlug;
            var slug       = el.dataset.slug;
            if (!parentSlug || !slug) return;
            if (!groups[parentSlug]) groups[parentSlug] = [];
            groups[parentSlug].push(slug);
        });
        return groups;
    }

    /**
     * Build unified SEO-friendly filter URL.
     *
     * No filters        → /vi/san-pham
     * Only categories   → /vi/san-pham/danh-muc?women=ao-thun&size=m
     * Only brand        → /vi/san-pham/danh-muc?brand=nike
     * Categories+brand  → /vi/san-pham/danh-muc?women=ao-thun&size=m&brand=nike,adidas
     *
     * The 'brand' key is handled specially by byCategory() as a brand FK filter.
     * All other keys are treated as root category slugs.
     */
    function buildFilterUrl(groups) {
        if (Object.keys(groups).length === 0) {
            return shopBaseUrl;
        }

        // Preserve unrelated params already in the URL (e.g. ?sort=popular)
        var existingParams = new URLSearchParams(window.location.search);

        var params = new URLSearchParams();
        Object.keys(groups).forEach(function (key) {
            existingParams.delete(key);              // remove stale value
            params.set(key, groups[key].join(','));
        });

        // Re-attach remaining unrelated params (sort, page, etc.)
        existingParams.forEach(function (val, key) {
            // Skip filter keys we own so we don't double-write
            if (!groups.hasOwnProperty(key)) {
                params.append(key, val);
            }
        });

        return categoryPageUrl + '?' + params.toString();
    }

    // Apply Filters button — only navigate on explicit click
    var applyBtn = document.getElementById('applyFiltersBtn');
    if (applyBtn) {
        applyBtn.addEventListener('click', function () {
            window.location.href = buildFilterUrl(getCheckedGroups());
        });
    }

    // Clear All → back to base shop, no filters
    var clearBtn = document.getElementById('clearFiltersBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            window.location.href = shopBaseUrl;
        });
    }
});

</script>
@endpush

@endsection