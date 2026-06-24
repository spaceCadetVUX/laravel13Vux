@php
    $daliOgRaw   = __('dali.seo.og_image');
    $defaultOgRaw = \App\Models\Setting::get('default_og_image');
    $daliOgImage = ($daliOgRaw && $daliOgRaw !== 'dali.seo.og_image')
        ? (str_starts_with($daliOgRaw, 'http') ? $daliOgRaw : asset($daliOgRaw))
        : ($defaultOgRaw
            ? (str_starts_with($defaultOgRaw, 'http') ? $defaultOgRaw : asset($defaultOgRaw))
            : asset('images/casambi/heroimg_dali.jpeg'));
@endphp
@extends('layouts.frontend')

{{-- DALI Page Schema: Organization + WebPage + Service --}}
@push('head')
@php
    $locale  = app()->getLocale();
    $appName = config('app.name');
    $appUrl  = config('app.url');
    $pageUrl = url()->current();

    $logoRaw = \App\Models\Setting::get('site_logo');
    $logoUrl = $logoRaw
        ? (str_starts_with($logoRaw, 'http') ? $logoRaw : url(asset($logoRaw)))
        : url(asset('assets/img/logo/logo.png'));

    $socialLinks = array_values(array_filter([
        \App\Models\Setting::get('social_facebook'),
        \App\Models\Setting::get('social_instagram'),
        \App\Models\Setting::get('social_youtube'),
        \App\Models\Setting::get('social_tiktok'),
    ]));

    $organizationSchema = [
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        'name'     => $appName,
        'url'      => $appUrl,
        'logo'     => ['@type' => 'ImageObject', 'url' => $logoUrl],
    ];
    if (!empty($socialLinks)) $organizationSchema['sameAs'] = $socialLinks;

    $webPageSchema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'WebPage',
        'url'         => $pageUrl,
        'name'        => __('dali.seo.title'),
        'description' => __('dali.seo.description'),
        'inLanguage'  => $locale === 'vi' ? 'vi-VN' : 'en-US',
        'isPartOf'    => ['@type' => 'WebSite', 'url' => $appUrl],
        'publisher'   => ['@type' => 'Organization', 'name' => $appName, 'url' => $appUrl],
    ];

    $serviceSchema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Service',
        'name'        => $locale === 'vi' ? 'Điều Khiển DALI Không Dây Casambi' : 'Casambi Wireless DALI Control',
        'description' => __('dali.seo.description'),
        'url'         => $pageUrl,
        'provider'    => ['@type' => 'Organization', 'name' => $appName, 'url' => $appUrl],
        'areaServed'  => 'VN',
        'serviceType' => 'Smart Lighting Control',
    ];

    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
