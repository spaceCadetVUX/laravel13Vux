
<!-- onsetting-admin -->
@php
    $locale         = app()->getLocale();
    $homeSeoTitle   = \App\Models\Setting::get('home_seo_title_' . $locale)
                      ?: config('app.name');
    $homeSeoDesc    = \App\Models\Setting::get('home_seo_description_' . $locale) ?? '';
    $homeSeoKw      = \App\Models\Setting::get('home_seo_keywords_' . $locale) ?? '';
    $homeOgImageRaw = \App\Models\Setting::get('home_og_image')
                      ?: \App\Models\Setting::get('default_og_image');
    $homeOgImage    = $homeOgImageRaw
        ? (str_starts_with($homeOgImageRaw, 'http') ? $homeOgImageRaw : asset($homeOgImageRaw))
        : asset('images/casambi/0-hero-banner.jpg');
@endphp

@extends('front.layouts.frontend', [
    'seo' => [
        'title'          => $homeSeoTitle,
        'description'    => $homeSeoDesc,
        'keywords'       => $homeSeoKw,
        'og_title'       => $homeSeoTitle,
        'og_description' => $homeSeoDesc,
        'image'          => $homeOgImage,
        'canonical'      => url(request()->path()),
        'robots'         => 'index, follow',
        'type'           => 'website',
        'hreflangs'      => [
            'en' => switch_locale_url('en'),
            'vi' => switch_locale_url('vi'),
        ]
    ]
])

{{-- Homepage Schema: WebSite + Organization --}}
@push('head')
@php
    $locale    = app()->getLocale();
    $searchUrl = $locale === 'vi'
        ? url('vi/san-pham/search') . '?q={search_term_string}'
        : url('en/products/search') . '?q={search_term_string}';

    $appName = config('app.name');
    $appUrl  = config('app.url');

    // Logo URL: prefer Settings DB upload, fallback to default asset
    $logoRaw  = \App\Models\Setting::get('site_logo');
    $logoUrl  = $logoRaw
        ? (str_starts_with($logoRaw, 'http') ? $logoRaw : url(asset($logoRaw)))
        : url(asset('assets/img/logo/logo.png'));

    $brandName = 'Casambi Vietnam';
    $orgDesc   = $locale === 'vi'
        ? 'Đối tác chính thức của Casambi Technologies tại Việt Nam, cung cấp giải pháp điều khiển chiếu sáng thông minh không dây và DALI cho các công trình dân dụng, thương mại và công nghiệp.'
        : 'Official partner of Casambi Technologies in Vietnam, providing wireless smart lighting control and DALI solutions for residential, commercial, and industrial projects.';

    $websiteSchema = [
        '@context' => 'https://schema.org',
        '@type'    => 'WebSite',
        '@id'      => $appUrl . '/#website',
        'name'     => $brandName,
        'url'      => $appUrl,
        'potentialAction' => [
            '@type'       => 'SearchAction',
            'target'      => [
                '@type'       => 'EntryPoint',
                'urlTemplate' => $searchUrl,
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ];

    $organizationSchema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Organization',
        '@id'         => $appUrl . '/#organization',
        'name'        => $brandName,
        'url'         => $appUrl,
        'description' => $orgDesc,
        'logo'        => [
            '@type' => 'ImageObject',
            'url'   => $logoUrl,
        ],
    ];

    // contactPoint for AI engines
    $orgPhone = \App\Models\Setting::get('contact_phone');
    $orgEmail = \App\Models\Setting::get('contact_email');
    if ($orgPhone || $orgEmail) {
        $cp = ['@type' => 'ContactPoint', 'contactType' => 'customer service', 'availableLanguage' => ['Vietnamese', 'English']];
        if ($orgPhone) $cp['telephone'] = $orgPhone;
        if ($orgEmail) $cp['email']     = $orgEmail;
        $organizationSchema['contactPoint'] = $cp;
    }

    // sameAs — read from Settings DB (managed via Admin → Settings → Social Media)
    $socialLinks = array_filter([
        \App\Models\Setting::get('social_facebook'),
        \App\Models\Setting::get('social_instagram'),
        \App\Models\Setting::get('social_youtube'),
        \App\Models\Setting::get('social_tiktok'),
    ]);
    if (!empty($socialLinks)) {
        $organizationSchema['sameAs'] = array_values($socialLinks);
    }

    // LocalBusiness schema — data từ Settings DB
    $localBusinessSchema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'LocalBusiness',
        '@id'         => $appUrl . '/#localbusiness',
        'name'        => $brandName,
        'url'         => $appUrl,
        'description' => $orgDesc,
        'logo'        => ['@type' => 'ImageObject', 'url' => $logoUrl],
        'parentOrganization' => ['@id' => $appUrl . '/#organization'],
    ];

    $contactEmail   = \App\Models\Setting::get('contact_email');
    $contactPhone   = \App\Models\Setting::get('contact_phone');
    $contactAddress = \App\Models\Setting::get('contact_address');
    $contactCity    = \App\Models\Setting::get('contact_city');
    $contactCountry = \App\Models\Setting::get('contact_country');
    $contactPostal  = \App\Models\Setting::get('contact_postal_code');
    $contactHours   = \App\Models\Setting::get('contact_working_hours');
    $contactMaps    = \App\Models\Setting::get('contact_maps_url');

    if ($contactEmail)  $localBusinessSchema['email'] = $contactEmail;
    if ($contactPhone)  $localBusinessSchema['telephone'] = $contactPhone;
    if ($contactMaps)   $localBusinessSchema['hasMap'] = $contactMaps;
    if ($contactHours)  $localBusinessSchema['openingHours'] = $contactHours;

    if ($contactAddress || $contactCity || $contactCountry) {
        $localBusinessSchema['address'] = array_filter([
            '@type'           => 'PostalAddress',
            'streetAddress'   => $contactAddress,
            'addressLocality' => $contactCity,
            'addressCountry'  => ($contactCountry === 'Vietnam' || $contactCountry === 'Việt Nam') ? 'VN' : $contactCountry,
            'postalCode'      => $contactPostal,
        ]);
    }

    if (!empty($socialLinks)) {
        $localBusinessSchema['sameAs'] = array_values($socialLinks);
    }

    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
