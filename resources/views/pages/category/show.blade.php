@php
    $locale  = app()->getLocale();
    $appName = config('app.name');

    $ogImage    = $fallbackImage ?? asset('assets/images/casambi/casambiblack.svg');
    $shopBaseUrl = route(current_locale() . '.category.show', $translation->slug);
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

        {{-- Sidebar --}}
        <aside class="col-lg-2 shop-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="shopSidebar" aria-labelledby="shopSidebarLabel">
            <div class="offcanvas-header d-lg-none border-bottom mb-3 px-4 pt-4">
                <h5 class="offcanvas-title fw-bold" id="shopSidebarLabel">{{ $locale === 'vi' ? 'Bộ lọc' : 'Filters' }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#shopSidebar" aria-label="Close"></button>
            </div>

            <div class="offcanvas-body flex-column px-4 px-lg-0 pb-4">
                <h5 class="fw-bold mb-4 fs-6 d-none d-lg-block">{{ $locale === 'vi' ? 'Bộ lọc' : 'Filters' }}</h5>

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
                            $logoSrc = $b->logo ? asset('storage/' . $b->logo) : null;
                        @endphp
                        <div class="form-check filter-check d-flex align-items-center gap-2">
                            <input class="form-check-input brand-checkbox"
                                   type="checkbox"
                                   id="brand-{{ $b->id }}"
                                   data-slug="{{ $b->slug }}"
                                   {{ $brandSlug === $b->slug ? 'checked' : '' }}>
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

        {{-- Product grid --}}
        <main class="col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                <span class="font-xs fw-bold text-uppercase letter-wide text-muted">
                    {{ $products->total() }} {{ $locale === 'vi' ? 'sản phẩm' : 'products' }}
                </span>
                <button class="btn btn-outline-dark btn-sm rounded-0 text-uppercase letter-wide fw-bold font-xs px-3 d-lg-none"
                        type="button" data-bs-toggle="offcanvas" data-bs-target="#shopSidebar">
                    <i class="bi bi-sliders me-2"></i> {{ $locale === 'vi' ? 'Lọc' : 'Filter' }}
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
            <div class="row row-cols-2 row-cols-md-3 g-3 g-lg-4 gx-lg-5">
                @foreach($products as $product)
                <div class="col">
                    @include('components.product.card', [
                        'url'      => route(current_locale() . '.product.show', $product->slug),
                        'image'    => $product->product->thumbnail?->url ?? asset('images/casambi/product-placeholder.jpg'),
                        'name'     => $product->name,
                        'price'    => (($product->sale_price > 0 && $product->sale_price < $product->price)
                            ? number_format($product->sale_price, 0, ',', '.') . 'đ'
                            : ($product->price > 0 ? number_format($product->price, 0, ',', '.') . 'đ' : null)),
                        'oldPrice' => ($product->sale_price > 0 && $product->price > 0 && $product->sale_price < $product->price)
                            ? number_format($product->price, 0, ',', '.') . 'đ' : null,
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var shopBaseUrl = '{{ $shopBaseUrl }}';

    function buildFilterUrl() {
        var params = new URLSearchParams();

        document.querySelectorAll('.filter-checkbox:checked').forEach(function (el) {
            var groupSlug = el.dataset.groupSlug;
            var valueSlug = el.dataset.valueSlug;
            var existing  = params.get(groupSlug);
            params.set(groupSlug, existing ? existing + ',' + valueSlug : valueSlug);
        });

        var checkedBrand = document.querySelector('.brand-checkbox:checked');
        if (checkedBrand) params.set('brand', checkedBrand.dataset.slug);

        var q = new URLSearchParams(window.location.search).get('q');
        if (q) params.set('q', q);

        var qs = params.toString();
        return qs ? shopBaseUrl + '?' + qs : shopBaseUrl;
    }

    var applyBtn = document.getElementById('applyFiltersBtn');
    if (applyBtn) applyBtn.addEventListener('click', function () {
        window.location.href = buildFilterUrl();
    });

    var clearBtn = document.getElementById('clearFiltersBtn');
    if (clearBtn) clearBtn.addEventListener('click', function () {
        window.location.href = shopBaseUrl;
    });

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
