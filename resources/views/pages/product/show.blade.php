@extends('layouts.frontend')

@php
    // Top-level — available in ALL @push and @section blocks
    $locale     = app()->getLocale();
    $isVi       = $locale === 'vi';
    $siteName   = \App\Models\Setting::get('site_name', config('app.name'));
    $returnDays = \App\Models\Setting::get('return_days', 7);
    $suppHours  = \App\Models\Setting::get('support_hours', '24/7');

    // Pricing
    $price          = $translation->price     ?? $product->price;
    $salePrice      = $translation->sale_price ?? $product->sale_price;
    $currency       = $translation->currency   ?? $product->currency ?? 'VND';
    $hasDiscount    = $salePrice > 0 && $salePrice < $price;
    $displayPrice   = $hasDiscount ? $salePrice : $price;
    $currencySymbol = $currency === 'VND' ? 'đ' : ($currency === 'USD' ? '$' : $currency);

    // Stock
    $inStock = $product->stock_quantity > 0;

    // FAQ
    $faqField    = 'faq_items_' . $locale;
    $faqItems    = is_array($product->$faqField ?? null) ? $product->$faqField : [];
    $faqEntities = array_filter(array_map(
        fn($f) => (trim($f['question'] ?? '') && trim($f['answer'] ?? '')) ? $f : null,
        $faqItems
    ));

    // Breadcrumb
    $shopUrl         = route($locale . '.product.shop');
    $firstCat        = $product->categories->first();
    $breadcrumbItems = [
        ['name' => __('common.home'),     'url' => route($locale . '.index')],
        ['name' => __('common.products'), 'url' => $shopUrl],
    ];
    if ($firstCat) {
        $breadcrumbItems[] = ['name' => $firstCat->name, 'url' => route($locale . '.category.show', $firstCat->slug)];
    }
    $breadcrumbItems[] = ['name' => $translation->name];

    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
@endphp

@push('head')

{{-- JSON-LD schemas — sourced from DB (richer: seller, brand logo, aggregateRating, AggregateOffer) --}}
@foreach($jsonldSchemas as $schema)
<script type="application/ld+json">{!! json_encode($schema, $jsonFlags) !!}</script>
@endforeach
@endpush

@section('content')

{{-- Spacer for fixed navbar --}}
<div style="width:100%;height:100px;background-color:#0c0c0c;"></div>

{{-- Breadcrumb --}}
<div class="pd-breadcrumb">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="pd-breadcrumb-list">
                @foreach($breadcrumbItems as $crumb)
                    @if(!$loop->last)
                        <li class="{{ $loop->iteration === count($breadcrumbItems) - 1 ? 'pd-bc-parent' : 'pd-bc-ancestor' }}">
                            <a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a>
                        </li>
                        <li class="pd-bc-sep"><i class="bi bi-chevron-right"></i></li>
                    @else
                        <li class="active">{{ $crumb['name'] }}</li>
                    @endif
                @endforeach
            </ol>
        </nav>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
    MAIN: Gallery + Info
