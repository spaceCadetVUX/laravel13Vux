@extends('front.layouts.frontend', [
    'seo' => [
        'title'           => $product->meta_title ?? $product->name . ' - ' . config('app.name'),
        'description'     => $product->meta_description ?? Str::limit($product->short_description, 160),
        'keywords'        => $product->meta_keywords ?? $product->brand . ', ' . $product->name,
        'image'           => $product->og_image
            ? (str_starts_with($product->og_image, 'http') ? $product->og_image : asset($product->og_image))
            : ($product->image_url
                ? (str_starts_with($product->image_url, 'http') ? $product->image_url : asset($product->image_url))
                : asset('images/casambi/0-hero-banner.jpg')),
        'og_title'        => $product->og_title ?? $product->meta_title ?? $product->name,
        'og_description'  => $product->og_description ?? $product->meta_description ?? Str::limit($product->short_description, 160),
        'canonical'       => $product->canonical_url ?? url(request()->path()),
        'robots'          => ($product->indexable ?? true) ? 'index, follow' : 'noindex, follow',
        'type'            => 'product',
        'hreflangs'       => [
            'en' => switch_locale_url('en'),
            'vi' => switch_locale_url('vi'),
        ],
    ]
])

{{-- Product + BreadcrumbList Schema --}}
@push('head')
@php
    $locale     = app()->getLocale();
    $productUrl = route($locale . '.product.show', $product->slug);
    $shopUrl    = $locale === 'vi' ? url('vi/san-pham') : url('en/products');

    /* ── Images ── */
    $images = [];
    if ($product->image_url_full) {
        $images[] = $product->image_url_full;
    }
    if ($product->gallery_images) {
        foreach ($product->gallery_images as $img) {
            $raw = is_array($img) ? ($img['url'] ?? '') : $img;
            if ($raw) {
                $images[] = str_starts_with($raw, 'http') ? $raw : asset($raw);
            }
        }
    }
    // Fallback khi không có ảnh nào
    if (empty($images)) {
        $images[] = asset('assets/img/default-product.jpg');
    }

    /* ── additionalProperty: specifications + warranty ── */
    $additionalProps = [];
    if ($product->specifications) {
        foreach ($product->specifications as $key => $val) {
            if (!empty($val)) {
                $additionalProps[] = ['@type' => 'PropertyValue', 'name' => $key, 'value' => (string) $val];
            }
        }
    }
    if ($product->warranty_period) {
        $additionalProps[] = [
            '@type' => 'PropertyValue',
            'name'  => $locale === 'vi' ? 'Bảo hành' : 'Warranty',
            'value' => $product->warranty_period,
        ];
    }

    /* ── Parse weight string → value + unitCode ── */
    $weightSchema = null;
    if ($product->weight) {
        preg_match('/^([\d.]+)\s*(kg|g|lb|lbs|gram|grams)?/i', trim((string) $product->weight), $wm);
        if (!empty($wm[1])) {
            $weightUnit = strtolower($wm[2] ?? '');
            $unitCode   = match(true) {
                str_contains($weightUnit, 'lb')   => 'LBR',
                $weightUnit === 'g' || str_contains($weightUnit, 'gram') => 'GRM',
                default => 'KGM',
            };
            $weightSchema = ['@type' => 'QuantitativeValue', 'value' => (float) $wm[1], 'unitCode' => $unitCode];
        }
    }

    /* ── Product Schema ── */
    $productSchema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Product',
        'name'        => $product->name,
        'url'         => $productUrl,
        'image'       => $images,
        'description' => Str::limit(strip_tags($product->meta_description ?? $product->short_description ?? ''), 5000),
        'sku'         => $product->sku,
        'dateModified' => $product->updated_at->toIso8601String(),
    ];

    // brand — chỉ thêm khi có giá trị
    $brandName = $product->brand_name ?? $product->brand ?? null;
    if ($brandName) $productSchema['brand'] = ['@type' => 'Brand', 'name' => $brandName];

    if ($product->catalog)  $productSchema['mpn']      = $product->catalog;
    if ($product->material) $productSchema['material'] = $product->material;
    if ($product->color)    $productSchema['color']    = $product->color;
    if ($weightSchema)      $productSchema['weight']   = $weightSchema;

    // countryOfOrigin — chỉ dùng field `origin` (tên nước thực sự), không dùng manufacturer_country (có thể là tên hãng)
    if ($product->origin)   $productSchema['countryOfOrigin'] = ['@type' => 'Country', 'name' => $product->origin];
    if ($additionalProps)               $productSchema['additionalProperty'] = $additionalProps;

    /* ── FAQPage Schema ── */
    $faqSchema = null;
    if (!empty($product->faqs) && is_array($product->faqs)) {
        $faqEntities = [];
        foreach ($product->faqs as $faq) {
            $q = trim($faq['question'] ?? '');
            $a = trim($faq['answer']   ?? '');
            if ($q && $a) {
                $faqEntities[] = [
                    '@type'          => 'Question',
                    'name'           => $q,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text'  => $a,
                    ],
                ];
            }
        }
        if (!empty($faqEntities)) {
            $faqSchema = [
                '@context'   => 'https://schema.org',
                '@type'      => 'FAQPage',
                'mainEntity' => $faqEntities,
            ];
        }
    }

    /* ── BreadcrumbList ── */
    $firstCat        = $product->categories()->first();
    $breadcrumbItems = [
        ['name' => $locale === 'vi' ? 'Trang chủ' : 'Home',    'url' => url('/')],
        ['name' => $locale === 'vi' ? 'Sản phẩm' : 'Products', 'url' => $shopUrl],
    ];
    if ($firstCat) {
        $breadcrumbItems[] = ['name' => $firstCat->name, 'url' => route($locale . '.product.category', ['category' => $firstCat->slug])];
    }
    $breadcrumbItems[] = ['name' => $product->name]; // trang hiện tại — không cần url

    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
