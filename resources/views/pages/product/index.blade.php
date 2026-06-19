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

    $shopBaseUrl = route(current_locale() . '.product.shop');
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
                <h5 class="offcanvas-title fw-bold" id="shopSidebarLabel">{{ $locale === 'vi' ? 'Bộ lọc' : 'Filters' }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#shopSidebar" aria-label="Close"></button>
            </div>

            <div class="offcanvas-body flex-column px-4 px-lg-0 pb-4">
                <h5 class="fw-bold mb-4 fs-6 d-none d-lg-block">{{ $locale === 'vi' ? 'Bộ lọc' : 'Filters' }}</h5>

                {{-- Filter Groups --}}
                @foreach($filterGroups as $loop_group => $group)
                @php
                    $activeSlugsForGroup = $activeValueSlugs[$group->slug] ?? [];
                    $hasActive           = count($activeSlugsForGroup) > 0;
                    $groupLabel          = ($locale !== 'vi' && $group->name_en) ? $group->name_en : $group->name;
                    $collapseId          = 'fg-collapse-' . $group->id;
                    $isOpen              = $hasActive || $loop_group < 2;
                @endphp
                @if($group->activeValues->count())
                <div class="filter-group mb-1">
                    <button class="filter-group-toggle w-100 d-flex justify-content-between align-items-center"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $collapseId }}"
                            aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                            aria-controls="{{ $collapseId }}">
                        <span class="font-xs fw-bold text-uppercase letter-wide text-muted">{{ $groupLabel }}</span>
                        <svg class="filter-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="collapse {{ $isOpen ? 'show' : '' }} pt-2 pb-3" id="{{ $collapseId }}">
                        @foreach($group->activeValues as $value)
                        @php
                            $isChecked  = in_array($value->slug, $activeSlugsForGroup);
                            $valueLabel = ($locale !== 'vi' && $value->name_en) ? $value->name_en : $value->name;
                        @endphp
                        <div class="form-check filter-check">
                            <input class="form-check-input filter-checkbox"
                                   type="checkbox"
                                   id="fv-{{ $value->id }}"
                                   data-group-slug="{{ $group->slug }}"
                                   data-value-slug="{{ $value->slug }}"
                                   {{ $isChecked ? 'checked' : '' }}>
                            <label class="form-check-label" for="fv-{{ $value->id }}">{{ $valueLabel }}</label>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
                @endforeach

                {{-- Brand --}}
                @if($brands->count())
                <div class="filter-group mb-1">
                    <button class="filter-group-toggle w-100 d-flex justify-content-between align-items-center"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#fg-collapse-brand"
                            aria-expanded="{{ $brandSlug ? 'true' : 'false' }}"
                            aria-controls="fg-collapse-brand">
                        <span class="font-xs fw-bold text-uppercase letter-wide text-muted">Brand</span>
                        <svg class="filter-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="collapse {{ $brandSlug ? 'show' : '' }} pt-2 pb-3" id="fg-collapse-brand">
                    @foreach($brands as $b)
                    @php
                        $isBrandChecked = $brandSlug === $b->slug;
                        $logoSrc = $b->logo ? asset('storage/' . $b->logo) : null;
                    @endphp
                    <div class="form-check filter-check d-flex align-items-center gap-2">
                        <input class="form-check-input brand-checkbox"
                               type="checkbox"
                               id="brand-{{ $b->id }}"
                               data-slug="{{ $b->slug }}"
                               {{ $isBrandChecked ? 'checked' : '' }}>
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
                    </div>{{-- /.collapse --}}
                </div>{{-- /.filter-group --}}
                @endif

                <div class="filter-actions mt-4 d-flex flex-column gap-2">
                    <button type="button" class="btn-dark-custom w-100 text-center" id="applyFiltersBtn">
                        {{ $locale === 'vi' ? 'Áp dụng' : 'Apply Filters' }}
                    </button>
                    <button type="button" class="btn-outline-custom w-100 text-center" id="clearFiltersBtn">
                        {{ $locale === 'vi' ? 'Xoá bộ lọc' : 'Clear All' }}
                    </button>
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
            <div class="row row-cols-2 row-cols-md-3 g-4">
                @foreach($products as $product)
                <div class="col">
                    @include('components.product.card', [
                        'url'      => route(current_locale() . '.product.show', $product->slug),
                        'image'    => $product->product->thumbnail?->url ?? asset('images/casambi/product-placeholder.jpg'),
                        'name'     => $product->name,
                        'category' => $product->product->categories->first()?->translations->first()?->name
                            ?? $product->product->categories->first()?->name
                            ?? $product->product->brand?->name,
                        'price'    => (($product->sale_price > 0 && $product->sale_price < $product->price) ? number_format($product->sale_price, 0, ',', '.') . 'đ' : ($product->price > 0 ? number_format($product->price, 0, ',', '.') . 'đ' : null)),
                        'oldPrice' => ($product->sale_price > 0 && $product->price > 0 && $product->sale_price < $product->price) ? number_format($product->price, 0, ',', '.') . 'đ' : null,
                        'onSale'   => ($product->sale_price > 0 && $product->sale_price < $product->price),
                        'discount' => ($product->sale_price > 0 && $product->price > 0 && $product->sale_price < $product->price) ? round((1 - $product->sale_price / $product->price) * 100) : null,
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
    var shopBaseUrl = '{{ $shopBaseUrl }}';

    function buildFilterUrl() {
        var params = new URLSearchParams();

        // Filter groups: ?protocol=knx,dali-2&voltage=24v-dc
        document.querySelectorAll('.filter-checkbox:checked').forEach(function (el) {
            var groupSlug = el.dataset.groupSlug;
            var valueSlug = el.dataset.valueSlug;
            var existing  = params.get(groupSlug);
            params.set(groupSlug, existing ? existing + ',' + valueSlug : valueSlug);
        });

        // Brand (single)
        var checkedBrand = document.querySelector('.brand-checkbox:checked');
        if (checkedBrand) params.set('brand', checkedBrand.dataset.slug);

        // Keyword
        var q = new URLSearchParams(window.location.search).get('q');
        if (q) params.set('q', q);

        var qs = params.toString();
        return qs ? shopBaseUrl + '?' + qs : shopBaseUrl;
    }

    // Apply
    var applyBtn = document.getElementById('applyFiltersBtn');
    if (applyBtn) applyBtn.addEventListener('click', function () {
        window.location.href = buildFilterUrl();
    });

    // Clear
    var clearBtn = document.getElementById('clearFiltersBtn');
    if (clearBtn) clearBtn.addEventListener('click', function () {
        window.location.href = shopBaseUrl;
    });

    // Brand: uncheck others when one is checked
    document.querySelectorAll('.brand-checkbox').forEach(function (el) {
        el.addEventListener('change', function () {
            if (this.checked) {
                document.querySelectorAll('.brand-checkbox').forEach(function (b) {
                    if (b !== el) b.checked = false;
                });
            }
        });
    });
});
</script>
@endpush

@endsection