════════════════════════════════════════════════════════════════════════════ --}}
<section id="productDetail" class="pd-section section-pad">
    <div class="container">
        <div class="row g-5 g-lg-6">

            {{-- Gallery --}}
            <div class="col-lg-7 fade-up">
                <div class="pd-gallery">
                    {{-- Thumbnails strip --}}
                    <div class="pd-thumbs" id="pdThumbs">
                        @foreach($product->images as $img)
                            <button class="pd-thumb {{ $loop->first ? 'active' : '' }}"
                                    data-img="{{ $img->url }}"
                                    aria-label="View image {{ $loop->iteration }}">
                                <img src="{{ $img->url }}" alt="{{ $img->alt_text ?? $translation->name }}" />
                            </button>
                        @endforeach
                        @if($product->images->isEmpty())
                            <button class="pd-thumb active"
                                    data-img="{{ asset('images/casambi/product-placeholder.jpg') }}"
                                    aria-label="Placeholder">
                                <img src="{{ asset('images/casambi/product-placeholder.jpg') }}" alt="{{ $translation->name }}" />
                            </button>
                        @endif
                    </div>

                    {{-- Main image --}}
                    <div class="pd-main-img-wrap {{ $product->images->count() <= 1 ? 'w-100' : 'flex-grow-1' }}">
                        @if($product->thumbnail)
                            <img src="{{ $product->thumbnail->url }}"
                                 alt="{{ $product->thumbnail->alt_text ?? $translation->name }}"
                                 id="mainImage">
                        @else
                            <div class="placeholder">
                                <div><i class="fa fa-image"></i><p class="mt-3">{{ __('common.no_image') }}</p></div>
                            </div>
                        @endif
                        <div class="pd-zoom-hint"><i class="bi bi-zoom-in"></i></div>
                    </div>
                </div>

                {{-- Video player (first video) --}}
                @php $firstVideo = $product->videos->first(); @endphp
                @if($firstVideo)
                <div class="pd-video-wrap mt-4">
                    <video controls
                           poster="{{ $firstVideo->thumbnail_url ?? '' }}"
                           style="width:100%;border-radius:8px;background:#000;">
                        <source src="{{ $firstVideo->url }}" type="video/mp4">
                    </video>
                    @if($firstVideo->title)
                        <p class="text-muted small mt-2">{{ $firstVideo->title }}</p>
                    @endif
                </div>
                @endif
            </div>

            {{-- Info panel --}}
            <div class="col-lg-5 fade-up">
                <div class="pd-info">

                    {{-- Categories badges --}}
                    @if($product->categories->isNotEmpty())
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @foreach($product->categories as $cat)
                            <a href="{{ route($locale . '.category.show', $cat->slug) }}"
                               class="badge bg-light text-dark border text-decoration-none"
                               style="font-size:.72rem;letter-spacing:.05em;">{{ $cat->name }}</a>
                        @endforeach
                    </div>
                    @endif

                    <h1 class="pd-title">{{ $translation->name }}</h1>

                    {{-- Meta row: SKU / Brand / Manufacturer --}}
                    <div class="d-flex flex-wrap gap-3 mb-3" style="font-size:.82rem;color:#666;">
                        <span>SKU: <strong>{{ $product->sku }}</strong></span>
                        @if($product->brand)
                            <span>{{ $isVi ? 'Hãng' : 'Brand' }}: <strong>{{ $product->brand->name }}</strong></span>
                        @endif
                        @if($product->manufacturer)
                            <span>{{ $isVi ? 'NSX' : 'Mfr' }}: <strong>{{ $product->manufacturer->name }}</strong>
                                @if($product->manufacturer->country)
                                    <span class="text-muted">({{ $product->manufacturer->country }})</span>
                                @endif
                            </span>
                        @endif
                    </div>

                    {{-- Stock status --}}
                    <div class="pd-rating mb-2">
                        <span class="pd-stock {{ $inStock ? 'stock-in' : 'stock-out' }}">
                            <i class="bi {{ $inStock ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }}"></i>
                            {{ $inStock ? __('common.in_stock') : __('common.out_of_stock') }}
                        </span>
                    </div>

                    {{-- Price --}}
                    @if($displayPrice > 0)
                    <div class="pd-price mb-3">
                        @if($hasDiscount)
                            <span class="pd-price-current">
                                {{ $currencySymbol === 'đ'
                                    ? number_format($salePrice, 0, ',', '.') . 'đ'
                                    : $currencySymbol . number_format($salePrice, 2, '.', ',') }}
                            </span>
                            <span class="pd-price-old ms-2">
                                {{ $currencySymbol === 'đ'
                                    ? number_format($price, 0, ',', '.') . 'đ'
                                    : $currencySymbol . number_format($price, 2, '.', ',') }}
                            </span>
                            @php $discountPct = round((1 - $salePrice / $price) * 100); @endphp
                            <span class="badge bg-danger ms-2" style="font-size:.75rem;">-{{ $discountPct }}%</span>
                        @else
                            <span class="pd-price-current">
                                {{ $currencySymbol === 'đ'
                                    ? number_format($price, 0, ',', '.') . 'đ'
                                    : $currencySymbol . number_format($price, 2, '.', ',') }}
                            </span>
                        @endif
                    </div>
                    @endif

                    <div class="pd-divider"></div>
                    <p class="pd-description">{{ $translation->short_description }}</p>
                    <div class="pd-divider"></div>

                    {{-- CTA --}}
                    <div class="pd-add-row">
                        <button class="btn-dark-custom pd-add-to-cart" onclick="openContactPopup()">
                            <i class="bi bi-telephone-fill me-2"></i>{{ __('common.contact_to_order') }}
                        </button>
                    </div>

                    {{-- Trust badges --}}
                    <div class="pd-trust">
                        <div class="pd-trust-item">
                            <i class="bi bi-patch-check"></i>
                            <div>
                                <span class="pd-trust-title">{{ __('common.trust_authentic') }}</span>
                                <span class="pd-trust-sub">{{ __('common.trust_authentic_sub') }}</span>
                            </div>
                        </div>
                        <div class="pd-trust-item">
                            <i class="bi bi-arrow-repeat"></i>
                            <div>
                                <span class="pd-trust-title">{{ __('common.trust_return', ['days' => $returnDays]) }}</span>
                                <span class="pd-trust-sub">{{ __('common.trust_return_sub') }}</span>
                            </div>
                        </div>
                        <div class="pd-trust-item">
                            <i class="bi bi-shield-check"></i>
                            <div>
                                <span class="pd-trust-title">{{ \App\Models\Setting::get('warranty_info_' . $locale, __('common.trust_warranty')) }}</span>
                                <span class="pd-trust-sub">{{ __('common.trust_warranty_sub', ['hours' => $suppHours]) }}</span>
                            </div>
                        </div>
                        @if($product->brand?->website)
                        <div class="pd-trust-item">
                            <i class="bi bi-globe"></i>
                            <div>
                                <span class="pd-trust-title">{{ $product->brand->name }}</span>
                                <span class="pd-trust-sub">
                                    <a href="{{ $product->brand->website }}" target="_blank" rel="noopener noreferrer" class="text-muted">
                                        {{ parse_url($product->brand->website, PHP_URL_HOST) ?? $product->brand->website }}
                                    </a>
                                </span>
                            </div>
                        </div>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════════════════════
    TABS: Description / Specifications / Videos