@endphp
<script type="application/ld+json">{!! json_encode($productSchema, $jsonFlags) !!}</script>
@if($faqSchema)
<script type="application/ld+json">{!! json_encode($faqSchema, $jsonFlags) !!}</script>
@endif
<x-breadcrumb-schema :items="$breadcrumbItems" />
@endpush


@section('content')

    <div style="width:100%;height:100px;background-color:#0c0c0c;"></div>

    <!-- ==========================================
        BREADCRUMB
        ========================================== -->
    <div class="pd-breadcrumb">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="pd-breadcrumb-list">
                @foreach($breadcrumbItems as $i => $crumb)
                    @if(!$loop->last)
                        @php $isParent = ($loop->iteration === count($breadcrumbItems) - 1); @endphp
                        <li class="{{ $isParent ? 'pd-bc-parent' : 'pd-bc-ancestor' }}">
                            <a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a>
                        </li>
                        <li class="pd-bc-sep{{ $isParent ? ' pd-bc-parent-sep' : '' }}"><i class="bi bi-chevron-right"></i></li>
                    @else
                        <li class="active">{{ $crumb['name'] }}</li>
                    @endif
                @endforeach
            </ol>
        </nav>
    </div>
    </div>


    <!-- ==========================================
        PRODUCT DETAIL SECTION
        ========================================== -->
    <section id="productDetail" class="pd-section section-pad">
        <div class="container">
            <div class="row g-5 g-lg-6">

            <!-- ── LEFT: Image Gallery ── -->
            <div class="col-lg-7 fade-up">
                <div class="pd-gallery">
                <div class="pd-thumbs" id="pdThumbs">
                    @if($product->image_url)
                        <button class="pd-thumb active" data-img="{{ str_starts_with($product->image_url, 'http') ? $product->image_url : asset($product->image_url) }}" aria-label="View image 1">
                            <img src="{{ str_starts_with($product->image_url, 'http') ? $product->image_url : asset($product->image_url) }}" alt="{{ $product->image_alt ?? $product->name }}" />
                        </button>
                    @endif
                    @if($product->gallery_images)
                        @foreach($product->gallery_images as $image)
                            @php
                                $imageUrl = is_array($image) ? ($image['url'] ?? '') : $image;
                                $imageAlt = is_array($image) ? ($image['alt'] ?? $product->image_alt ?? $product->name) : ($product->image_alt ?? $product->name);
                            @endphp
                            @if(!empty($imageUrl))
                                <button class="pd-thumb" data-img="{{ str_starts_with($imageUrl, 'http') ? $imageUrl : asset($imageUrl) }}" aria-label="View image {{ $loop->iteration + 1 }}">
                                    <img src="{{ str_starts_with($imageUrl, 'http') ? $imageUrl : asset($imageUrl) }}" alt="{{ $imageAlt }}" />
                                </button>
                            @endif
                        @endforeach
                    @endif
                </div>

                <!-- Main Image -->
                    <div class="pd-main-img-wrap @if(!$product->gallery_images || count($product->gallery_images) == 0) w-100 @else flex-grow-1 @endif">
                        @if($product->image_url)
                            <img src="{{ str_starts_with($product->image_url, 'http') ? $product->image_url : asset($product->image_url) }}" 
                                alt="{{ $product->image_alt ?? $product->name }}" 
                                id="mainImage">
                        @else
                            <div class="placeholder">
                                <div>
                                    <i class="fa fa-image"></i>
                                    <p class="mt-3">the img not avilable</p>
                                </div>
                            </div>
                        @endif
                        <!-- Badge -->
                        <span class="pd-img-badge">New</span>
                        <!-- Zoom hint -->
                        <div class="pd-zoom-hint"><i class="bi bi-zoom-in"></i></div>
                    </div>
                </div>
            </div>

            <!-- ── RIGHT: Product Info ── -->
            <div class="col-lg-5 fade-up">
                <div class="pd-info">
                <!-- Brand + Wishlist -->
                <!-- <div class="pd-info-top">
                    <span class="pd-brand">{{ $product->brand }}</span>
                    <button class="pd-wishlist-btn" aria-label="Add to wishlist" id="pdWishlistBtn">
                    <i class="bi bi-heart"></i>
                    </button>
                </div> -->
                <!-- Product name -->
                <h1 class="pd-title">{{ $product->name }}</h1>
                <p class="text-muted mb-1">SKU: {{ $product->sku }}</p>
                <!-- Rating -->
                <div class="pd-rating">
                    @php
                    $status = [
                        'in_stock' => [
                            'label' => 'In Stock',
                            'icon' => 'bi-check-circle-fill',
                            'class' => 'stock-in'
                        ],
                        'on_backorder' => [
                            'label' => 'On Backorder',
                            'icon' => 'bi-clock-fill',
                            'class' => 'stock-backorder'
                        ],
                        'out_of_stock' => [
                            'label' => 'Out of Stock',
                            'icon' => 'bi-x-circle-fill',
                            'class' => 'stock-out'
                        ],
                    ][$product->stock_status] ?? [
                        'label' => 'Unknown',
                        'icon' => 'bi-question-circle-fill',
                        'class' => 'stock-unknown'
                    ];
                    @endphp
                    <span class="pd-stock {{ $status['class'] }}">
                        <i class="bi {{ $status['icon'] }}"></i>
                        {{ $status['label'] }}
                    </span>
                </div>
                <!-- Price -->
                @php
                    $hasPrice     = ($product->price ?? 0) > 0;
                    $hasSalePrice = ($product->sale_price ?? 0) > 0;
                    $showDiscount = $hasPrice && $hasSalePrice && $product->sale_price < $product->price;
                @endphp
                {{-- Price hidden intentionally; contact us for pricing --}}

                <!-- Flash Sale Countdown -->
                <!-- <div class="pd-flash-sale">
                    <span class="pd-flash-label"><i class="bi bi-lightning-charge-fill"></i> Flash Sale ends in:</span>
                    <div class="pd-flash-countdown">
                    <div class="pd-cd-unit">
                        <span class="pd-cd-num" id="pd-cd-hours">00</span>
                        <span class="pd-cd-text">Hrs</span>
                    </div>
                    <span class="pd-cd-sep">:</span>
                    <div class="pd-cd-unit">
                        <span class="pd-cd-num" id="pd-cd-mins">05</span>
                        <span class="pd-cd-text">Min</span>
                    </div>
                    <span class="pd-cd-sep">:</span>
                    <div class="pd-cd-unit">
                        <span class="pd-cd-num" id="pd-cd-secs">30</span>
                        <span class="pd-cd-text">Sec</span>
                    </div>
                    </div>
                </div> -->

                <!-- Divider -->
                <div class="pd-divider"></div>

                <!-- Description -->
                <p class="pd-description">
                    {{ $product->short_description }}
                </p>

                <!-- Size Selector -->
                <!-- <div class="pd-option-group">
                    <div class="pd-option-label">
                    Size: <span class="pd-selected-size" id="pdSelectedSize">M</span>
                    <a href="#" class="pd-size-guide">Size Guide <i class="bi bi-rulers"></i></a>
                    </div>
                    <div class="pd-size-grid" id="pdSizeGrid">
                    <button class="pd-size-btn">S</button>
                    <button class="pd-size-btn active">M</button>
                    <button class="pd-size-btn">L</button>
                    <button class="pd-size-btn">XL</button>
                    <button class="pd-size-btn">XXL</button>
                    </div>
                </div> -->

                <!-- Color Selector -->
                <!-- <div class="pd-option-group">
                    <div class="pd-option-label">Color: <span class="pd-selected-color" id="pdSelectedColor">Black</span></div>
                    <div class="pd-color-grid" id="pdColorGrid">
                    <button class="pd-color-swatch active" style="background:#1a1a1a;" data-color="Black"
                        aria-label="Black"></button>
                    <button class="pd-color-swatch" style="background:#8b6c4f;" data-color="Camel"
                        aria-label="Camel"></button>
                    <button class="pd-color-swatch" style="background:#c8c8c8; border: 1px solid #aaa;" data-color="Silver"
                        aria-label="Silver"></button>
                    </div>
                </div> -->

                <!-- Divider -->
                <div class="pd-divider"></div>

                <!-- Quantity + Add to Cart -->
                <div class="pd-add-row">
                    <!-- Qty Stepper -->
                    <!-- Contact Us -->
                    <button class="btn-dark-custom pd-add-to-cart" onclick="openContactPopup()">
                        <i class="bi bi-telephone-fill me-2"></i>{{ app()->getLocale() === 'vi' ? 'Liên Hệ' : 'Contact Us' }}
                    </button>

                </div>

                <!-- Trust Badges -->
                <div class="pd-trust">
                    <div class="pd-trust-item">
                    <i class="bi bi-truck"></i>
                    <div>
                        <span class="pd-trust-title">Chính hãng</span>
                        <span class="pd-trust-sub">Cam kết 100% hàng chính hãng</span>
                    </div>
                    </div>
                    <div class="pd-trust-item">
                    <i class="bi bi-arrow-repeat"></i>
                    <div>
                        <span class="pd-trust-title">Đổi trả</span>
                        <span class="pd-trust-sub">7 ngày nếu lỗi từ NSX</span>
                    </div>
                    </div>
                    <div class="pd-trust-item">
                    <i class="bi bi-shield-check"></i>
                    <div>
                        <span class="pd-trust-title">Bảo hành</span>
                        <span class="pd-trust-sub">12-24 tháng, hỗ trợ kỹ thuật</span>
                    </div>
                    </div>
                </div>

                <!-- Payment icons -->
                <!-- <div class="pd-payments">
                    <span class="pd-payments-label">We Accept:</span>
                    <i class="bi bi-credit-card-2-front" title="Visa"></i>
                    <i class="bi bi-paypal" title="PayPal"></i>
                    <i class="bi bi-apple" title="Apple Pay"></i>
                    <i class="bi bi-google" title="Google Pay"></i>
                </div> -->

                </div><!-- /.pd-info -->
            </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container -->
    </section>





    <!-- ==========================================
    RELATED PRODUCTS
    ========================================== -->
    @if($relatedProducts->isNotEmpty())
    <section class="related-products-section">
        <div class="container">
            <h2 class="related-products-title">Related Products</h2>
            <div class="rp-slider-wrap">
                <button class="products-arrow rp-arrow-prev" id="rpPrev" aria-label="Previous">&#8592;</button>
                <div class="rp-track-wrap">
                <div class="rp-track" id="rpTrack">
                    @foreach($relatedProducts as $rp)
                    <div class="rp-item">
                        @include('front.components.product-card', [
                            'url'      => route(current_locale() . '.product.show', $rp->slug),
                            'image'    => $rp->image_url ? (str_starts_with($rp->image_url, 'http') ? $rp->image_url : asset($rp->image_url)) : asset('assets/img/product/default.jpg'),
                            'name'     => $rp->name,
                            'price'    => ($rp->sale_price > 0 && $rp->sale_price < $rp->price) ? number_format($rp->sale_price, 0, ',', '.') . 'đ' : ($rp->price > 0 ? number_format($rp->price, 0, ',', '.') . 'đ' : null),
                            'oldPrice' => ($rp->sale_price > 0 && $rp->price > 0 && $rp->sale_price < $rp->price) ? number_format($rp->price, 0, ',', '.') . 'đ' : null,
                            'tag'      => $rp->featured ? 'badge-featured' : ($rp->sale_price && $rp->sale_price < $rp->price ? 'badge-sale' : null),
                            'tagLabel' => $rp->featured ? __('shop.labels.badge_featured') : ($rp->sale_price && $rp->sale_price < $rp->price ? __('shop.labels.badge_sale') : null),
                        ])
                    </div>
                    @endforeach
                </div>
                </div><!-- /.rp-track-wrap -->
                <button class="products-arrow rp-arrow-next" id="rpNext" aria-label="Next">&#8594;</button>
            </div><!-- /.rp-slider-wrap -->
        </div>
    </section>
    @endif

    <!-- ==========================================
    PRODUCT TABS (Description / Reviews)
    ========================================== -->
    <section class="pd-tabs-section">
        <div class="container">
            @php $hasPdfs = !empty($product->pdf_files) && is_array($product->pdf_files) && count(array_filter($product->pdf_files, fn($p) => !empty($p['url']))); @endphp
            <div class="pd-tabs" id="pdTabs">
            <button class="pd-tab active" data-tab="description">Description</button>
            <button class="pd-tab" data-tab="details">Details</button>
            @if($hasPdfs)
            <button class="pd-tab" data-tab="documents">
                <i class="bi bi-file-earmark-pdf me-1"></i>
                {{ app()->getLocale() === 'vi' ? 'Tài liệu' : 'Documents' }}
                <span class="pd-tab-badge">{{ count(array_filter($product->pdf_files, fn($p) => !empty($p['url']))) }}</span>
            </button>
            @endif
            </div>

            <div class="pd-tab-content" id="pdTabContent">

            <!-- Tab: Description -->
            <div class="pd-tab-panel active" data-panel="description">
                @if($product->description)
                    <div class="pd-full-description">
                        {!! $product->description !!}
                    </div>
                @elseif($product->short_description)
                    <p class="pd-full-description">{{ $product->short_description }}</p>
                @else
                    <p class="text-muted">No description available.</p>
                @endif
            </div>

            <!-- Tab: Details & Care -->
            <div class="pd-tab-panel" data-panel="details">
                @php
                    $specs = $product->specifications;
                    if (is_string($specs)) $specs = json_decode($specs, true) ?: [];
                    $specs = is_array($specs) ? array_filter($specs, fn($v) => !empty($v)) : [];

                    $feats = $product->features;
                    if (is_string($feats)) $feats = json_decode($feats, true) ?: [];
                    $feats = is_array($feats) ? array_filter($feats) : [];

                    $specsLabel = $product->specifications_label ?: 'Product Details';
                    $featsLabel = $product->features_label       ?: 'Care Instructions';
                @endphp

                <div class="row g-4">

                {{-- Left: Specifications --}}
                <div class="col-md-6">
                    <h4 class="pd-details-heading">{{ $specsLabel }}</h4>
                    @if(!empty($specs))
                        <table class="pd-details-table">
                            @foreach($specs as $key => $value)
                                <tr>
                                    <td>{{ $key }}</td>
                                    <td>
                                        @if(filter_var($value, FILTER_VALIDATE_URL))
                                            <a href="{{ $value }}" target="_blank" rel="noopener noreferrer"
                                            class="text-blue-600 hover:underline break-all">{{ $value }}</a>
                                        @else
                                            {{ $value }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    @else
                        <p class="text-muted text-sm">No details available.</p>
                    @endif
                </div>

                {{-- Right: Features --}}
                <div class="col-md-6">
                    <h4 class="pd-details-heading">{{ $featsLabel }}</h4>
                    @if(!empty($feats))
                        <ul class="pd-care-list">
                            @foreach($feats as $feature)
                                <li><i class="bi bi-check2-circle"></i> {{ $feature }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted text-sm">No information available.</p>
                    @endif
                </div>

                </div>
            </div>


            <!-- Tab: Documents (PDF) -->
            @if($hasPdfs)
            <div class="pd-tab-panel" data-panel="documents">
                <p style="font-size:.9rem;color:#666;margin-bottom:1.25rem;">
                    {{ app()->getLocale() === 'vi' ? 'Tải xuống tài liệu kỹ thuật liên quan đến sản phẩm.' : 'Download technical documents related to this product.' }}
                </p>
                <div style="display:flex;flex-direction:column;gap:.75rem;max-width:640px;">
                    @foreach($product->pdf_files as $pdf)
                        @if(!empty($pdf['url']))
                        <a href="{{ asset($pdf['url']) }}" target="_blank" rel="noopener" download
                           style="display:flex;align-items:center;gap:1rem;padding:1rem 1.25rem;background:#fff;border:1px solid #e5e5e5;border-radius:12px;text-decoration:none;color:inherit;transition:border-color .2s,box-shadow .2s;"
                           onmouseover="this.style.borderColor='#b08d6a';this.style.boxShadow='0 4px 16px rgba(0,0,0,.08)'"
                           onmouseout="this.style.borderColor='#e5e5e5';this.style.boxShadow='none'">
                            {{-- PDF icon --}}
                            <div style="width:44px;height:44px;background:#fff0eb;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.4rem;color:#e53e3e;">
                                <i class="bi bi-file-earmark-pdf-fill"></i>
                            </div>
                            {{-- Info --}}
                            <div style="flex:1;min-width:0;">
                                <span style="display:block;font-size:.92rem;font-weight:600;color:#1a1a1a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    {{ $pdf['name'] ?: basename($pdf['url']) }}
                                </span>
                                <span style="display:block;font-size:.72rem;color:#999;margin-top:2px;text-transform:uppercase;letter-spacing:.06em;">PDF</span>
                            </div>
                            {{-- Download button --}}
                            <div style="width:36px;height:36px;border-radius:50%;background:#f4f3f0;display:flex;align-items:center;justify-content:center;font-size:.95rem;color:#666;flex-shrink:0;">
                                <i class="bi bi-download"></i>
                            </div>
                        </a>
                        @endif
                    @endforeach
                </div>
            </div>
            @endif

            </div><!-- /.pd-tab-content -->
        </div><!-- /.container -->
    </section>

    @if(!empty($product->faqs) && is_array($product->faqs) && count(array_filter($product->faqs, fn($f) => !empty($f['question']) && !empty($f['answer']))) > 0)
    {{-- ==========================================
        FAQ ACCORDION SECTION
    ========================================== --}}
    <section class="pd-faq-section">
    <div class="container">
        <h2 class="pd-faq-title">Câu hỏi thường gặp</h2>
        <div class="pd-faq-list" id="pdFaqList">
            @foreach($product->faqs as $index => $faq)
                @if(!empty($faq['question']) && !empty($faq['answer']))
                <div class="pd-faq-item {{ $index === 0 ? 'active' : '' }}" id="faq-{{ $index }}">
                    <button class="pd-faq-question" aria-expanded="{{ $index === 0 ? 'true' : 'false' }}" aria-controls="faq-answer-{{ $index }}" data-faq-index="{{ $index }}" onclick="toggleFaq(this.dataset.faqIndex)">
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
    // ── Related products slider ──
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
        function totalItems() { return track.children.length; }
        function maxIndex() { return Math.max(0, totalItems() - getVisible()); }
        function slide() {
            current = Math.min(current, maxIndex());
            const itemW = track.children[0].offsetWidth;
            const gap   = parseInt(getComputedStyle(track).gap) || 24;
            track.style.transform = `translateX(-${current * (itemW + gap)}px)`;
            btnPrev.disabled = current === 0;
            btnNext.disabled = current >= maxIndex();
        }
        btnPrev.addEventListener('click', () => { current = Math.max(0, current - 1); slide(); });
        btnNext.addEventListener('click', () => { current = Math.min(maxIndex(), current + 1); slide(); });
        window.addEventListener('resize', () => slide());
        slide();
    }

    // ── Image gallery ──
    const thumbs = document.querySelectorAll(".pd-thumb");
    const mainImage = document.getElementById("mainImage");
    thumbs.forEach(thumb => {
        thumb.addEventListener("click", function () {
            const newImg = this.getAttribute("data-img");
            if (mainImage && newImg) mainImage.src = newImg;
            thumbs.forEach(t => t.classList.remove("active"));
            this.classList.add("active");
        });
    });
});