@endphp
<script type="application/ld+json">{!! json_encode($organizationSchema, $jsonFlags) !!}</script>
<script type="application/ld+json">{!! json_encode($webPageSchema, $jsonFlags) !!}</script>
<script type="application/ld+json">{!! json_encode($serviceSchema, $jsonFlags) !!}</script>
@php
    $daliFaqs = $locale === 'vi' ? [
        ['q' => 'DALI là gì?', 'a' => 'DALI (Digital Addressable Lighting Interface) là giao thức điều khiển chiếu sáng kỹ thuật số theo tiêu chuẩn IEC 62386, cho phép điều khiển từng bộ đèn riêng lẻ với độ chính xác cao qua đường truyền 2 dây.'],
        ['q' => 'Casambi DALI khác gì so với DALI truyền thống?', 'a' => 'Casambi DALI kết hợp giao thức DALI chuẩn với giao diện điều khiển không dây Bluetooth Mesh của Casambi, giúp vận hành toàn bộ hệ thống DALI qua smartphone/tablet mà không cần phần mềm máy tính chuyên dụng.'],
        ['q' => 'DALI có tích hợp được với hệ thống BMS không?', 'a' => 'Có. DALI hỗ trợ tích hợp với hệ thống quản lý tòa nhà (BMS) thông qua gateway DALI-BACnet hoặc DALI-KNX, cho phép giám sát và điều khiển tập trung toàn bộ hệ thống chiếu sáng.'],
        ['q' => 'Một mạng DALI có thể kết nối tối đa bao nhiêu thiết bị?', 'a' => 'Một phân đoạn mạng DALI chuẩn hỗ trợ tối đa 64 địa chỉ thiết bị và 16 nhóm. Với Casambi DALI, nhiều phân đoạn có thể được quản lý trong cùng một hệ thống qua Bluetooth Mesh.'],
        ['q' => 'DALI phù hợp với loại công trình nào?', 'a' => 'DALI đặc biệt phù hợp với tòa nhà văn phòng, trung tâm thương mại, bệnh viện, trường học và các công trình công nghiệp đòi hỏi điều khiển chiếu sáng chính xác và khả năng giám sát tiêu thụ điện năng.'],
    ] : [
        ['q' => 'What is DALI?', 'a' => 'DALI (Digital Addressable Lighting Interface) is a digital lighting control protocol per IEC 62386 standard, enabling individual control of each luminaire with high precision over a 2-wire bus.'],
        ['q' => 'How is Casambi DALI different from traditional DALI?', 'a' => 'Casambi DALI combines the standard DALI protocol with Casambi\'s Bluetooth Mesh wireless interface, allowing the entire DALI system to be operated via smartphone or tablet without dedicated PC software.'],
        ['q' => 'Can DALI integrate with a BMS system?', 'a' => 'Yes. DALI supports integration with building management systems (BMS) via DALI-BACnet or DALI-KNX gateways, enabling centralized monitoring and control of the entire lighting infrastructure.'],
        ['q' => 'How many devices can a DALI network support?', 'a' => 'A standard DALI segment supports up to 64 device addresses and 16 groups. With Casambi DALI, multiple segments can be managed within the same system via Bluetooth Mesh.'],
        ['q' => 'What types of projects is DALI best suited for?', 'a' => 'DALI is especially suitable for office buildings, shopping centers, hospitals, schools, and industrial facilities requiring precise lighting control and energy consumption monitoring.'],
    ];

    $faqSchema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => array_map(fn($faq) => [
            '@type'          => 'Question',
            'name'           => $faq['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a']],
        ], $daliFaqs),
    ];
@endphp
<script type="application/ld+json">{!! json_encode($faqSchema, $jsonFlags) !!}</script>
@php
    $breadcrumbSchema = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => $locale === 'vi' ? 'Trang chủ' : 'Home', 'item' => $appUrl],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $locale === 'vi' ? 'DALI Casambi' : 'Casambi DALI', 'item' => $pageUrl],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($breadcrumbSchema, $jsonFlags) !!}</script>
@endpush