════════════════════════════════════════════════════════════════════════════ --}}
<section class="pd-tabs-section">
    <div class="container">
        @php
            $hasSpecs   = $product->attributes->isNotEmpty();
            $hasVideos  = $product->videos->count() > 1; // first video already shown in gallery
            $extraVids  = $hasVideos ? $product->videos->skip(1) : collect();
        @endphp

        <div class="pd-tabs" id="pdTabs">
            <button class="pd-tab active" data-tab="description">
                {{ __('common.description') }}
            </button>
            @if($hasSpecs)
            <button class="pd-tab" data-tab="specs">
                {{ __('common.specifications') }}
            </button>
            @endif
            @if($hasVideos)
            <button class="pd-tab" data-tab="videos">
                <i class="bi bi-play-circle me-1"></i>Video
                <span class="pd-tab-badge">{{ $product->videos->count() }}</span>
            </button>
            @endif
        </div>

        <div class="pd-tab-content" id="pdTabContent">

            {{-- Description --}}
            <div class="pd-tab-panel active" data-panel="description">
                @php
                    $rawDesc = $translation->description ?? null;
                    $descHtml = null;
                    if ($rawDesc) {
                        $decoded = is_array($rawDesc) ? $rawDesc : json_decode($rawDesc, true);
                        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['type'])) {
                            $descHtml = (new \Tiptap\Editor)->setContent($decoded)->getHTML();
                        } else {
                            $descHtml = $rawDesc;
                        }
                    }
                @endphp
                @if($descHtml)
                    <div class="pd-full-description">{!! $descHtml !!}</div>
                @elseif($translation->short_description)
                    <p class="pd-full-description">{{ $translation->short_description }}</p>
                @else
                    <p class="text-muted">{{ __('common.no_description') }}</p>
                @endif
            </div>

            {{-- Specifications --}}
            @if($hasSpecs)
            <div class="pd-tab-panel" data-panel="specs">
                <table class="pd-details-table">
                    @foreach($product->attributes as $attr)
                    <tr>
                        <td>{{ $attr->name }}</td>
                        <td>{{ $attr->value }}{{ $attr->unit ? ' ' . $attr->unit : '' }}</td>
                    </tr>
                    @endforeach
                </table>
            </div>
            @endif

            {{-- Extra videos --}}
            @if($hasVideos)
            <div class="pd-tab-panel" data-panel="videos">
                <div class="row g-4">
                    @foreach($extraVids as $vid)
                    <div class="col-md-6">
                        <video controls poster="{{ $vid->thumbnail_url ?? '' }}"
                               style="width:100%;border-radius:8px;background:#000;">
                            <source src="{{ $vid->url }}" type="video/mp4">
                        </video>
                        @if($vid->title)
                            <p class="text-muted small mt-2 mb-0">{{ $vid->title }}</p>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════════════════════
    RELATED PRODUCTS