@endphp
<script type="application/ld+json">{!! json_encode($websiteSchema, $jsonFlags) !!}</script>
<script type="application/ld+json">{!! json_encode($organizationSchema, $jsonFlags) !!}</script>
<script type="application/ld+json">{!! json_encode($localBusinessSchema, $jsonFlags) !!}</script>
@php
    $homeFaqs = $locale === 'vi' ? [
        ['q' => 'Casambi Vietnam là ai?',
         'a' => 'Casambi Vietnam là đối tác chính thức của Casambi Technologies tại Việt Nam, cung cấp giải pháp điều khiển chiếu sáng thông minh không dây và DALI cho các công trình dân dụng, thương mại và công nghiệp.'],
        ['q' => 'Casambi có những giải pháp chiếu sáng nào?',
         'a' => 'Casambi cung cấp hai giải pháp chính: Casambi Wireless (điều khiển qua Bluetooth Mesh, không cần hub) và Casambi DALI (tích hợp giao thức DALI chuẩn với giao diện không dây). Cả hai đều điều khiển qua smartphone hoặc tablet.'],
        ['q' => 'Làm thế nào để liên hệ tư vấn giải pháp Casambi?',
         'a' => 'Bạn có thể liên hệ trực tiếp qua form trên website, gọi điện hoặc nhắn tin Zalo. Đội ngũ kỹ thuật sẽ tư vấn và thiết kế giải pháp phù hợp với từng công trình.'],
        ['q' => 'Sản phẩm Casambi có bảo hành không?',
         'a' => 'Tất cả sản phẩm Casambi phân phối bởi Casambi Vietnam đều là hàng chính hãng, có bảo hành từ 12–24 tháng tùy sản phẩm, kèm hỗ trợ kỹ thuật trong suốt thời gian sử dụng.'],
    ] : [
        ['q' => 'Who is Casambi Vietnam?',
         'a' => 'Casambi Vietnam is the official partner of Casambi Technologies in Vietnam, providing wireless smart lighting control and DALI solutions for residential, commercial, and industrial projects.'],
        ['q' => 'What lighting solutions does Casambi offer?',
         'a' => 'Casambi offers two main solutions: Casambi Wireless (Bluetooth Mesh control, no hub required) and Casambi DALI (standard DALI protocol with wireless interface). Both are controlled via smartphone or tablet.'],
        ['q' => 'How can I get a consultation?',
         'a' => 'You can contact us via the website form, phone, or Zalo. Our technical team will consult and design a solution tailored to your project.'],
        ['q' => 'Do Casambi products come with a warranty?',
         'a' => 'All Casambi products distributed by Casambi Vietnam are genuine, with 12–24 months warranty depending on the product, along with technical support throughout the usage period.'],
    ];
    $homeFaqSchema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => array_map(fn($f) => [
            '@type'          => 'Question',
            'name'           => $f['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
        ], $homeFaqs),
    ];
@endphp
<script type="application/ld+json">{!! json_encode($homeFaqSchema, $jsonFlags) !!}</script>
@endpush


