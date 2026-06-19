@php $locale = app()->getLocale(); @endphp

@extends('layouts.frontend')

@push('head')
@php $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT; @endphp
@foreach($businessSchemas as $schema)
<script type="application/ld+json">{!! json_encode($schema, $jsonFlags) !!}</script>
@endforeach
@endpush

@section('content')

    <!-- magic cursor -->
    <div id="magic-cursor" class="cursor-white-bg"><div id="ball"></div></div>

    <!-- hero area -->
    <div class="tp-hero-area tp-hero-ptb p-relative fix z-index-1" data-background="{{ asset('images/casambi/luma5-2000x1334.jpg') }}">
        <div class="container container-1750">
            <div class="row">
                <div class="col-xl-9">
                    <div class="tp-hero-title-box">
                        <h2 class="tp-hero-title tp-char-animation">
                            <img src="{{ asset('images/casambi/casambiwhite.svg') }}" alt="{{ $locale === 'vi' ? 'Casambi Vietnam — Giải pháp chiếu sáng thông minh' : 'Casambi Vietnam — Smart Lighting Solutions' }}" class="responsive-img">
                            <span> <br> DALI & </span> Wireless
                        </h2>
                    </div>
                </div>
                <div class="col-xl-3">
                    <div class="tp-hero-content-wrap d-flex flex-xl-column justify-content-between pb-20">
                        <div class="tp-hero-info d-flex align-items-start justify-content-between tp_text_anim" style="background-color: rgba(0,0,0,0.1); padding: 20px; border-radius: 8px;">
                            <h1 style="color: white; font-size: 1.2rem;">{{ __('index.hero.desc') }}</h1>
                            <span>
                                <a href="#contact" onclick="openContactPopup();return false;" aria-label="{{ $locale === 'vi' ? 'Liên hệ' : 'Contact us' }}">
                                    <svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M1 21L21 1M21 1H1M21 1V21" stroke="currentcolor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    <svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M1 21L21 1M21 1H1M21 1V21" stroke="currentcolor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </a>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- about area -->
    <div class="tp-about-area pt-140 pb-120 tp-bounce-trigger">
        <div class="container">
            <div class="tp-about-box p-relative">
                <div class="tp-about-shape-1 tp-bounce d-none d-md-block"></div>
                <div class="row">
                    <div class="col-xl-3">
                        <div class="tp-about-title-box">
                            <h2 class="tp-section-subtitle pre tp_fade_anim">{{ __('index.about.label') }}</h2>
                        </div>
                    </div>
                    <div class="col-xl-9">
                        <div class="tp-about-wrap">
                            <div class="tp-about-text tp_fade_anim">
                                <p>{!! __('index.about.desc') !!}</p>
                            </div>
                            <div class="row align-items-center mt-50">
                                <div class="col-xl-5 col-lg-5 col-md-5">
                                    <div class="tp-about-thumb">
                                        <img data-speed=".8" src="{{ asset('images/casambi/warmligth.jpg') }}" alt="Casambi smart lighting ambience">
                                    </div>
                                </div>
                                <div class="col-xl-7 col-lg-7 col-md-7">
                                    <div class="tp-about-funcact-wrap ps-xl-4">
                                        @foreach([
                                            ['tag'=>__('index.about.f1_tag'),'title'=>__('index.about.f1_title'),'desc'=>__('index.about.f1_desc')],
                                            ['tag'=>__('index.about.f2_tag'),'title'=>__('index.about.f2_title'),'desc'=>__('index.about.f2_desc')],
                                            ['tag'=>__('index.about.f3_tag'),'title'=>__('index.about.f3_title'),'desc'=>__('index.about.f3_desc')],
                                        ] as $i => $f)
                                        <div class="tp-about-avater-info" style="{{ $i < 2 ? 'border-bottom: 1px solid #e8e8e8; padding-bottom: 22px; margin-bottom: 22px;' : 'padding-bottom: 10px;' }}">
                                            <span style="font-size: 12px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; color: #ff5622;">{{ $f['tag'] }}</span>
                                            <h3 style="font-size: 22px; font-weight: 600; margin: 10px 0 8px;">{{ $f['title'] }}</h3>
                                            <p style="color: #666; font-size: 15px; line-height: 1.6;">{{ $f['desc'] }}</p>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- banner -->
    <div class="tp-banner-area">
        <div class="tp-banner-img">
            <img class="w-100" data-speed=".7" src="{{ asset('images/casambi/worldlight-officinas-04-scaled.jpg') }}" alt="Casambi lighting installation project">
        </div>
    </div>

    <!-- service area -->
    <div class="tp-service-area pt-120">
        <div class="container-fluid p-0">
            <div class="row gx-0">
                <div class="col-12">
                    <div class="tp-service-title-box">
                        <h2 class="tp-section-subtitle pre">{{ __('index.service.label') }}</h2>
                    </div>
                </div>
            </div>
            <div class="tp-service-pin">
                @foreach([
                    ['num'=>'01','img'=>'yourfingertips.jpg',       'alt'=>'Casambi app control','title'=>__('index.service.s1_title'),'desc'=>__('index.service.s1_desc'),'tags'=>__('index.service.s1_tags')],
                    ['num'=>'02','img'=>'Fastest-Installation.jpg', 'alt'=>'Fastest installation','title'=>__('index.service.s2_title'),'desc'=>__('index.service.s2_desc'),'tags'=>__('index.service.s2_tags')],
                    ['num'=>'03','img'=>'casambipro.png',            'alt'=>'Casambi Pro',         'title'=>__('index.service.s3_title'),'desc'=>__('index.service.s3_desc'),'tags'=>__('index.service.s3_tags')],
                    ['num'=>'04','img'=>'office.jpeg',               'alt'=>'Smart office',        'title'=>__('index.service.s4_title'),'desc'=>__('index.service.s4_desc'),'tags'=>__('index.service.s4_tags')],
                ] as $s)
                <div class="tp-service-item tp-service-panel">
                    <div class="row">
                        <div class="col-xxl-3 col-xl-2 col-lg-1 col-md-1">
                            <div class="tp-service-number"><span>{{ $s['num'] }}.</span></div>
                        </div>
                        <div class="col-xxl-5 col-xl-6 col-lg-7 col-md-7">
                            <div class="tp-service-content">
                                <h3 class="tp-section-title-phudu"><a class="tp_text_invert" href="#">{{ $s['title'] }}</a></h3>
                                <p>{{ $s['desc'] }}</p>
                                <div class="tp-service-category">
                                    @foreach(explode('|', $s['tags']) as $tag)
                                        <span>{{ $tag }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-xl-4 col-lg-4 col-md-4">
                            <div class="tp-service-thumb text-end">
                                <img class="tp_fade_anim" data-fade-from="right" data-delay=".2" src="{{ asset('images/casambi/' . $s['img']) }}" alt="{{ $s['alt'] }}">
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- technology tabs -->
    <div class="tp-tech-tabs-area pt-160 pb-120">
        <div class="container">
            <div class="row justify-content-center text-center mb-55">
                <div class="col-xl-8">
                    <h2 class="tp-section-title tp-char-animation" style="font-size: 52px; font-weight: 800; line-height: 1.1; letter-spacing: -1.5px; margin-bottom: 20px; text-transform: none;">
                        {!! __('index.tech.heading') !!}
                    </h2>
                    <p style="font-size: 17px; color: #666; line-height: 1.7; max-width: 640px; margin: 0 auto;">{{ __('index.tech.subheading') }}</p>
                </div>
            </div>
            <div class="row justify-content-center mb-40">
                <div class="col-auto">
                    <div class="tp-tech-tab-nav" style="display: inline-flex; background: #f2f0ed; border-radius: 50px; padding: 5px; gap: 4px;">
                        <button class="tp-tech-tab-btn active" data-tab="dali"    style="padding: 12px 36px; border-radius: 50px; border: none; font-size: 15px; font-weight: 600; cursor: pointer; background: #f9561a; color: #fff;">{{ __('index.tech.tab_dali') }}</button>
                        <button class="tp-tech-tab-btn"        data-tab="wireless" style="padding: 12px 36px; border-radius: 50px; border: none; font-size: 15px; font-weight: 600; cursor: pointer; background: transparent; color: #111013;">{{ __('index.tech.tab_wireless') }}</button>
                        <button class="tp-tech-tab-btn"        data-tab="hybrid"   style="padding: 12px 36px; border-radius: 50px; border: none; font-size: 15px; font-weight: 600; cursor: pointer; background: transparent; color: #111013;">{{ __('index.tech.tab_hybrid') }}</button>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <div class="tp-tech-video-wrap" style="position: relative; border-radius: 16px; overflow: hidden; background: #111013; aspect-ratio: 16/9; width: 100%;">
                        @foreach([
                            ['key'=>'dali',    'active'=>true,  'tab_key'=>'index.tech.tab_dali',    'cap_key'=>'index.tech.dali_cap',    'video'=>'Room-1-loop.mp4'],
                            ['key'=>'wireless','active'=>false, 'tab_key'=>'index.tech.tab_wireless', 'cap_key'=>'index.tech.wireless_cap', 'video'=>'Room-2-loop.mp4'],
                            ['key'=>'hybrid',  'active'=>false, 'tab_key'=>'index.tech.tab_hybrid',   'cap_key'=>'index.tech.hybrid_cap',   'video'=>'Room-3-loop.mp4'],
                        ] as $panel)
                        <div class="tp-tech-panel{{ $panel['active'] ? ' active' : '' }}" data-panel="{{ $panel['key'] }}" style="position: absolute; inset: 0; opacity: {{ $panel['active'] ? 1 : 0 }}; transition: opacity .5s ease;">
                            <video src="{{ asset('assets/video/' . $panel['video']) }}" autoplay muted loop playsinline style="width: 100%; height: 100%; object-fit: cover; pointer-events: none; display: block;"></video>
                            <div class="tp-tech-panel-caption" style="position: absolute; bottom: 36px; left: 40px; color: #fff;">
                                <span style="font-size: 12px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; color: #ff5622; display: block; margin-bottom: 8px;">{{ __($panel['tab_key']) }}</span>
                                <p style="font-size: 16px; opacity: .8; max-width: 400px; line-height: 1.6; margin: 0; color: white;">{{ __($panel['cap_key']) }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Product category sliders (populated by HomeController after DB integration) --}}
    @if(!empty($homeCategoryProducts))
    <section class="home-cat-products">
        <div class="container">
            @foreach($homeCategoryProducts as $catId => $data)
            @php
                $cat      = $data['category'];
                $products = $data['products'];
                $sliderId = 'hcp-slider-' . $catId;
                $catUrl   = route(current_locale() . '.category.show', $cat->slug);
            @endphp
            @if($products->isNotEmpty())
            <div class="hcp-row">
                <div class="hcp-header">
                    <a href="{{ $catUrl }}" class="hcp-title">{{ $cat->name }}</a>
                    <div class="hcp-nav">
                        <button class="hcp-btn" data-dir="prev" data-slider="{{ $sliderId }}" aria-label="Previous">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                        <button class="hcp-btn" data-dir="next" data-slider="{{ $sliderId }}" aria-label="Next">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    </div>
                </div>
                <div class="hcp-slider-wrap">
                    <div class="hcp-slider" id="{{ $sliderId }}">
                        @foreach($products as $product)
                        <div class="hcp-item">
                            @include('components.product.card', [
                                'url'      => route(current_locale() . '.product.show', $product->slug),
                                'image'    => $product->image_url ? (str_starts_with($product->image_url, 'http') ? $product->image_url : asset($product->image_url)) : asset('images/casambi/product-placeholder.jpg'),
                                'name'     => $product->name,
                                'price'    => ($product->sale_price > 0 && $product->sale_price < $product->price) ? number_format($product->sale_price, 0, ',', '.') . 'đ' : ($product->price > 0 ? number_format($product->price, 0, ',', '.') . 'đ' : null),
                                'oldPrice' => ($product->sale_price > 0 && $product->price > 0 && $product->sale_price < $product->price) ? number_format($product->price, 0, ',', '.') . 'đ' : null,
                                'onSale'   => ($product->sale_price > 0 && $product->sale_price < $product->price),
                                'discount' => ($product->sale_price > 0 && $product->price > 0 && $product->sale_price < $product->price) ? round((1 - $product->sale_price / $product->price) * 100) : null,
                            ])
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
            @endforeach
        </div>
    </section>
    @endif

    {{-- Latest blog posts (populated by HomeController after DB integration) --}}
    @if(!empty($latestBlogs) && $latestBlogs->isNotEmpty())
    <section class="home-latest-blogs">
        <div class="container">
            <div class="hcp-header" style="margin-bottom:24px;">
                <a href="{{ route(current_locale() . '.blog.index') }}" class="hcp-title">{{ $locale === 'vi' ? 'Bài viết mới nhất' : 'Latest Articles' }}</a>
                <a href="{{ route(current_locale() . '.blog.index') }}" class="blog-view-all">
                    {{ $locale === 'vi' ? 'Xem tất cả' : 'View all' }}
                    <svg width="11" height="11" viewBox="0 0 12 12" fill="none"><path d="M1 11L11 1M11 1H1M11 1V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
            </div>
            <div class="row g-4">
                @foreach($latestBlogs as $blog)
                <div class="col-md-6 col-lg-4">
                    <div class="blog-card">
                        <a href="{{ route(current_locale() . '.blog.show', [$blog->category_slug, $blog->slug]) }}" class="blog-card__img-wrap d-block">
                            @if($blog->featured_image)
                                <img src="{{ asset($blog->featured_image) }}" alt="{{ $blog->title }}" loading="lazy">
                            @else
                                <div class="blog-card__img-placeholder"></div>
                            @endif
                        </a>
                        @if($blog->category)<div class="blog-card__category">{{ $blog->category }}</div>@endif
                        <h2 class="blog-card__title"><a href="{{ route(current_locale() . '.blog.show', [$blog->category_slug, $blog->slug]) }}">{{ Str::limit($blog->title, 65) }}</a></h2>
                        @if($blog->excerpt)<p class="blog-card__excerpt">{{ strip_tags($blog->excerpt) }}</p>@endif
                        <div class="d-flex align-items-center justify-content-between mt-auto">
                            <a href="{{ route(current_locale() . '.blog.show', [$blog->category_slug, $blog->slug]) }}" class="blog-card__read-more">
                                {{ $locale === 'vi' ? 'Đọc thêm' : 'Read more' }}
                                <svg width="11" height="11" viewBox="0 0 12 12" fill="none"><path d="M1 11L11 1M11 1H1M11 1V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                            <div class="blog-card__date">{{ $blog->formatted_published_date }}</div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <!-- CTA area -->
    <div class="app-cta-area z-index-1">
        <div class="container container-1430">
            <div class="app-cta-wrap">
                <div class="row align-items-end">
                    <div class="col-lg-6">
                        <div class="app-cta-wrapper pt-90 pb-90">
                            <div class="app-cta-heading mb-30">
                                <h3 class="tp-section-title-phudu fs-70 mb-20 tp_fade_anim" data-delay=".3">{!! __('dali.cta.heading') !!}</h3>
                                <div class="tp_fade_anim" data-delay=".5"><p>{{ __('dali.cta.desc') }}</p></div>
                            </div>
                            <div class="app-cta-store-box d-flex align-items-center tp_fade_anim" data-delay=".3" data-fade-from="top" data-ease="bounce">
                                <a href="{{ __('dali.cta.store1_url') }}" target="_blank" rel="noopener" class="app-cta-store mr-15">
                                    <div class="app-cta-store-content"><p>{{ __('dali.cta.store1_label') }}</p><span>{{ __('dali.cta.store1_name') }}</span></div>
                                </a>
                                <a href="{{ __('dali.cta.store2_url') }}" target="_blank" rel="noopener" class="app-cta-store">
                                    <div class="app-cta-store-content"><p>{{ __('dali.cta.store2_label') }}</p><span>{{ __('dali.cta.store2_name') }}</span></div>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="app-cta-thumb-wrap">
                            <div class="app-cta-thumb p-relative">
                                <div class="cta-slide-in cta-slide-left z-index-1"><img class="app-cta-thumb-1" src="{{ asset('images/casambi/casambiapp.webp') }}" alt="Casambi App"></div>
                                <div class="cta-slide-in cta-slide-right"><img class="app-cta-thumb-2" src="{{ asset('images/casambi/casambipro.png') }}" alt="Casambi Pro"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(!empty($faqItems))
    <!-- FAQ -->
    <section class="home-faq-section">
        <div class="container">
            <div class="home-faq__inner">

                {{-- Left: sticky label + title --}}
                <div class="home-faq__left">
                    <span class="home-faq__label">FAQ</span>
                    <h2 class="home-faq__title">
                        {{ $locale === 'vi' ? 'Câu hỏi thường gặp' : 'Frequently Asked Questions' }}
                    </h2>
                    <p class="home-faq__desc">
                        {{ $locale === 'vi'
                            ? 'Những câu hỏi phổ biến về giải pháp chiếu sáng thông minh của chúng tôi.'
                            : 'Common questions about our smart lighting solutions.' }}
                    </p>
                </div>

                {{-- Right: accordion list --}}
                <div class="home-faq__list">
                    @foreach($faqItems as $i => $faq)
                    <div class="home-faq__item{{ $i === 0 ? ' open' : '' }}">
                        <button type="button" class="home-faq__question" onclick="toggleFaq(this)">
                            <span class="home-faq__num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="home-faq__q-text">{{ $faq['q'] }}</span>
                            <span class="home-faq__icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </span>
                        </button>
                        <div class="home-faq__answer"><p>{{ $faq['a'] }}</p></div>
                    </div>
                    @endforeach
                </div>

            </div>
        </div>
    </section>
    <script>
    function toggleFaq(btn) {
        var item = btn.closest('.home-faq__item');
        var isOpen = item.classList.contains('open');
        document.querySelectorAll('.home-faq__item.open').forEach(function(el) { el.classList.remove('open'); });
        if (!isOpen) item.classList.add('open');
    }
    </script>
    @endif

    <style>
    @media (max-width: 991px) {
        .tp-tech-tabs-area { padding-top: 80px !important; padding-bottom: 70px !important; }
        .tp-tech-tabs-area .tp-section-title { font-size: 36px !important; }
        .hcp-item { flex: 0 0 calc(33.333% - 16px); }
    }
    @media (max-width: 767px) {
        .tp-tech-tabs-area { padding-top: 56px !important; padding-bottom: 48px !important; }
        .tp-tech-tabs-area .tp-section-title { font-size: 26px !important; }
        .tp-tech-tab-nav { display: flex !important; flex-wrap: wrap; justify-content: center; border-radius: 16px !important; padding: 4px !important; gap: 4px !important; }
        .tp-tech-tab-btn { padding: 9px 18px !important; font-size: 13px !important; border-radius: 12px !important; flex: 1 1 auto; }
        .tp-tech-panel-caption { bottom: 16px !important; left: 16px !important; right: 16px !important; }
        .tp-tech-panel-caption span { font-size: 10px !important; }
        .tp-tech-panel-caption p { font-size: 13px !important; max-width: 100% !important; }
        .hcp-slider { gap: 12px; }
        .hcp-item { flex: 0 0 calc(50% - 6px); }
        .hcp-title { font-size: 1.15rem; }
    }
    .home-latest-blogs { padding: 60px 0; }
    .blog-view-all { font-size: .8rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #111013; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
    .blog-view-all:hover { color: #f9561a; }
    .home-cat-products { padding: 60px 0; }
    .hcp-row { margin-bottom: 56px; }
    .hcp-row:last-child { margin-bottom: 0; }
    .hcp-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
    .hcp-title { font-size: 1.5rem; font-weight: 700; color: #111013; text-decoration: none; letter-spacing: -0.01em; }
    .hcp-title:hover { color: #f9561a; }
    .hcp-nav { display: flex; gap: 8px; }
    .hcp-btn { width: 40px; height: 40px; border: 1px solid #e0e0e0; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background .2s, border-color .2s, color .2s; color: #111013; }
    .hcp-btn:hover { background: #111013; border-color: #111013; color: #fff; }
    .hcp-btn:disabled { opacity: .35; cursor: default; pointer-events: none; }
    .hcp-slider-wrap { overflow: hidden; }
    .hcp-slider { display: flex; flex-wrap: nowrap; gap: 24px; transition: transform .4s cubic-bezier(.4,0,.2,1); }
    .hcp-item { flex: 0 0 calc(25% - 18px); min-width: 0; }
    </style>

@push('scripts')
<script>
(function () {
    var btns = document.querySelectorAll('.tp-tech-tab-btn');
    var panels = document.querySelectorAll('.tp-tech-panel');
    btns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = this.getAttribute('data-tab');
            btns.forEach(function (b) { b.classList.remove('active'); b.style.background = 'transparent'; b.style.color = '#111013'; });
            this.classList.add('active'); this.style.background = '#f9561a'; this.style.color = '#fff';
            panels.forEach(function (p) {
                if (p.getAttribute('data-panel') === target) {
                    p.style.opacity = '1'; p.style.zIndex = '1';
                    var vid = p.querySelector('video'); if (vid) { vid.currentTime = 0; vid.play(); }
                } else { p.style.opacity = '0'; p.style.zIndex = '0'; }
            });
        });
    });

    function getCols() { if (window.innerWidth <= 767) return 2; if (window.innerWidth <= 991) return 3; return 4; }
    document.querySelectorAll('.hcp-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var sliderId = btn.getAttribute('data-slider'), dir = btn.getAttribute('data-dir');
            var slider = document.getElementById(sliderId); if (!slider) return;
            var cols = getCols(), total = slider.querySelectorAll('.hcp-item').length, pages = Math.ceil(total / cols);
            var current = parseInt(slider.getAttribute('data-page') || '0');
            if (dir === 'next') current = Math.min(current + 1, pages - 1); else current = Math.max(current - 1, 0);
            slider.setAttribute('data-page', current);
            var itemW = slider.querySelector('.hcp-item').offsetWidth, gap = parseInt(getComputedStyle(slider).gap) || 24;
            slider.style.transform = 'translateX(-' + (current * cols * (itemW + gap)) + 'px)';
            var wrap = btn.closest('.hcp-row'); wrap.querySelectorAll('.hcp-btn').forEach(function (b) {
                var d = b.getAttribute('data-dir');
                b.disabled = (d === 'prev' && current === 0) || (d === 'next' && current === pages - 1);
            });
        });
    });
    document.querySelectorAll('.hcp-row').forEach(function (row) {
        var slider = row.querySelector('.hcp-slider'); if (!slider) return;
        var prev = row.querySelector('[data-dir="prev"]'), next = row.querySelector('[data-dir="next"]');
        if (prev) prev.disabled = true;
        if (next) next.disabled = slider.querySelectorAll('.hcp-item').length <= getCols();
    });
})();
</script>
@endpush

@endsection