@section('content')

    <div id="magic-cursor" class="cursor-white-bg"><div id="ball"></div></div>
    <div class="back-to-top-wrapper">
        <button id="back_to_top" type="button" class="back-to-top-btn">
            <svg width="12" height="7" viewBox="0 0 12 7" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11 6L6 1L1 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
        </button>
    </div>

    <section class="dali-hero">
        <div class="container container-1230">
            <div class="row align-items-center g-5 dali-hero__row">
                <div class="col-lg-6">
                    <div class="dali-hero__text">
                        <div class="dali-hero__eyebrow">
                            <span class="dali-hero__dot"></span>
                            {{ __('dali.hero.eyebrow') }}
                        </div>
                        <h1 class="dali-hero__title">{!! __('dali.hero.title') !!}</h1>
                        <p class="dali-hero__desc">{{ __('dali.hero.desc') }}</p>
                        <div class="dali-hero__actions">
                            <a href="#" class="dali-hero__btn-primary">
                                {{ __('dali.hero.btn_primary') }}
                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                            <a href="#features" class="dali-hero__btn-ghost">{{ __('dali.hero.btn_ghost') }}</a>
                        </div>
                        <div class="dali-hero__stats">
                            <div class="dali-hero__stat">
                                <span class="dali-hero__stat-num">250K+</span>
                                <span class="dali-hero__stat-lbl">{{ __('dali.hero.stat1_lbl') }}</span>
                            </div>
                            <div class="dali-hero__stat-divider"></div>
                            <div class="dali-hero__stat">
                                <span class="dali-hero__stat-num">6M+</span>
                                <span class="dali-hero__stat-lbl">{{ __('dali.hero.stat2_lbl') }}</span>
                            </div>
                            <div class="dali-hero__stat-divider"></div>
                            <div class="dali-hero__stat">
                                <span class="dali-hero__stat-num">100s</span>
                                <span class="dali-hero__stat-lbl">{{ __('dali.hero.stat3_lbl') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="dali-hero__visual">
                        <img src="{{ asset('images/casambi/dalicasambi-hero.jpeg') }}" alt="Casambi DALI Control">
                        <div class="dali-hero__badge">
                            <div class="dali-hero__badge-icon">
                                <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="#ff581e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </div>
                            <div>
                                <div class="dali-hero__badge-title">Zero Gateways</div>
                                <div class="dali-hero__badge-sub">DALI + BLE + EnOcean</div>
                            </div>
                        </div>
                        <div class="dali-hero__rating">
                            <span class="dali-hero__rating-stars">★★★★★</span>
                            <span class="dali-hero__rating-txt">4.9 · Casambi App</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div style="padding: 80px;"></div>

    <div class="app-feature-area app-feature-border-style" id="features">
        <div class="container container-1230">
            <div class="row">
                <div class="col-lg-12">
                    <div class="app-feature-heading text-center mb-55">
                        <h3 class="tp-section-title-phudu fs-70 mb-20 tp_fade_anim" data-delay=".5">{!! __('dali.feature.heading') !!}</h3>
                        <div class="tp_fade_anim" data-delay=".7"><p>{{ __('dali.feature.subheading') }}</p></div>
                    </div>
                </div>
            </div>
            <div class="app-feature-bg tp_fade_anim">
                <div class="row gx-10">
                    <div class="col-lg-4 col-md-6">
                        <div class="app-feature-item mb-30" style="background: #ffffff; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
                            <div class="app-feature-item-icon"><span><svg xmlns="http://www.w3.org/2000/svg" style="color: #ff581e;" width="26" height="26" viewBox="0 0 26 26" fill="none"><path d="M22.1594 3.76253L14.5434 0.370404C13.4257 -0.123468 11.7361 -0.123468 10.6184 0.370404L3.0024 3.76253C1.07889 4.6203 0.792969 5.79 0.792969 6.41384C0.792969 7.03768 1.07889 8.20738 3.0024 9.06516L10.6184 12.4573C11.1773 12.7042 11.8791 12.8342 12.5809 12.8342C13.2827 12.8342 13.9846 12.7042 14.5434 12.4573L22.1594 9.06516C24.0829 8.20738 24.3689 7.03768 24.3689 6.41384C24.3689 5.79 24.0959 4.6203 22.1594 3.76253Z" fill="currentColor" /><path opacity="0.4" d="M12.5807 19.5535C12.0869 19.5535 11.593 19.4495 11.1381 19.2546L2.37839 15.3556C1.03973 14.7577 0 13.1591 0 11.6905C0 11.1576 0.428889 10.7288 0.961751 10.7288C1.49461 10.7288 1.9235 11.1576 1.9235 11.6905C1.9235 12.3923 2.50835 13.3021 3.15818 13.588L11.9179 17.487C12.3338 17.669 12.8147 17.669 13.2306 17.487L21.9903 13.588C22.6401 13.3021 23.225 12.4053 23.225 11.6905C23.225 11.1576 23.6539 10.7288 24.1867 10.7288C24.7196 10.7288 25.1485 11.1576 25.1485 11.6905C25.1485 13.1461 24.1088 14.7577 22.7701 15.3556L14.0104 19.2546C13.5685 19.4495 13.0746 19.5535 12.5807 19.5535Z" fill="currentColor" /></svg></span></div>
                            <div class="app-feature-item-content">
                                <h4 class="app-feature-title">{{ __('dali.feature.f1_title') }}</h4>
                                <p>{{ __('dali.feature.f1_desc') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="app-feature-item mb-30" style="background: #ffffff; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
                            <div class="app-feature-item-icon"><span><svg xmlns="http://www.w3.org/2000/svg" style="color: #ff581e;" width="28" height="26" viewBox="0 0 28 26" fill="none"><g><path d="M11.1391 14.1861H15.1554V23.5445C15.1554 24.9222 16.8711 25.5721 17.7809 24.5323L27.6202 13.3542C28.478 12.3794 27.7891 10.8587 26.4894 10.8587H22.4731V1.50026C22.4731 0.1225 20.7574 -0.527389 19.8476 0.512433L10.0083 11.6905C9.16348 12.6653 9.85235 14.1861 11.1391 14.1861Z" fill="currentColor"/><path d="M10.0732 3.09894H0.974825C0.441921 3.09894 0 2.65702 0 2.12411C0 1.5912 0.441921 1.14928 0.974825 1.14928H10.0732C10.6061 1.14928 11.048 1.5912 11.048 2.12411C11.048 2.65702 10.6061 3.09894 10.0732 3.09894Z" fill="currentColor"/><path d="M8.77343 23.8956H0.974825C0.441921 23.8956 0 23.4536 0 22.9207C0 22.3878 0.441921 21.9459 0.974825 21.9459H8.77343C9.30633 21.9459 9.74825 22.3878 9.74825 22.9207C9.74825 23.4536 9.30633 23.8956 8.77343 23.8956Z" fill="currentColor"/><path d="M4.87413 13.4972H0.974825C0.441921 13.4972 0 13.0553 0 12.5224C0 11.9895 0.441921 11.5476 0.974825 11.5476H4.87413C5.40703 11.5476 5.84895 11.9895 5.84895 12.5224C5.84895 13.0553 5.40703 13.4972 4.87413 13.4972Z" fill="currentColor"/></g></svg></span></div>
                            <div class="app-feature-item-content">
                                <h4 class="app-feature-title">{{ __('dali.feature.f2_title') }}</h4>
                                <p>{{ __('dali.feature.f2_desc') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="app-feature-item mb-30" style="background: #ffffff; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
                            <div class="app-feature-item-icon"><span><svg xmlns="http://www.w3.org/2000/svg" style="color: #ff581e;" width="27" height="28" viewBox="0 0 27 28" fill="none"><path d="M20.864 12.3287C20.8913 12.683 20.7686 13.0509 20.4961 13.3234L12.2926 21.5268C10.4803 23.3391 8.65425 23.3391 6.82824 21.5268L1.36383 16.0624C0.437199 15.1222 -0.0261177 14.1819 0.00113616 13.2417H0.0965244L20.7005 12.3423L20.864 12.3287Z" fill="currentColor"/><path d="M20.4959 11.4976L9.29459 0.296382C8.89941 -0.0987938 8.24531 -0.0987938 7.85013 0.296382C7.45495 0.691557 7.45495 1.34564 7.85013 1.74081L9.03568 2.92633L1.36371 10.5982C0.491583 11.4703 0.0283901 12.3559 0.00113616 13.2417H0.0965244L20.7005 12.3423L20.864 12.3287C20.8504 12.0289 20.714 11.7156 20.4959 11.4976Z" fill="currentColor"/><path d="M19.0788 28H1.36382C0.805111 28 0.341795 27.5367 0.341795 26.978C0.341795 26.4193 0.805111 25.956 1.36382 25.956H19.0788C19.6376 25.956 20.1009 26.4193 20.1009 26.978C20.1009 27.5367 19.6376 28 19.0788 28Z" fill="currentColor"/><path d="M23.644 17.1392C23.2897 16.7577 22.6356 16.7577 22.2813 17.1392C21.8588 17.6025 19.7603 19.9735 19.7603 21.7586C19.7603 23.5301 21.1911 24.961 22.9626 24.961C24.7341 24.961 26.165 23.5301 26.165 21.7586C26.165 19.9735 24.0664 17.6025 23.644 17.1392Z" fill="currentColor"/></svg></span></div>
                            <div class="app-feature-item-content">
                                <h4 class="app-feature-title">{{ __('dali.feature.f3_title') }}</h4>
                                <p>{{ __('dali.feature.f3_desc') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="dali-sys-section">
        <div class="container container-1230">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <div class="dali-sys-img-wrap">
                        <img src="{{ asset('images/casambi/HybridDALISystem.jpg') }}" alt="Hybrid DALI System">
                        <div class="dali-sys-img-badge">
                            <span class="dali-sys-img-badge__num">250K+</span>
                            <span class="dali-sys-img-badge__txt">{{ __('dali.sys.badge_txt') }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <p class="dali-sys-eyebrow">{{ __('dali.sys.eyebrow') }}</p>
                    <h2 class="dali-sys-title">{!! __('dali.sys.title') !!}</h2>
                    <p class="dali-sys-desc">{{ __('dali.sys.desc') }}</p>
                    <div class="dali-sys-feats">
                        <div class="dali-sys-feat">
                            <div class="dali-sys-feat__icon"><svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" stroke="#ff581e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                            <div><div class="dali-sys-feat__title">{{ __('dali.sys.feat1_title') }}</div><div class="dali-sys-feat__sub">{{ __('dali.sys.feat1_sub') }}</div></div>
                        </div>
                        <div class="dali-sys-feat">
                            <div class="dali-sys-feat__icon"><svg width="18" height="18" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="#ff581e" stroke-width="2"/><path d="M8 12l3 3 5-5" stroke="#ff581e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                            <div><div class="dali-sys-feat__title">{{ __('dali.sys.feat2_title') }}</div><div class="dali-sys-feat__sub">{{ __('dali.sys.feat2_sub') }}</div></div>
                        </div>
                        <div class="dali-sys-feat">
                            <div class="dali-sys-feat__icon"><svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="#ff581e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                            <div><div class="dali-sys-feat__title">{{ __('dali.sys.feat3_title') }}</div><div class="dali-sys-feat__sub">{{ __('dali.sys.feat3_sub') }}</div></div>
                        </div>
                    </div>
                    <a href="#" class="dali-sys-cta">{{ __('dali.sys.cta') }} <svg width="15" height="15" fill="none" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
                </div>
            </div>
            <div class="dali-sys-apps">
                <div class="dali-sys-apps__group">
                    <div class="dali-sys-apps__label">{{ __('dali.sys.apps_label1') }}</div>
                    <div class="dali-sys-apps__chips">
                        @foreach(explode('|', __('dali.sys.apps_chips1')) as $chip)<span>{{ $chip }}</span>@endforeach
                    </div>
                </div>
                <div class="dali-sys-apps__divider"></div>
                <div class="dali-sys-apps__group">
                    <div class="dali-sys-apps__label">{{ __('dali.sys.apps_label2') }}</div>
                    <div class="dali-sys-apps__chips">
                        @foreach(explode('|', __('dali.sys.apps_chips2')) as $chip)<span>{{ $chip }}</span>@endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="app-stack-area app-stack-ptb app-stack-box stack-panel-pin-area">
        <div class="app-stack-contain">
            <div class="app-stack-item stack-item app-stack-item-01 stack-panel-pin pt-200 pb-200 atropos" data-atropos data-bg-color="#f8f7fd">
                <div class="app-stack-warpper"><div class="container container-1230"><div class="row">
                    <div class="col-lg-6"><div class="app-stack-heading"><span class="app-stack-subtitle">{{ __('dali.stack.s1_subtitle') }}</span><h4 class="app-stack-number">01</h4><h3 class="app-stack-title">{!! __('dali.stack.s1_title') !!}</h3><div class="app-stack-content"><p>{{ __('dali.stack.s1_desc') }}</p><div class="app-stack-btn"><a href="#">{{ __('dali.stack.s1_btn') }}</a></div></div></div></div>
                    <div class="col-lg-6"><div class="dali-stack-img-wrap"><img src="{{ asset('images/casambi/withoutlimitations.jpeg') }}" alt="DALI Project"></div></div>
                </div></div></div>
            </div>
            <div class="app-stack-item stack-item app-stack-item-02 stack-panel-pin pt-200 pb-200">
                <div class="app-stack-warpper"><div class="container container-1230"><div class="row">
                    <div class="col-lg-6"><div class="app-stack-heading"><span class="app-stack-subtitle">{{ __('dali.stack.s2_subtitle') }}</span><h4 class="app-stack-number">02</h4><h3 class="app-stack-title">{!! __('dali.stack.s2_title') !!}</h3><div class="app-stack-content"><p>{{ __('dali.stack.s2_desc') }}</p><div class="app-stack-btn"><a href="#">{{ __('dali.stack.s2_btn') }}</a></div></div></div></div>
                    <div class="col-lg-6"><div class="dali-stack-img-wrap"><img src="{{ asset('images/casambi/withouttheheadaches-Picsart-AiImageEnhancer.png') }}" alt=""></div></div>
                </div></div></div>
            </div>
            <div class="app-stack-item stack-item app-stack-item-03 stack-panel-pin pt-200 pb-200" data-bg-color="#f8f7fd">
                <div class="app-stack-warpper"><div class="container container-1230"><div class="row">
                    <div class="col-lg-6"><div class="app-stack-heading"><span class="app-stack-subtitle">{{ __('dali.stack.s3_subtitle') }}</span><h4 class="app-stack-number">03</h4><h3 class="app-stack-title">{!! __('dali.stack.s3_title') !!}</h3><div class="app-stack-content"><p>{{ __('dali.stack.s3_desc') }}</p><div class="app-stack-btn"><a href="#">{{ __('dali.stack.s3_btn') }}</a></div></div></div></div>
                    <div class="col-lg-6"><div class="dali-stack-img-wrap"><img src="{{ asset('images/casambi/yourfingertips.jpg') }}" alt=""></div></div>
                </div></div></div>
            </div>
        </div>
    </div>

    <div class="app-faq-area p-relative pb-120">
        <div class="container container-1230">
            <div class="row">
                <div class="col-lg-4">
                    <div class="app-faq-heading p-relative mb-40">
                        <span class="tp-section-subtitle border-bg bg-color tp_fade_anim" data-delay=".3">{{ __('dali.faq.label') }}</span>
                        <h3 class="tp-section-title-phudu fs-70 mb-20 tp_fade_anim" data-delay=".5">{!! __('dali.faq.heading') !!}</h3>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="app-faq-wrap pl-70">
                        <div class="ai-faq-accordion-wrap">
                            <div class="accordion" id="accordionExample">
                                @foreach(['q1','q2','q3','q4','q5','q6'] as $i => $k)
                                <div class="accordion-items">
                                    <h2 class="accordion-header">
                                        <button class="accordion-buttons{{ $i === 0 ? '' : ' collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#dali-faq-{{ $i }}" aria-expanded="{{ $i === 0 ? 'true' : 'false' }}">
                                            {{ __('dali.faq.' . $k) }}<span class="accordion-icon"></span>
                                        </button>
                                    </h2>
                                    <div id="dali-faq-{{ $i }}" class="accordion-collapse collapse{{ $i === 0 ? ' show' : '' }}" data-bs-parent="#accordionExample">
                                        <div class="accordion-body"><p>{{ __('dali.faq.' . str_replace('q', 'a', $k)) }}</p></div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                        <div class="app-cta-thumb-wrap"><div class="app-cta-thumb p-relative">
                            <div class="cta-slide-in cta-slide-left z-index-1"><img class="app-cta-thumb-1" src="{{ asset('images/casambi/casambiapp.webp') }}" alt="Casambi App"></div>
                            <div class="cta-slide-in cta-slide-right"><img class="app-cta-thumb-2" src="{{ asset('images/casambi/casambipro.png') }}" alt="Casambi Pro"></div>
                        </div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@push('scripts')
<script src="{{ asset('assets/js/atropos.js') }}"></script>
<style>
.cta-slide-in { opacity: 0; transition: opacity 0.8s ease, transform 0.8s ease; }
.cta-slide-left  { transform: translateX(-60px); }
.cta-slide-right { transform: translateX(60px); }
.cta-slide-in.visible { opacity: 1; transform: translateX(0); }
.cta-slide-right.visible { transition-delay: 0.2s; }
</style>
<script>
(function () {
    var els = document.querySelectorAll('.cta-slide-in');
    if (!els.length) return;
    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) { entry.target.classList.add('visible'); observer.unobserve(entry.target); }
        });
    }, { threshold: 0.2 });
    els.forEach(function (el) { observer.observe(el); });
})();
</script>
@endpush

@if(!empty($latestBlogs) && $latestBlogs->isNotEmpty())
<section class="home-latest-blogs" style="padding: 72px 0 60px;">
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

@endsection
