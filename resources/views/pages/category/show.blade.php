@php
    $locale  = app()->getLocale();
    $appName = config('app.name');

    $ogImage           = $fallbackImage ?? asset('assets/images/casambi/casambiblack.svg');
    $shopBaseUrl       = route(current_locale() . '.category.show', $translation->slug);
    $activeFilterCount = array_sum(array_map('count', $activeValueSlugs)) + ($brandSlug ? 1 : 0);
@endphp

@extends('layouts.frontend')

@push('head')
@foreach($jsonldSchemas as $schema)
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endforeach
@endpush

@section('content')

{{-- ── Hero ────────────────────────────────────────────────────────────── --}}
<section class="shop-hero position-relative overflow-hidden">
    @if($category->image_path)
        <img src="{{ asset('storage/' . $category->image_path) }}"
             alt="{{ $translation->name }}"
             class="shop-hero-bg w-100 h-100 position-absolute top-0 start-0 object-fit-cover"
             style="z-index:0; filter:brightness(0.55);">
    @else
        <div class="shop-hero-bg w-100 h-100 position-absolute top-0 start-0" style="z-index:0; background:#111;"></div>
    @endif
    <div class="container position-relative h-100 d-flex align-items-center" style="z-index:2;">
        <div>
            <p class="font-xs text-white fw-bold letter-wide m-0" style="margin-bottom:6px !important;">
                {{ $locale === 'vi' ? 'GIẢI PHÁP' : 'SOLUTION' }}
            </p>
            <h1 class="shop-hero-title text-white fw-black m-0"
                style="font-size:clamp(2rem,6vw,5rem); line-height:1.05; letter-spacing:-0.01em;">
                {{ $translation->name }}
            </h1>
            @if($translation->description)
            <p class="text-white-50 mt-3 mb-0" style="max-width:520px; font-size:0.95rem; line-height:1.6;">
                {{ $translation->description }}
            </p>
            @endif
        </div>
    </div>
</section>

{{-- ── Breadcrumb ───────────────────────────────────────────────────────── --}}
<div class="shop-header text-center py-4 mt-3">
    <p class="text-muted text-uppercase mb-0" style="font-size:0.75rem; letter-spacing:0.25em;">
        <a href="{{ route(current_locale() . '.index') }}" class="text-muted text-decoration-none">
            {{ $locale === 'vi' ? 'Trang chủ' : 'Home' }}
        </a>
        <span class="mx-1">&gt;</span>
        <a href="{{ route(current_locale() . '.product.category') }}" class="text-muted text-decoration-none">
            {{ $locale === 'vi' ? 'Giải pháp' : 'Solutions' }}
        </a>
        <span class="mx-1">&gt;</span>
        {{ $translation->name }}
    </p>
</div>