// ── FAQ Accordion ──
function toggleFaq(index) {
    const item   = document.getElementById('faq-' + index);
    const btn    = item.querySelector('.pd-faq-question');
    const answer = document.getElementById('faq-answer-' + index);
    const isOpen = item.classList.contains('active');

    // Đóng tất cả
    document.querySelectorAll('.pd-faq-item').forEach(el => {
        el.classList.remove('active');
        el.querySelector('.pd-faq-question').setAttribute('aria-expanded', 'false');
    });

    // Mở item được click (nếu chưa open)
    if (!isOpen) {
        item.classList.add('active');
        btn.setAttribute('aria-expanded', 'true');
    }
}
</script>

<style>
/* ── FAQ Section ── */
.pd-faq-section {
    padding: 3rem 0;
    background: #fafafa;
    border-top: 1px solid #f0f0f0;
}
.pd-faq-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    color: #1a1a1a;
}
.pd-faq-list {
    max-width: 780px;
}
.pd-faq-item {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    margin-bottom: 0.75rem;
    background: #fff;
    overflow: hidden;
    transition: box-shadow .2s;
}
.pd-faq-item:hover {
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
}
.pd-faq-question {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 0.95rem;
    font-weight: 600;
    color: #1a1a1a;
    text-align: left;
}
.pd-faq-icon {
    transition: transform .3s ease;
    flex-shrink: 0;
    font-size: 0.85rem;
    color: #6b7280;
}
.pd-faq-item.active .pd-faq-icon {
    transform: rotate(-180deg);
}
.pd-faq-answer {
    max-height: 0;
    overflow: hidden;
    transition: max-height .35s ease, padding .3s ease;
    padding: 0 1.25rem;
}
.pd-faq-answer p {
    margin: 0;
    font-size: 0.9rem;
    color: #4b5563;
    line-height: 1.7;
    padding-bottom: 1rem;
}
.pd-faq-item.active .pd-faq-answer {
    max-height: 500px;
    padding-top: 0;
}
</style>
@endpush