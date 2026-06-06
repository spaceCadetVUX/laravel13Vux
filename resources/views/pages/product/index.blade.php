@php
    $locale  = app()->getLocale();
    $appName = config('app.name');

    $firstWithImg = $products->first(fn($p) => !empty($p->product->thumbnail?->url));
    $ogImage = $firstWithImg?->product->thumbnail?->url ?? asset('assets/images/casambi/casambiblack.svg');

    if (!empty($keyword)) {
        $seoTitle    = ($locale === 'vi' ? 'Tìm kiếm: ' : 'Search: ') . $keyword . ' — ' . $appName;
        $seoDesc     = $locale === 'vi'
            ? "Tìm thấy {$products->total()} sản phẩm cho \"{$keyword}\"."
            : "Found {$products->total()} products matching \"{$keyword}\".";
        $seoKeywords = $keyword;
        $seoRobots   = 'noindex, follow';
    } elseif (!empty($brand)) {
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
        $seoTitle    = ($locale === 'vi' ? 'Danh mục sản phẩm' : 'Product Category') . ' — ' . $appName;
        $seoDesc     = $locale === 'vi'
            ? 'Khám phá danh mục sản phẩm chiếu sáng thông minh Casambi.'
            : 'Browse our smart lighting product categories.';
        $seoKeywords = __('shop.keywords');
        $seoRobots   = 'noindex, follow';
    } else {
        $seoTitle    = __('shop.title');
        $seoDesc     = __('shop.description');
        $seoKeywords = __('shop.keywords');
        $seoOgTitle  = __('shop.og_title');
        $seoOgDesc   = __('shop.og_description');
        $seoRobots   = 'index, follow';
    }

    $categoryPageUrl = route(current_locale() . '.product.category');
    $shopBaseUrl     = route(current_locale() . '.product.shop');
@endphp

@extends('layouts.frontend')

@push('head')
@php
    $bcLocale = app()->getLocale();
    $bcItems  = [
        ['name' => $bcLocale === 'vi' ? 'Trang chủ' : 'Home', 'url' => route($bcLocale . '.index')],
        ['name' => $bcLocale === 'vi' ? 'Sản phẩm' : 'Shop',  'url' => route($bcLocale . '.product.shop')],
    ];
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
        $bcItems[] = ['name' => implode(', ', $bcCategoryNames)];
    }
@endphp
<x-breadcrumb-schema :items="$bcItems" />
@endpush

@section('content')

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