════════════════════════════════════════════════════════════════════════════ --}}
@if($relatedProducts->isNotEmpty())
<section class="related-products-section">
    <div class="container">
        <h2 class="related-products-title">{{ __('common.related_products') }}</h2>
        <div class="rp-slider-wrap">
            <button class="products-arrow rp-arrow-prev" id="rpPrev" aria-label="Previous">&#8592;</button>
            <div class="rp-track-wrap">
                <div class="rp-track" id="rpTrack">
                    @foreach($relatedProducts as $rp)
                    <div class="rp-item">
                        @php
                            $rpPrice    = $rp->sale_price > 0 && $rp->sale_price < $rp->price ? $rp->sale_price : $rp->price;
                            $rpOldPrice = $rp->sale_price > 0 && $rp->sale_price < $rp->price ? $rp->price : null;
                        @endphp
                        @include('components.product.card', [
                            'url'      => route(current_locale() . '.product.show', $rp->slug),
                            'image'    => $rp->product->thumbnail?->url ?? asset('images/casambi/product-placeholder.jpg'),
                            'name'     => $rp->name,
                            'price'    => $rpPrice > 0 ? number_format($rpPrice, 0, ',', '.') . 'đ' : null,
                            'oldPrice' => $rpOldPrice ? number_format($rpOldPrice, 0, ',', '.') . 'đ' : null,
                            'tag'      => null,
                            'tagLabel' => null,
                        ])
                    </div>
                    @endforeach
                </div>
            </div>
            <button class="products-arrow rp-arrow-next" id="rpNext" aria-label="Next">&#8594;</button>
        </div>
    </div>
</section>
@endif

{{-- ═══════════════════════════════════════════════════════════════════════════
    FAQ
════════════════════════════════════════════════════════════════════════════ --}}
@if(! empty($faqEntities))
<section class="pd-faq-section">
    <div class="container">
        <h2 class="pd-faq-title">{{ __('common.faq') }}</h2>
        <div class="pd-faq-list" id="pdFaqList">
            @foreach($faqItems as $index => $faq)
                @if(!empty($faq['question']) && !empty($faq['answer']))
                <div class="pd-faq-item {{ $index === 0 ? 'active' : '' }}" id="faq-{{ $index }}">
                    <button class="pd-faq-question"
                            aria-expanded="{{ $index === 0 ? 'true' : 'false' }}"
                            aria-controls="faq-answer-{{ $index }}"
                            data-faq-index="{{ $index }}"
                            onclick="toggleFaq(this.dataset.faqIndex)">
                        <span>{{ $faq['question'] }}</span>
                        <i class="bi bi-chevron-down pd-faq-icon"></i>
                    </button>
                    <div class="pd-faq-answer" id="faq-answer-{{ $index }}" role="region">
                        <p>{{ $faq['answer'] }}</p>
                    </div>
                </div>
                @endif
            @endforeach
        </div>
    </div>
</section>
@endif

@endsection

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", function () {
    // ── Gallery thumbs ──────────────────────────────────────────────────────
    const thumbs    = document.querySelectorAll(".pd-thumb");
    const mainImage = document.getElementById("mainImage");
    thumbs.forEach(thumb => {
        thumb.addEventListener("click", function () {
            const newImg = this.getAttribute("data-img");
            if (mainImage && newImg) mainImage.src = newImg;
            thumbs.forEach(t => t.classList.remove("active"));
            this.classList.add("active");
        });
    });

    // ── Tabs ────────────────────────────────────────────────────────────────
    document.querySelectorAll('.pd-tab').forEach(tab => {
        tab.addEventListener('click', function () {
            const target = this.dataset.tab;
            document.querySelectorAll('.pd-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.pd-tab-panel').forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            const panel = document.querySelector('.pd-tab-panel[data-panel="' + target + '"]');
            if (panel) panel.classList.add('active');
        });
    });

    // ── Related products slider ─────────────────────────────────────────────
    const track   = document.getElementById('rpTrack');
    const btnPrev = document.getElementById('rpPrev');
    const btnNext = document.getElementById('rpNext');
    if (track && btnPrev && btnNext) {
        let current = 0;
        function getVisible() {
            if (window.innerWidth >= 992) return 4;
            if (window.innerWidth >= 576) return 2;
            return 1;
        }
        function maxIndex() { return Math.max(0, track.children.length - getVisible()); }
        function slide() {
            current = Math.min(current, maxIndex());
            const itemW = track.children[0]?.offsetWidth || 0;
            const gap   = parseInt(getComputedStyle(track).gap) || 24;
            track.style.transform = `translateX(-${current * (itemW + gap)}px)`;
            btnPrev.disabled = current === 0;
            btnNext.disabled = current >= maxIndex();
        }
        btnPrev.addEventListener('click', () => { current = Math.max(0, current - 1); slide(); });
        btnNext.addEventListener('click', () => { current = Math.min(maxIndex(), current + 1); slide(); });
        window.addEventListener('resize', slide);
        slide();
    }
});

function toggleFaq(index) {
    const item   = document.getElementById('faq-' + index);
    if (!item) return;
    const btn    = item.querySelector('.pd-faq-question');
    const isOpen = item.classList.contains('active');
    document.querySelectorAll('.pd-faq-item').forEach(el => {
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