{{-- ── Products + Sidebar ───────────────────────────────────────────────── --}}
<div class="container pb-5">
    <div class="row gx-lg-5">

        {{-- Desktop Sidebar (lg+) --}}
        <div class="d-none d-lg-block col-lg-2">
            <aside id="shopSidebarDesktop">
                <h5 class="fw-bold mb-4 fs-6">{{ $locale === 'vi' ? 'Bộ lọc' : 'Filters' }}</h5>
                @include('partials.filter-panel', [
                    'idPrefix'    => '',
                    'applyBtnId'  => 'applyFiltersBtn',
                    'clearBtnId'  => 'clearFiltersBtn',
                    'showActions' => true,
                ])
            </aside>
        </div>

        {{-- Product grid --}}
        <main class="col-12 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                <span class="font-xs fw-bold text-uppercase letter-wide text-muted">
                    {{ $products->total() }} {{ $locale === 'vi' ? 'sản phẩm' : 'products' }}
                </span>
                {{-- Mobile filter button --}}
                <button class="mob-filter-btn d-lg-none" type="button" id="mobileFilterBtn" aria-haspopup="dialog" aria-expanded="false">
                    <i class="bi bi-sliders me-1"></i>
                    {{ $locale === 'vi' ? 'Lọc' : 'Filter' }}
                    @if($activeFilterCount > 0)
                    <span class="mob-filter-badge">{{ $activeFilterCount }}</span>
                    @endif
                </button>
            </div>

            @if($products->isEmpty())
            <div class="d-flex flex-column align-items-center justify-content-center py-5 w-100 text-center">
                <i class="fal fa-box-open fs-1 text-muted mb-3"></i>
                <p class="text-muted mb-0">
                    {{ $locale === 'vi' ? 'Không tìm thấy sản phẩm nào.' : 'No products found.' }}
                </p>
            </div>
            @else
            <div class="row row-cols-2 row-cols-md-3 g-4">
                @foreach($products as $product)
                <div class="col d-flex">
                    @include('components.product.card', [
                        'url'      => route(current_locale() . '.product.show', $product->slug),
                        'image'    => $product->product->thumbnail?->url ?? asset('images/casambi/product-placeholder.jpg'),
                        'name'     => $product->name,
                        'category' => $product->product->categories->first()?->translations->first()?->name
                            ?? $product->product->categories->first()?->name
                            ?? $product->product->brand?->name,
                        'price'    => (($product->sale_price > 0 && $product->sale_price < $product->price)
                            ? format_price($product->sale_price, $product->currency)
                            : ($product->price > 0 ? format_price($product->price, $product->currency) : null)),
                        'oldPrice' => ($product->sale_price > 0 && $product->price > 0 && $product->sale_price < $product->price)
                            ? format_price($product->price, $product->currency) : null,
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

{{-- ── Rich content ─────────────────────────────────────────────────────── --}}
@if($richContentHtml)
<div class="category-rich-content">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="rich-content-body">
                    {!! $richContentHtml !!}
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ── FAQ ──────────────────────────────────────────────────────────────── --}}
@if(! empty($faqEntities))
<section class="pd-faq-section">
    <div class="container">
        <div class="row justify-content-center">
        <div class="col-lg-8">
        <h2 class="pd-faq-title">{{ __('common.faq') }}</h2>
        <div class="pd-faq-list" id="catFaqList">
            @foreach($faqItems as $index => $faq)
                @if(!empty($faq['question']) && !empty($faq['answer']))
                <div class="pd-faq-item {{ $index === 0 ? 'active' : '' }}" id="cat-faq-{{ $index }}">
                    <button class="pd-faq-question"
                            aria-expanded="{{ $index === 0 ? 'true' : 'false' }}"
                            aria-controls="cat-faq-answer-{{ $index }}"
                            data-faq-index="{{ $index }}"
                            onclick="toggleCatFaq(this.dataset.faqIndex)">
                        <span>{{ $faq['question'] }}</span>
                        <i class="bi bi-chevron-down pd-faq-icon"></i>
                    </button>
                    <div class="pd-faq-answer" id="cat-faq-answer-{{ $index }}" role="region">
                        <p>{{ $faq['answer'] }}</p>
                    </div>
                </div>
                @endif
            @endforeach
        </div>
        </div>
        </div>
    </div>
</section>
@endif