<div class="shop-header text-center py-5 mt-3">
    <h2 class="fw-bold fs-3 mb-2">Sản Phẩm Casambi</h2>
    <p class="text-muted text-uppercase mb-0" style="font-size: 0.75rem; letter-spacing: 0.25em;">
        Home <span class="mx-1">&gt;</span> Shop
        @if(isset($activeSlugs) && count($activeSlugs))
            @php
                $breadcrumbNames = [];
                if (isset($categories)) {
                    foreach ($categories as $parent) {
                        if (in_array($parent->slug, $activeSlugs)) $breadcrumbNames[] = $parent->name;
                        if ($parent->children) {
                            foreach ($parent->children as $child) {
                                if (in_array($child->slug, $activeSlugs)) $breadcrumbNames[] = $child->name;
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

<div class="container pb-5 mb-5">
    <div class="row gx-lg-5">
        <aside class="col-lg-2 shop-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="shopSidebar" aria-labelledby="shopSidebarLabel">
            <div class="offcanvas-header d-lg-none border-bottom mb-3 px-4 pt-4">
                <h5 class="offcanvas-title fw-bold" id="shopSidebarLabel">Filters</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#shopSidebar" aria-label="Close"></button>
            </div>

            <div class="offcanvas-body flex-column px-4 px-lg-0 pb-4">
                <h5 class="fw-bold mb-4 fs-6 d-none d-lg-block">Filters</h5>

                @php
                    $selectedSlugs = isset($activeSlugs) && is_array($activeSlugs) ? $activeSlugs : [];
                    $sizeCategory  = null;
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
                                <input class="category-checkbox visually-hidden" type="checkbox" id="size-{{ $child->id }}" data-slug="{{ $child->slug }}" data-parent-slug="{{ $sizeCategory->slug }}" {{ $isChecked ? 'checked' : '' }}>
                                <label for="size-{{ $child->id }}" class="size-btn{{ $isChecked ? ' active' : '' }}">{{ $child->name }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if(isset($categories) && $categories->count())
                    @foreach($categories as $parent)
                        @if($sizeCategory && $parent->id === $sizeCategory->id) @continue @endif
                        @php
                            $isColorParent = ($parent->type === 'color')
                                || $parent->children->filter(fn($c) => !empty($c->color_code))->count() > 0;
                        @endphp
                        <div class="filter-group mb-4">
                            <h6 class="filter-title font-xs fw-bold text-uppercase letter-wide text-muted mb-3">{{ $parent->name }}</h6>
                            @if($parent->children && $parent->children->count())
                                @if($isColorParent)
                                    <div class="d-flex flex-wrap gap-2 color-filters">
                                        @foreach($parent->children as $child)
                                            @php
                                                $isChecked = in_array($child->slug, $selectedSlugs);
                                                $colorCode = $child->color_code ?: '#cccccc';
                                                $isLight   = $colorCode === '#ffffff' || strtolower($colorCode) === 'white';
                                            @endphp
                                            <input class="category-checkbox visually-hidden" type="checkbox" id="color-cat-{{ $child->id }}" data-slug="{{ $child->slug }}" data-parent-slug="{{ $parent->slug }}" {{ $isChecked ? 'checked' : '' }}>
                                            <label for="color-cat-{{ $child->id }}" class="color-btn{{ $isChecked ? ' active' : '' }}" title="{{ $child->name }} ({{ $colorCode }})"
                                                @style(['background-color: ' . $colorCode, 'display: block', 'cursor: pointer', 'border: 1px solid #ccc' => $isLight])>
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    @foreach($parent->children as $child)
                                        @php $isChecked = in_array($child->slug, $selectedSlugs); @endphp
                                        <div class="form-check filter-check">
                                            <input class="form-check-input category-checkbox" type="checkbox" id="cat-{{ $child->id }}" data-slug="{{ $child->slug }}" data-parent-slug="{{ $parent->slug }}" {{ $isChecked ? 'checked' : '' }}>
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

                @if(isset($brands) && $brands->count())
                @php $activeBrandSlugsFromQuery = isset($activeFilters['brand']) ? $activeFilters['brand'] : []; @endphp
                <div class="filter-group mb-4">
                    <h6 class="filter-title font-xs fw-bold text-uppercase letter-wide text-muted mb-3">Brand</h6>
                    <div class="d-flex flex-column gap-1">
                        @foreach($brands as $b)
                        @php
                            $isBrandChecked = in_array($b->slug, $activeBrandSlugsFromQuery);
                            $logoSrc = $b->logo ? asset('storage/' . $b->logo) : null;
                        @endphp
                        <div class="form-check filter-check d-flex align-items-center gap-2">
                            <input class="form-check-input category-checkbox" type="checkbox" id="brand-{{ $b->id }}" data-slug="{{ $b->slug }}" data-parent-slug="brand" {{ $isBrandChecked ? 'checked' : '' }}>
                            <label class="form-check-label d-flex align-items-center gap-2 w-100" for="brand-{{ $b->id }}">
                                @if($logoSrc)
                                    <img src="{{ $logoSrc }}" alt="{{ $b->name }}" style="height:16px;width:auto;max-width:40px;object-fit:contain;" loading="lazy" onerror="this.style.display='none'">
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

                <div class="filter-actions mt-5 d-flex flex-column gap-2">
                    <button type="button" class="btn-dark-custom w-100 text-center" id="applyFiltersBtn">Apply Filters</button>
                    <button type="button" class="btn-outline-custom w-100 text-center" id="clearFiltersBtn">Clear All</button>
                </div>
            </div>
        </aside>

        <main class="col-lg-10">
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
                    @include('components.product.card', [
                        'url'      => route(current_locale() . '.product.show', $product->slug),
                        'image'    => $product->product->thumbnail?->url ?? asset('images/casambi/product-placeholder.jpg'),
                        'name'     => $product->name,
                        'price'    => (($product->sale_price > 0 && $product->sale_price < $product->price) ? number_format($product->sale_price, 0, ',', '.') . 'đ' : ($product->price > 0 ? number_format($product->price, 0, ',', '.') . 'đ' : null)),
                        'oldPrice' => ($product->sale_price > 0 && $product->price > 0 && $product->sale_price < $product->price) ? number_format($product->price, 0, ',', '.') . 'đ' : null,
                        'tag'      => ($product->sale_price && $product->sale_price < $product->price ? 'badge-sale' : null),
                        'tagLabel' => ($product->sale_price && $product->sale_price < $product->price ? __('shop.labels.badge_sale') : null),
                    ])
                </div>
                @endforeach
            </div>
            @endif

            @if($products->hasPages())
            <div class="blog-pagination" style="margin-top:40px; margin-bottom:24px;">
                @if($products->onFirstPage())
                    <span class="disabled"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg></span>
                @else
                    <a href="{{ $products->previousPageUrl() }}" rel="prev"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg></a>
                @endif
                @foreach($products->getUrlRange(1, $products->lastPage()) as $page => $url)
                    @if($page == $products->currentPage())
                        <span class="current">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
                @if($products->hasMorePages())
                    <a href="{{ $products->nextPageUrl() }}" rel="next"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg></a>
                @else
                    <span class="disabled"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg></span>
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

    function buildFilterUrl(groups) {
        if (Object.keys(groups).length === 0) return shopBaseUrl;
        var existingParams = new URLSearchParams(window.location.search);
        var params = new URLSearchParams();
        Object.keys(groups).forEach(function (key) {
            existingParams.delete(key);
            params.set(key, groups[key].join(','));
        });
        existingParams.forEach(function (val, key) {
            if (!groups.hasOwnProperty(key)) params.append(key, val);
        });
        return categoryPageUrl + '?' + params.toString();
    }

    var applyBtn = document.getElementById('applyFiltersBtn');
    if (applyBtn) applyBtn.addEventListener('click', function () { window.location.href = buildFilterUrl(getCheckedGroups()); });

    var clearBtn = document.getElementById('clearFiltersBtn');
    if (clearBtn) clearBtn.addEventListener('click', function () { window.location.href = shopBaseUrl; });
});
</script>
@endpush

@endsection