@section('content')

    <!-- magic cursor -->
    <div id="magic-cursor" class="cursor-white-bg">
        <div id="ball"></div>
    </div>


    <!-- back to top -->
    <div class="back-to-top-wrapper">
        <button id="back_to_top" type="button" class="back-to-top-btn">
            <svg width="12" height="7" viewBox="0 0 12 7" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M11 6L6 1L1 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
    </div>

    <!-- hero area -->
    <div class="tp-hero-area tp-hero-ptb  p-relative fix z-index-1" data-background="{{ asset('images/casambi/luma5-2000x1334.jpg') }}">
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
                        <div class="tp-hero-info d-flex align-items-start justify-content-between tp_text_anim" style="background-color: rgba(0, 0, 0, 0.1); padding: 20px; border-radius: 8px;">
                            <h1 style="color: white; font-size: 1.2rem;">{{ __('index.hero.desc') }}</h1>
                            <span>
                                <a href="#contact" onclick="openContactPopup();return false;" aria-label="{{ app()->getLocale() === 'vi' ? 'Liên hệ' : 'Contact us' }}">
                                    <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M1 21L21 1M21 1H1M21 1V21" stroke="currentcolor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M1 21L21 1M21 1H1M21 1V21" stroke="currentcolor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </a>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- hero area end -->

    <!-- about area start -->
    <div class="tp-about-area pt-140 pb-120 tp-bounce-trigger">
        <div class="container">
            <div class="tp-about-box p-relative">
                <div class="tp-about-shape-1 tp-bounce d-none d-md-block">
                    
                </div>
                <div class="row">
                    <div class="col-xl-3">
                        <div class="tp-about-title-box">
                            <h2 class="tp-section-subtitle pre tp_fade_anim">{{ __('index.about.label') }}</h2>
                        </div>
                    </div>
                    <div class="col-xl-9">
                        <div class="tp-about-wrap">
                            <div class="tp-about-text tp_fade_anim">
                                <p>
                                    {!! __('index.about.desc') !!}
                                </p>
                            </div>
                            <div class="row align-items-center mt-50">
                                <div class="col-xl-5 col-lg-5 col-md-5">
                                    <div class="tp-about-thumb">
                                        <img data-speed=".8" src="{{ asset('images/casambi/warmligth.jpg') }}" alt="Casambi smart lighting ambience">
                                    </div>
                                </div>
                                <div class="col-xl-7 col-lg-7 col-md-7">
                                    <div class="tp-about-funcact-wrap ps-xl-4">
                                        <div class="tp-about-avater-info" style="border-bottom: 1px solid #e8e8e8; padding-bottom: 22px; margin-bottom: 22px;">
                                            <span style="font-size: 12px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; color: #ff5622;">{{ __('index.about.f1_tag') }}</span>
                                            <h3 style="font-size: 22px; font-weight: 600; margin: 10px 0 8px;">{{ __('index.about.f1_title') }}</h3>
                                            <p style="color: #666; font-size: 15px; line-height: 1.6;">{{ __('index.about.f1_desc') }}</p>
                                        </div>
                                        <div class="tp-about-avater-info" style="border-bottom: 1px solid #e8e8e8; padding-bottom: 22px; margin-bottom: 22px;">
                                            <span style="font-size: 12px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; color: #ff5622;">{{ __('index.about.f2_tag') }}</span>
                                            <h3 style="font-size: 22px; font-weight: 600; margin: 10px 0 8px;">{{ __('index.about.f2_title') }}</h3>
                                            <p style="color: #666; font-size: 15px; line-height: 1.6;">{{ __('index.about.f2_desc') }}</p>
                                        </div>
                                        <div class="tp-about-avater-info" style="padding-bottom: 10px;">
                                            <span style="font-size: 12px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; color: #ff5622;">{{ __('index.about.f3_tag') }}</span>
                                            <h3 style="font-size: 22px; font-weight: 600; margin: 10px 0 8px;">{{ __('index.about.f3_title') }}</h3>
                                            <p style="color: #666; font-size: 15px; line-height: 1.6;">{{ __('index.about.f3_desc') }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- about area end -->

    <!-- banner area start -->
    <div class="tp-banner-area">
        <div class="tp-banner-img">
            <img class="w-100" data-speed=".7" src="{{ asset('images/casambi/worldlight-officinas-04-scaled.jpg') }}" alt="Casambi lighting installation project">
        </div>
    </div>
    <!-- banner area end -->


    <!-- service area start -->
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
                <div class="tp-service-item tp-service-panel">
                    <div class="row">
                        <div class="col-xxl-3 col-xl-2 col-lg-1 col-md-1">
                            <div class="tp-service-number"><span>01.</span></div>
                        </div>
                        <div class="col-xxl-5 col-xl-6 col-lg-7 col-md-7">
                            <div class="tp-service-content">
                                <h3 class="tp-section-title-phudu"><a class="tp_text_invert" href="#">{{ __('index.service.s1_title') }}</a></h3>
                                <p>{{ __('index.service.s1_desc') }}</p>
                                <div class="tp-service-category">
                                    @foreach(explode('|', __('index.service.s1_tags')) as $tag)
                                        <span>{{ $tag }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-xl-4 col-lg-4 col-md-4">
                            <div class="tp-service-thumb text-end">
                                <img class="tp_fade_anim" data-fade-from="right" data-delay=".2" src="{{ asset('images/casambi/yourfingertips.jpg') }}" alt="Casambi app control at your fingertips">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tp-service-item tp-service-panel">
                    <div class="row">
                        <div class="col-xxl-3 col-xl-2 col-lg-1 col-md-1">
                            <div class="tp-service-number"><span>02.</span></div>
                        </div>
                        <div class="col-xxl-5 col-xl-6 col-lg-7 col-md-7">
                            <div class="tp-service-content">
                                <h3 class="tp-section-title-phudu"><a class="tp_text_invert" href="#">{{ __('index.service.s2_title') }}</a></h3>
                                <p>{{ __('index.service.s2_desc') }}</p>
                                <div class="tp-service-category">
                                    @foreach(explode('|', __('index.service.s2_tags')) as $tag)
                                        <span>{{ $tag }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-xl-4 col-lg-4 col-md-4">
                            <div class="tp-service-thumb text-end">
                                <img class="tp_fade_anim" data-fade-from="right" data-delay=".2" src="{{ asset('images/casambi/Fastest-Installation.jpg') }}" alt="Casambi wireless fastest installation">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tp-service-item tp-service-panel">
                    <div class="row">
                        <div class="col-xxl-3 col-xl-2 col-lg-1 col-md-1">
                            <div class="tp-service-number"><span>03.</span></div>
                        </div>
                        <div class="col-xxl-5 col-xl-6 col-lg-7 col-md-7">
                            <div class="tp-service-content">
                                <h3 class="tp-section-title-phudu"><a class="tp_text_invert" href="#">{{ __('index.service.s3_title') }}</a></h3>
                                <p>{{ __('index.service.s3_desc') }}</p>
                                <div class="tp-service-category">
                                    @foreach(explode('|', __('index.service.s3_tags')) as $tag)
                                        <span>{{ $tag }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-xl-4 col-lg-4 col-md-4">
                            <div class="tp-service-thumb text-end">
                                <img class="tp_fade_anim" data-fade-from="right" data-delay=".2" src="{{ asset('images/casambi/casambipro.png') }}" alt="Casambi Pro platform dashboard">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tp-service-item tp-service-panel">
                    <div class="row">
                        <div class="col-xxl-3 col-xl-2 col-lg-1 col-md-1">
                            <div class="tp-service-number"><span>04.</span></div>
                        </div>
                        <div class="col-xxl-5 col-xl-6 col-lg-7 col-md-7">
                            <div class="tp-service-content">
                                <h3 class="tp-section-title-phudu"><a class="tp_text_invert" href="#">{{ __('index.service.s4_title') }}</a></h3>
                                <p>{{ __('index.service.s4_desc') }}</p>
                                <div class="tp-service-category">
                                    @foreach(explode('|', __('index.service.s4_tags')) as $tag)
                                        <span>{{ $tag }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-xl-4 col-lg-4 col-md-4">
                            <div class="tp-service-thumb text-end">
                                <img class="tp_fade_anim" data-fade-from="right" data-delay=".2" src="{{ asset('images/casambi/office.jpeg') }}" alt="Casambi smart lighting office environment">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- service area end -->

    <!-- technology tabs area start -->
    <div class="tp-tech-tabs-area pt-160 pb-120">
        <div class="container">
            <div class="row justify-content-center text-center mb-55">
                <div class="col-xl-8">
                    <h2 class="tp-section-title tp-char-animation" style="font-size: 52px; font-weight: 800; line-height: 1.1; letter-spacing: -1.5px; margin-bottom: 20px; text-transform: none;">
                        {!! __('index.tech.heading') !!}
                    </h2>
                    <p style="font-size: 17px; color: #666; line-height: 1.7; max-width: 640px; margin: 0 auto;">
                        {{ __('index.tech.subheading') }}
                    </p>
                </div>
            </div>
            <div class="row justify-content-center mb-40">
                <div class="col-auto">
                    <div class="tp-tech-tab-nav" style="display: inline-flex; background: #f2f0ed; border-radius: 50px; padding: 5px; gap: 4px;">
                        <button class="tp-tech-tab-btn active" data-tab="dali" style="padding: 12px 36px; border-radius: 50px; border: none; font-size: 15px; font-weight: 600; cursor: pointer; transition: all .3s ease; background: #f9561a; color: #fff;">{{ __('index.tech.tab_dali') }}</button>
                        <button class="tp-tech-tab-btn" data-tab="wireless" style="padding: 12px 36px; border-radius: 50px; border: none; font-size: 15px; font-weight: 600; cursor: pointer; transition: all .3s ease; background: transparent; color: #111013;">{{ __('index.tech.tab_wireless') }}</button>
                        <button class="tp-tech-tab-btn" data-tab="hybrid" style="padding: 12px 36px; border-radius: 50px; border: none; font-size: 15px; font-weight: 600; cursor: pointer; transition: all .3s ease; background: transparent; color: #111013;">{{ __('index.tech.tab_hybrid') }}</button>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <div class="tp-tech-video-wrap" style="position: relative; border-radius: 16px; overflow: hidden; background: #111013; aspect-ratio: 16/9; width: 100%;">
                        <div class="tp-tech-panel active" data-panel="dali" style="position: absolute; inset: 0; opacity: 1; transition: opacity .5s ease;">
                            <video src="{{ asset('assets/video/Room-1-loop.mp4') }}" autoplay muted loop playsinline style="width: 100%; height: 100%; object-fit: cover; pointer-events: none; display: block;"></video>
                            <div class="tp-tech-panel-caption" style="position: absolute; bottom: 36px; left: 40px; color: #fff;">
                                <span style="font-size: 12px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; color: #ff5622; display: block; margin-bottom: 8px;">{{ __('index.tech.tab_dali') }}</span>
                                <p style="font-size: 16px; opacity: .8; max-width: 400px; line-height: 1.6; margin: 0; color: white;">{{ __('index.tech.dali_cap') }}</p>
                            </div>
                        </div>
                        <div class="tp-tech-panel" data-panel="wireless" style="position: absolute; inset: 0; opacity: 0; transition: opacity .5s ease;">
                            <video src="{{ asset('assets/video/Room-2-loop.mp4') }}" autoplay muted loop playsinline style="width: 100%; height: 100%; object-fit: cover; pointer-events: none; display: block;"></video>
                            <div class="tp-tech-panel-caption" style="position: absolute; bottom: 36px; left: 40px; color: #fff;">
                                <span style="font-size: 12px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; color: #ff5622; display: block; margin-bottom: 8px;">{{ __('index.tech.tab_wireless') }}</span>
                                <p style="font-size: 16px; opacity: .8; max-width: 400px; line-height: 1.6; margin: 0; color: white;">{{ __('index.tech.wireless_cap') }}</p>
                            </div>
                        </div>
                        <div class="tp-tech-panel" data-panel="hybrid" style="position: absolute; inset: 0; opacity: 0; transition: opacity .5s ease;">
                            <video src="{{ asset('assets/video/Room-3-loop.mp4') }}" autoplay muted loop playsinline style="width: 100%; height: 100%; object-fit: cover; pointer-events: none; display: block;"></video>
                            <div class="tp-tech-panel-caption" style="position: absolute; bottom: 36px; left: 40px; color: #fff;">
                                <span style="font-size: 12px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; color: #ff5622; display: block; margin-bottom: 8px;">{{ __('index.tech.tab_hybrid') }}</span>
                                <p style="font-size: 16px; opacity: .8; max-width: 400px; line-height: 1.6; margin: 0; color: white;">{{ __('index.tech.hybrid_cap') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- technology tabs area end -->


    <!-- list of productss -->
    @if(!empty($homeCategoryProducts))
    <section class="home-cat-products">
        <div class="container">
            @foreach($homeCategoryProducts as $catId => $data)
            @php
                $cat      = $data['category'];
                $products = $data['products'];
                $sliderId = 'hcp-slider-' . $catId;
                $catUrl   = route(current_locale() . '.product.category', ['category' => $cat->slug]);
            @endphp
            @if($products->isNotEmpty())
            <div class="hcp-row">
                <!-- header -->
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
                <!-- slider -->
                <div class="hcp-slider-wrap">
                    <div class="hcp-slider" id="{{ $sliderId }}">
                        @foreach($products as $product)
                        <div class="hcp-item">
                            @include('front.components.product-card', [
                                'url'      => route(current_locale() . '.product.show', $product->slug),
                                'image'    => $product->image_url ? (str_starts_with($product->image_url, 'http') ? $product->image_url : asset($product->image_url)) : asset('images/casambi/product-placeholder.jpg'),
                                'name'     => $product->name,
                                'price'    => ($product->sale_price > 0 && $product->sale_price < $product->price) ? number_format($product->sale_price, 0, ',', '.') . 'đ' : ($product->price > 0 ? number_format($product->price, 0, ',', '.') . 'đ' : null),
                                'oldPrice' => ($product->sale_price > 0 && $product->price > 0 && $product->sale_price < $product->price) ? number_format($product->price, 0, ',', '.') . 'đ' : null,
                                'tag'      => $product->featured ? 'badge-featured' : ($product->sale_price && $product->sale_price < $product->price ? 'badge-sale' : null),
                                'tagLabel' => $product->featured ? __('shop.labels.badge_featured') : ($product->sale_price && $product->sale_price < $product->price ? __('shop.labels.badge_sale') : null),
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

    @if(!empty($latestBlogs) && $latestBlogs->isNotEmpty())
    <section class="home-latest-blogs">
        <div class="container">
            <div class="hcp-header" style="margin-bottom:24px;">
                <a href="{{ route(current_locale() . '.blog.index') }}" class="hcp-title">{{ app()->getLocale() === 'vi' ? 'Bài viết mới nhất' : 'Latest Articles' }}</a>
                <a href="{{ route(current_locale() . '.blog.index') }}" class="blog-view-all">
                    {{ app()->getLocale() === 'vi' ? 'Xem tất cả' : 'View all' }}
                    <svg width="11" height="11" viewBox="0 0 12 12" fill="none"><path d="M1 11L11 1M11 1H1M11 1V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
            </div>
            <div class="row g-4">
                @foreach($latestBlogs as $blog)
                <div class="col-md-6 col-lg-4">
                    <div class="blog-card">
                        <a href="{{ route(current_locale() . '.blog.show', $blog->slug) }}" class="blog-card__img-wrap d-block">
                            @if($blog->featured_image)
                                <img src="{{ asset($blog->featured_image) }}" alt="{{ $blog->title }}" loading="lazy">
                            @else
                                <div class="blog-card__img-placeholder"></div>
                            @endif
                        </a>
                        @if($blog->category)
                        <div class="blog-card__category">
                            <svg width="11" height="11" viewBox="0 0 15 14" fill="none"><path d="M4.39012 4.13048H4.39847M13.6056 8.14369L8.74375 12.6328C8.61780 12.7492 8.46823 12.8415 8.30359 12.9046C8.13896 12.9676 7.96248 13 7.78426 13C7.60604 13 7.42956 12.9676 7.26493 12.9046C7.10029 12.8415 6.95072 12.7492 6.82477 12.6328L1 7.2609V1H7.78087L13.6056 6.37811C13.8582 6.61273 14 6.93009 14 7.2609C14 7.59171 13.8582 7.90908 13.6056 8.14369Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            {{ $blog->category }}
                        </div>
                        @endif
                        <h2 class="blog-card__title">
                            <a href="{{ route(current_locale() . '.blog.show', $blog->slug) }}">{{ Str::limit($blog->title, 65) }}</a>
                        </h2>
                        @if($blog->excerpt)
                        <p class="blog-card__excerpt">{{ strip_tags($blog->excerpt) }}</p>
                        @endif
                        <div class="d-flex align-items-center justify-content-between mt-auto">
                            <a href="{{ route(current_locale() . '.blog.show', $blog->slug) }}" class="blog-card__read-more">
                                {{ app()->getLocale() === 'vi' ? 'Đọc thêm' : 'Read more' }}
                                <svg width="11" height="11" viewBox="0 0 12 12" fill="none"><path d="M1 11L11 1M11 1H1M11 1V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                            <div class="blog-card__date">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                {{ $blog->formatted_published_date }}
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif
    
    <!-- cta area start -->
    <div class="app-cta-area z-index-1">
        <div class="container container-1430">
            <div class="app-cta-wrap">
                <div class="row align-items-end">
                    <div class="col-lg-6">
                        <div class="app-cta-wrapper pt-90 pb-90">
                            <div class="app-cta-heading mb-30">
                                <h3 class="tp-section-title-phudu fs-70 mb-20 tp_fade_anim" data-delay=".3">
                                    {!! __('dali.cta.heading') !!}
                                </h3>
                                <div class="tp_fade_anim" data-delay=".5">
                                    <p>{{ __('dali.cta.desc') }}</p>
                                </div>
                            </div>
                            <div class="app-cta-store-box d-flex align-items-center tp_fade_anim" data-delay=".3" data-fade-from="top" data-ease="bounce">
                                <a href="{{ __('dali.cta.store1_url') }}" target="_blank" rel="noopener" class="app-cta-store mr-15">
                                    <div class="app-cta-store-icon">
                                        <span><img src="" alt=""></span>
                                    </div>
                                    <div class="app-cta-store-content">
                                        <p>{{ __('dali.cta.store1_label') }}</p>
                                        <span>{{ __('dali.cta.store1_name') }}</span>
                                    </div>
                                </a>
                                <a href="{{ __('dali.cta.store2_url') }}" target="_blank" rel="noopener" class="app-cta-store">
                                    <div class="app-cta-store-icon">
                                        <span><img src="" alt=""></span>
                                    </div>
                                    <div class="app-cta-store-content">
                                        <p>{{ __('dali.cta.store2_label') }}</p>
                                        <span>{{ __('dali.cta.store2_name') }}</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="app-cta-thumb-wrap">
                            <div class="app-cta-thumb p-relative">
                                <div class="cta-slide-in cta-slide-left z-index-1">
                                    <img class="app-cta-thumb-1" src="{{ asset('images/casambi/casambiapp.webp') }}" alt="Casambi App">
                                </div>
                                <div class="cta-slide-in cta-slide-right">
                                    <img class="app-cta-thumb-2" src="{{ asset('images/casambi/casambipro.png') }}" alt="Casambi Pro">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- cta area end -->

    {{-- ═══ FAQ Section ══════════════════════════════════════════════════════ --}}
    @php
        $faqLocale = app()->getLocale();
        $faqItems = $faqLocale === 'vi' ? [
            ['q' => 'Casambi Vietnam là ai?',
             'a' => 'Casambi Vietnam là đối tác chính thức của Casambi Technologies tại Việt Nam, cung cấp giải pháp điều khiển chiếu sáng thông minh không dây và DALI cho các công trình dân dụng, thương mại và công nghiệp.'],
            ['q' => 'Casambi có những giải pháp chiếu sáng nào?',
             'a' => 'Casambi cung cấp hai giải pháp chính: Casambi Wireless (điều khiển qua Bluetooth Mesh, không cần hub) và Casambi DALI (tích hợp giao thức DALI chuẩn với giao diện không dây). Cả hai đều điều khiển qua smartphone hoặc tablet.'],
            ['q' => 'Làm thế nào để liên hệ tư vấn giải pháp Casambi?',
             'a' => 'Bạn có thể liên hệ trực tiếp qua form trên website, gọi điện hoặc nhắn tin Zalo. Đội ngũ kỹ thuật sẽ tư vấn và thiết kế giải pháp phù hợp với từng công trình.'],
            ['q' => 'Sản phẩm Casambi có bảo hành không?',
             'a' => 'Tất cả sản phẩm Casambi phân phối bởi Casambi Vietnam đều là hàng chính hãng, có bảo hành từ 12–24 tháng tùy sản phẩm, kèm hỗ trợ kỹ thuật trong suốt thời gian sử dụng.'],
        ] : [
            ['q' => 'Who is Casambi Vietnam?',
             'a' => 'Casambi Vietnam is the official partner of Casambi Technologies in Vietnam, providing wireless smart lighting control and DALI solutions for residential, commercial, and industrial projects.'],
            ['q' => 'What lighting solutions does Casambi offer?',
             'a' => 'Casambi offers two main solutions: Casambi Wireless (Bluetooth Mesh control, no hub required) and Casambi DALI (standard DALI protocol with wireless interface). Both are controlled via smartphone or tablet.'],
            ['q' => 'How can I get a consultation?',
             'a' => 'You can contact us via the website form, phone, or Zalo. Our technical team will consult and design a solution tailored to your project.'],
            ['q' => 'Do Casambi products come with a warranty?',
             'a' => 'All Casambi products distributed by Casambi Vietnam are genuine, with 12–24 months warranty depending on the product, along with technical support throughout the usage period.'],
        ];
    @endphp
    <section class="home-faq-section">
        <div class="container">
            <h2 class="home-faq__title">{{ $faqLocale === 'vi' ? 'Câu hỏi thường gặp' : 'Frequently Asked Questions' }}</h2>
            <div class="home-faq__list">
                @foreach($faqItems as $faq)
                <details class="home-faq__item">
                    <summary class="home-faq__question">{{ $faq['q'] }}</summary>
                    <div class="home-faq__answer">{{ $faq['a'] }}</div>
                </details>
                @endforeach
            </div>
        </div>
    </section>

    <style>
    /* ── Technology tabs responsive ── */
    @media (max-width: 991px) {
        .tp-tech-tabs-area { padding-top: 80px !important; padding-bottom: 70px !important; }
        .tp-tech-tabs-area .tp-section-title { font-size: 36px !important; letter-spacing: -0.5px !important; }
    }
    @media (max-width: 767px) {
        .tp-tech-tabs-area { padding-top: 56px !important; padding-bottom: 48px !important; }
        .tp-tech-tabs-area .tp-section-title { font-size: 26px !important; letter-spacing: 0 !important; margin-bottom: 14px !important; }
        .tp-tech-tabs-area p { font-size: 15px !important; }
        /* tab nav — stack or shrink on small screens */
        .tp-tech-tab-nav { display: flex !important; flex-wrap: wrap; justify-content: center; border-radius: 16px !important; padding: 4px !important; gap: 4px !important; }
        .tp-tech-tab-btn { padding: 9px 18px !important; font-size: 13px !important; border-radius: 12px !important; flex: 1 1 auto; text-align: center; }
        /* caption inside video — smaller & more room */
        .tp-tech-panel-caption { bottom: 16px !important; left: 16px !important; right: 16px !important; }
        .tp-tech-panel-caption span { font-size: 10px !important; margin-bottom: 5px !important; }
        .tp-tech-panel-caption p { font-size: 13px !important; max-width: 100% !important; }
    }

    .home-latest-blogs { padding: 60px 0; }
    .blog-view-all {
        font-size: .8rem;
        font-weight: 600;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #111013;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .blog-view-all:hover { color: #f9561a; }

    .home-cat-products { padding: 60px 0; }
    .hcp-row { margin-bottom: 56px; }
    .hcp-row:last-child { margin-bottom: 0; }

    .hcp-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
    }
    .hcp-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #111013;
        text-decoration: none;
        letter-spacing: -0.01em;
    }
    .hcp-title:hover { color: #f9561a; }

    .hcp-nav { display: flex; gap: 8px; }
    .hcp-btn {
        width: 40px; height: 40px;
        border: 1px solid #e0e0e0;
        border-radius: 50%;
        background: #fff;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        transition: background .2s, border-color .2s, color .2s;
        color: #111013;
    }
    .hcp-btn:hover { background: #111013; border-color: #111013; color: #fff; }
    .hcp-btn:disabled { opacity: .35; cursor: default; pointer-events: none; }

    .hcp-slider-wrap { overflow: hidden; }
    .hcp-slider {
        display: flex;
        flex-wrap: nowrap;
        gap: 24px;
        transition: transform .4s cubic-bezier(.4,0,.2,1);
    }
    .hcp-item { flex: 0 0 calc(25% - 18px); min-width: 0; }

    @media (max-width: 991px) {
        .hcp-item { flex: 0 0 calc(33.333% - 16px); }
    }
    @media (max-width: 767px) {
        .hcp-slider { gap: 12px; }
        .hcp-item { flex: 0 0 calc(50% - 6px); }
        .hcp-title { font-size: 1.15rem; }
    }
    </style>

    @push('scripts')
    <script>
    (function () {
        var COLS = 4;
        function getCols() {
            if (window.innerWidth <= 767) return 2;
            if (window.innerWidth <= 991) return 3;
            return 4;
        }

        document.querySelectorAll('.hcp-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var sliderId = btn.getAttribute('data-slider');
                var dir      = btn.getAttribute('data-dir');
                var slider   = document.getElementById(sliderId);
                if (!slider) return;

                var items     = slider.querySelectorAll('.hcp-item');
                var cols      = getCols();
                var total     = items.length;
                var pages     = Math.ceil(total / cols);
                var current   = parseInt(slider.getAttribute('data-page') || '0');

                if (dir === 'next') current = Math.min(current + 1, pages - 1);
                else                current = Math.max(current - 1, 0);

                slider.setAttribute('data-page', current);

                // Slide via translateX: move by (cols * item+gap) per page
                var itemW   = slider.querySelector('.hcp-item').offsetWidth;
                var gap     = parseInt(getComputedStyle(slider).gap) || 24;
                var offset  = current * cols * (itemW + gap);
                slider.style.transform = 'translateX(-' + offset + 'px)';

                // Update button states
                var wrap   = btn.closest('.hcp-row');
                var btns   = wrap.querySelectorAll('.hcp-btn');
                btns.forEach(function (b) {
                    var d = b.getAttribute('data-dir');
                    b.disabled = (d === 'prev' && current === 0) || (d === 'next' && current === pages - 1);
                });
            });
        });

        // Init disabled state
        document.querySelectorAll('.hcp-row').forEach(function (row) {
            var slider = row.querySelector('.hcp-slider');
            if (!slider) return;
            var total = slider.querySelectorAll('.hcp-item').length;
            var cols  = getCols();
            var prev  = row.querySelector('[data-dir="prev"]');
            var next  = row.querySelector('[data-dir="next"]');
            if (prev) prev.disabled = true;
            if (next) next.disabled = total <= cols;
        });
    })();
    </script>
    @endpush
    @endif



@push('scripts')
<script>
(function () {
    var btns = document.querySelectorAll('.tp-tech-tab-btn');
    var panels = document.querySelectorAll('.tp-tech-panel');
    btns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = this.getAttribute('data-tab');
            btns.forEach(function (b) {
                b.classList.remove('active');
                b.style.background = 'transparent';
                b.style.color = '#111013';
            });
            this.classList.add('active');
            this.style.background = '#f9561a';
            this.style.color = '#fff';
            panels.forEach(function (p) {
                if (p.getAttribute('data-panel') === target) {
                    p.style.opacity = '1';
                    p.style.zIndex = '1';
                    var vid = p.querySelector('video');
                    if (vid) { vid.currentTime = 0; vid.play(); }
                } else {
                    p.style.opacity = '0';
                    p.style.zIndex = '0';
                }
            });
        });
    });
})();
</script>
@endpush

@endsection