{{-- ── Mobile Filter Overlay ────────────────────────────────────────────── --}}
{{-- JS sẽ append element này thẳng vào <body> để tránh bị trap bởi parent transform --}}
<div id="mobFilterOverlay" class="mob-filter-overlay" role="dialog" aria-modal="true" aria-hidden="true"
     aria-label="{{ $locale === 'vi' ? 'Bộ lọc sản phẩm' : 'Product filters' }}">
    {{-- Sticky header --}}
    <div class="mob-filter-header">
        <span class="fw-bold">
            {{ $locale === 'vi' ? 'Bộ lọc' : 'Filters' }}
            @if($activeFilterCount > 0)
            <span class="badge bg-dark rounded-pill ms-1" style="font-size:10px;vertical-align:middle;">{{ $activeFilterCount }}</span>
            @endif
        </span>
        {{-- Floating close button — luôn hiện, không bị scroll che --}}
        <button class="mob-filter-fab" id="mobFilterClose" aria-label="{{ $locale === 'vi' ? 'Đóng' : 'Close' }}">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    {{-- Scrollable filter values wrapper --}}
    <div class="mob-filter-body" id="mobFilterBody">
        @include('partials.filter-panel', [
            'idPrefix'    => 'mob-',
            'showActions' => false,
        ])
    </div>
    {{-- Sticky footer --}}
    <div class="mob-filter-footer">
        <button type="button" class="btn-outline-custom" id="mobClearFiltersBtn">
            {{ $locale === 'vi' ? 'Xoá bộ lọc' : 'Clear All' }}
        </button>
        <button type="button" class="btn-dark-custom" id="mobApplyFiltersBtn">
            {{ $locale === 'vi' ? 'Áp dụng' : 'Apply' }}
        </button>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var shopBaseUrl = '{{ $shopBaseUrl }}';

    function buildFilterUrl(container) {
        var params = new URLSearchParams();
        container.querySelectorAll('.filter-checkbox:checked').forEach(function (el) {
            var groupSlug = el.dataset.groupSlug;
            var valueSlug = el.dataset.valueSlug;
            var existing  = params.get(groupSlug);
            params.set(groupSlug, existing ? existing + ',' + valueSlug : valueSlug);
        });
        var checkedBrand = container.querySelector('.brand-checkbox:checked');
        if (checkedBrand) params.set('brand', checkedBrand.dataset.slug);
        var q = new URLSearchParams(window.location.search).get('q');
        if (q) params.set('q', q);
        var qs = params.toString();
        return qs ? shopBaseUrl + '?' + qs : shopBaseUrl;
    }

    // ── Desktop filter ────────────────────────────────────────────────────
    var desktopSidebar = document.getElementById('shopSidebarDesktop');

    var applyBtn = document.getElementById('applyFiltersBtn');
    if (applyBtn && desktopSidebar) {
        applyBtn.addEventListener('click', function () {
            window.location.href = buildFilterUrl(desktopSidebar);
        });
    }

    var clearBtn = document.getElementById('clearFiltersBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            window.location.href = shopBaseUrl;
        });
    }

    if (desktopSidebar) {
        desktopSidebar.querySelectorAll('.brand-checkbox').forEach(function (el) {
            el.addEventListener('change', function () {
                if (this.checked) {
                    desktopSidebar.querySelectorAll('.brand-checkbox').forEach(function (b) {
                        if (b !== el) b.checked = false;
                    });
                }
            });
        });
    }

    // ── Mobile filter overlay ─────────────────────────────────────────────
    var overlay   = document.getElementById('mobFilterOverlay');
    var mobBody   = document.getElementById('mobFilterBody');
    var openBtn   = document.getElementById('mobileFilterBtn');
    var closeBtn  = document.getElementById('mobFilterClose');
    var mobApply  = document.getElementById('mobApplyFiltersBtn');
    var mobClear  = document.getElementById('mobClearFiltersBtn');
    var header    = document.getElementById('header-sticky');
    var scrollPos = 0;

    // Move overlay thẳng vào <body> — tránh bị trap bởi parent transform/stacking context
    if (overlay && overlay.parentNode !== document.body) {
        document.body.appendChild(overlay);
    }

    function openMobileFilter() {
        if (!overlay) return;
        scrollPos = window.scrollY || window.pageYOffset;
        document.documentElement.style.setProperty('--mob-scroll-pos', '-' + scrollPos + 'px');
        document.body.classList.add('mob-filter-open');
        if (header) header.style.visibility = 'hidden';
        overlay.classList.add('active');
        overlay.setAttribute('aria-hidden', 'false');
        if (openBtn) openBtn.setAttribute('aria-expanded', 'true');
    }

    function closeMobileFilter() {
        if (!overlay) return;
        document.body.classList.remove('mob-filter-open');
        document.documentElement.style.removeProperty('--mob-scroll-pos');
        window.scrollTo(0, scrollPos);
        if (header) header.style.visibility = '';
        overlay.classList.remove('active');
        overlay.setAttribute('aria-hidden', 'true');
        if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
    }

    if (openBtn)  openBtn.addEventListener('click', openMobileFilter);
    if (closeBtn) closeBtn.addEventListener('click', closeMobileFilter);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay && overlay.classList.contains('active')) closeMobileFilter();
    });

    if (mobApply && mobBody) {
        mobApply.addEventListener('click', function () {
            closeMobileFilter();
            window.location.href = buildFilterUrl(mobBody);
        });
    }

    if (mobClear) {
        mobClear.addEventListener('click', function () {
            closeMobileFilter();
            window.location.href = shopBaseUrl;
        });
    }

    if (mobBody) {
        mobBody.querySelectorAll('.brand-checkbox').forEach(function (el) {
            el.addEventListener('change', function () {
                if (this.checked) {
                    mobBody.querySelectorAll('.brand-checkbox').forEach(function (b) {
                        if (b !== el) b.checked = false;
                    });
                }
            });
        });
    }
});
</script>
@endpush

@push('scripts')
<script>
function toggleCatFaq(index) {
    const item   = document.getElementById('cat-faq-' + index);
    if (!item) return;
    const btn    = item.querySelector('.pd-faq-question');
    const isOpen = item.classList.contains('active');
    document.querySelectorAll('#catFaqList .pd-faq-item').forEach(el => {
        el.classList.remove('active');
        el.querySelector('.pd-faq-question').setAttribute('aria-expanded', 'false');
    });
    if (!isOpen) {
        item.classList.add('active');
        btn.setAttribute('aria-expanded', 'true');
    }
}
</script>
@endpush

@endsection
