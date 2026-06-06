@php
    $sbrOgRaw   = __('sbr.seo.og_image');
    $defaultOgRaw = \App\Models\Setting::get('default_og_image');
    $sbrOgImage = ($sbrOgRaw && $sbrOgRaw !== 'sbr.seo.og_image')
        ? (str_starts_with($sbrOgRaw, 'http') ? $sbrOgRaw : asset($sbrOgRaw))
        : ($defaultOgRaw
            ? (str_starts_with($defaultOgRaw, 'http') ? $defaultOgRaw : asset($defaultOgRaw))
            : asset('images/casambi/withoutlimitations.jpeg'));
@endphp
@extends('front.layouts.frontend', [
    'seo' => [
        'title'          => __('sbr.seo.title'),
        'description'    => __('sbr.seo.description'),
        'keywords'       => __('sbr.seo.keywords'),
        'og_title'       => __('sbr.seo.og_title'),
        'og_description' => __('sbr.seo.og_description'),
        'image'          => $sbrOgImage,
        'canonical'      => url(request()->path()),
        'robots'         => 'index, follow',
        'type'           => 'website',
        'hreflangs'      => [
            'en' => switch_locale_url('en'),
            'vi' => switch_locale_url('vi'),
        ]
    ]
])

{{-- Solutions by Role Page Schema: Organization + WebPage + Service + FAQPage + BreadcrumbList --}}
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
    if (!empty($socialLinks)) {
        $organizationSchema['sameAs'] = $socialLinks;
    }

    $webPageSchema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'WebPage',
        'url'         => $pageUrl,
        'name'        => __('sbr.seo.title'),
        'description' => __('sbr.seo.description'),
        'inLanguage'  => $locale === 'vi' ? 'vi-VN' : 'en-US',
        'isPartOf'    => ['@type' => 'WebSite', 'url' => $appUrl],
        'publisher'   => ['@type' => 'Organization', 'name' => $appName, 'url' => $appUrl],
    ];

    $serviceSchema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Service',
        'name'        => $locale === 'vi'
            ? 'Chương Trình Đối Tác Casambi'
            : 'Casambi Partner Program',
        'description' => __('sbr.seo.description'),
        'url'         => $pageUrl,
        'provider'    => ['@type' => 'Organization', 'name' => $appName, 'url' => $appUrl],
        'areaServed'  => 'VN',
        'serviceType' => 'Smart Lighting Solutions Partnership',
    ];

    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
@endphp
<script type="application/ld+json">{!! json_encode($organizationSchema, $jsonFlags) !!}</script>
<script type="application/ld+json">{!! json_encode($webPageSchema, $jsonFlags) !!}</script>
<script type="application/ld+json">{!! json_encode($serviceSchema, $jsonFlags) !!}</script>
@php
    $sbrFaqs = $locale === 'vi' ? [
        ['q' => 'Chương trình đối tác Casambi là gì?',
         'a' => 'Chương trình đối tác Casambi dành cho các nhà phân phối, nhà thầu và đơn vị dịch vụ chiếu sáng muốn tích hợp và triển khai giải pháp điều khiển chiếu sáng thông minh Casambi. Đối tác được hưởng đào tạo miễn phí, hỗ trợ kỹ thuật và tài nguyên tiếp thị.'],
        ['q' => 'Làm thế nào để trở thành đối tác Casambi?',
         'a' => 'Bạn có thể đăng ký trở thành đối tác Casambi bằng cách liên hệ qua trang web của Casambi Việt Nam. Sau khi đăng ký, đối tác sẽ được cấp quyền truy cập vào cổng thông tin đối tác, tài liệu kỹ thuật và chương trình đào tạo.'],
        ['q' => 'Đối tác Casambi nhận được những hỗ trợ gì?',
         'a' => 'Đối tác Casambi được cung cấp đào tạo miễn phí (trực tuyến và trực tiếp), hỗ trợ kỹ thuật đẳng cấp, tài nguyên tiếp thị và bán hàng, cũng như hỗ trợ kỹ thuật chuyên sâu cho các dự án phức tạp.'],
        ['q' => 'Casambi hỗ trợ các giao thức điều khiển nào?',
         'a' => 'Casambi hỗ trợ điều khiển không dây Bluetooth 5.3, DALI, DALI-2, D4i, 0/1–10V, PWM và cắt pha — cho phép tích hợp linh hoạt với hầu hết các thiết bị chiếu sáng hiện có.'],
        ['q' => 'Casambi Ready là gì?',
         'a' => 'Casambi Ready là danh mục các thiết bị chiếu sáng của bên thứ ba được chứng nhận tương thích hoàn toàn với hệ sinh thái Casambi, đảm bảo khả năng kết nối và hoạt động liền mạch.'],
    ] : [
        ['q' => 'What is the Casambi Partner Program?',
         'a' => 'The Casambi Partner Program is for lighting distributors, contractors, and service providers who want to integrate and deploy Casambi smart lighting control solutions. Partners receive free training, technical support, and marketing resources.'],
        ['q' => 'How do I become a Casambi partner?',
         'a' => 'You can register to become a Casambi partner by contacting Casambi Vietnam through our website. Upon registration, partners gain access to the partner portal, technical documentation, and training programs.'],
        ['q' => 'What support do Casambi partners receive?',
         'a' => 'Casambi partners receive free training (online and in-person), world-class technical support, sales and marketing materials, and advanced engineering assistance for complex projects.'],
        ['q' => 'What control protocols does Casambi support?',
         'a' => 'Casambi supports Bluetooth 5.3 wireless control, DALI, DALI-2, D4i, 0/1–10V, PWM, and phase-cut — enabling flexible integration with most existing lighting devices.'],
        ['q' => 'What is Casambi Ready?',
         'a' => 'Casambi Ready is a catalog of third-party lighting devices certified as fully compatible with the Casambi ecosystem, ensuring seamless connectivity and interoperability.'],
    ];

    $faqSchema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => array_map(fn($faq) => [
            '@type'          => 'Question',
            'name'           => $faq['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a']],
        ], $sbrFaqs),
    ];
@endphp
<script type="application/ld+json">{!! json_encode($faqSchema, $jsonFlags) !!}</script>
@php
    $breadcrumbSchema = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => [
            [
                '@type'    => 'ListItem',
                'position' => 1,
                'name'     => $locale === 'vi' ? 'Trang chủ' : 'Home',
                'item'     => $appUrl,
            ],
            [
                '@type'    => 'ListItem',
                'position' => 2,
                'name'     => $locale === 'vi' ? 'Giải Pháp Cho Đối Tác' : 'Solutions by Role',
                'item'     => $pageUrl,
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($breadcrumbSchema, $jsonFlags) !!}</script>
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
                <path d="M11 6L6 1L1 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
    </div>

    {{-- ═══ 1. HERO ══════════════════════════════════════════════════════════════ --}}
    @php $sbrHeroBg = asset('images/casambi/withoutlimitations.jpeg'); @endphp
    <section class="sbr-hero" style="background-image: url('{{ $sbrHeroBg }}')">

        <div class="container container-1230">

            <div class="sbr-hero__content">
                <p class="sbr-eyebrow">{{ __('sbr.hero.eyebrow') }}</p>
                <h1 class="sbr-hero__title">
                    {{ __('sbr.hero.title_pre') }}<br>
                    <span class="sbr-hero__title-accent">{{ __('sbr.hero.title_main') }}</span>
                </h1>
                <p class="sbr-hero__desc">{{ __('sbr.hero.desc') }}</p>
                <div class="sbr-hero__actions">
                    <a href="#sbr-contact" class="sbr-btn-primary">{{ __('sbr.hero.cta') }}</a>
                </div>
            </div>



        </div>
    </section>

    {{-- ═══ 2. FEATURES STRIP ═══════════════════════════════════════════════════ --}}
    <section class="sbr-features">
        <div class="container container-1230">
            <div class="sbr-features__grid sbr-features__grid--3">

                <div class="sbr-feat">
                    <div class="sbr-feat__icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    </div>
                    <h3 class="sbr-feat__title">{{ __('sbr.features.f1_title') }}</h3>
                    <p class="sbr-feat__desc">{{ __('sbr.features.f1_desc') }}</p>
                </div>


                <div class="sbr-feat">
                    <div class="sbr-feat__icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                    </div>
                    <h3 class="sbr-feat__title">{{ __('sbr.features.f3_title') }}</h3>
                    <p class="sbr-feat__desc">{{ __('sbr.features.f3_desc') }}</p>
                </div>

                <div class="sbr-feat">
                    <div class="sbr-feat__icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                    </div>
                    <h3 class="sbr-feat__title">{{ __('sbr.features.f4_title') }}</h3>
                    <p class="sbr-feat__desc">{{ __('sbr.features.f4_desc') }}</p>
                </div>

            </div>
        </div>
    </section>

    {{-- ═══ 3. BENEFITS ══════════════════════════════════════════════════════════ --}}
    <section class="sbr-benefits">
        <div class="container container-1230">

            <div class="sbr-benefits__head">
                <span class="sbr-eyebrow sbr-eyebrow--center">{{ __('sbr.benefits.eyebrow') }}</span>
                <h2 class="sbr-benefits__title">{{ __('sbr.benefits.title') }}</h2>
            </div>

            {{-- Block 1: img-left text-right --}}
            <div class="sbr-benefit">
                <div class="sbr-benefit__img sbr-benefit__img--warm">
                    <img src="{{ asset('images/casambi/outdoor.jpeg') }}"
                         alt="{{ __('sbr.benefits.b1_title') }}"
                         loading="lazy">
                </div>
                <div class="sbr-benefit__text">
                    <div class="sbr-benefit__icon-wrap">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-orange)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    </div>
                    <h3 class="sbr-benefit__heading">{{ __('sbr.benefits.b1_title') }}</h3>
                    <p class="sbr-benefit__body">{{ __('sbr.benefits.b1_body') }}</p>
                </div>
            </div>

            {{-- Block 2: text-left img-right --}}
            <div class="sbr-benefit sbr-benefit--rev">
                <div class="sbr-benefit__text">
                    <div class="sbr-benefit__icon-wrap">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-orange)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    </div>
                    <h3 class="sbr-benefit__heading">{{ __('sbr.benefits.b2_title') }}</h3>
                    <p class="sbr-benefit__body">{{ __('sbr.benefits.b2_body') }}</p>
                </div>
                <div class="sbr-benefit__img">
                    <img src="{{ asset('images/casambi/bbc.jpg') }}"
                         alt="{{ __('sbr.benefits.b2_title') }}"
                         loading="lazy">
                </div>
            </div>

            {{-- Block 3: img-left text-right --}}
            <div class="sbr-benefit">
                <div class="sbr-benefit__img sbr-benefit__img--warm">
                    <img src="{{ asset('images/casambi/berkeley-communications-WEDDt-u3q3o-unsplash.jpg') }}"
                         alt="{{ __('sbr.benefits.b3_title') }}"
                         loading="lazy">
                </div>
                <div class="sbr-benefit__text">
                    <div class="sbr-benefit__icon-wrap">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-orange)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <h3 class="sbr-benefit__heading">{{ __('sbr.benefits.b3_title') }}</h3>
                    <p class="sbr-benefit__body">{{ __('sbr.benefits.b3_body') }}</p>
                </div>
            </div>

        </div>
    </section>

    {{-- ═══ 4. WHY CHOOSE ════════════════════════════════════════════════════════ --}}
    <section class="sbr-why" id="sbr-interop">
        <div class="container container-1230">

            <div class="sbr-why__head">
                <span class="sbr-eyebrow">{{ __('sbr.why.eyebrow') }}</span>
                <h2 class="sbr-why__title">{{ __('sbr.why.title') }}</h2>
            </div>

            @php
                $chevron = '<span class="sbr-why__item-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>';
            @endphp

            <div class="sbr-why__groups">

                {{-- Group 1 — light card --}}
                <div class="sbr-why__group">
                    <div class="sbr-why__group-header">
                        <div class="sbr-why__group-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-orange)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/></svg>
                        </div>
                        <h3 class="sbr-why__group-title">{{ __('sbr.why.g1_title') }}</h3>
                    </div>
                    <div class="sbr-why__items">
                        <div class="sbr-why__item">{!! $chevron !!}<div><h4 class="sbr-why__item-title">{{ __('sbr.why.g1_i1_title') }}</h4><p class="sbr-why__item-desc">{{ __('sbr.why.g1_i1_desc') }}</p></div></div>
                        <div class="sbr-why__item">{!! $chevron !!}<div><h4 class="sbr-why__item-title">{{ __('sbr.why.g1_i2_title') }}</h4><p class="sbr-why__item-desc">{{ __('sbr.why.g1_i2_desc') }}</p></div></div>
                        <div class="sbr-why__item">{!! $chevron !!}<div><h4 class="sbr-why__item-title">{{ __('sbr.why.g1_i3_title') }}</h4><p class="sbr-why__item-desc">{{ __('sbr.why.g1_i3_desc') }}</p></div></div>
                    </div>
                </div>

                {{-- Group 2 --}}
                <div class="sbr-why__group">
                    <div class="sbr-why__group-header">
                        <div class="sbr-why__group-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-orange)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/></svg>
                        </div>
                        <h3 class="sbr-why__group-title">{{ __('sbr.why.g2_title') }}</h3>
                    </div>
                    <div class="sbr-why__items">
                        <div class="sbr-why__item">{!! $chevron !!}<div><h4 class="sbr-why__item-title">{{ __('sbr.why.g2_i1_title') }}</h4><p class="sbr-why__item-desc">{{ __('sbr.why.g2_i1_desc') }}</p></div></div>
                        <div class="sbr-why__item">{!! $chevron !!}<div><h4 class="sbr-why__item-title">{{ __('sbr.why.g2_i2_title') }}</h4><p class="sbr-why__item-desc">{{ __('sbr.why.g2_i2_desc') }}</p></div></div>
                        <div class="sbr-why__item">{!! $chevron !!}<div><h4 class="sbr-why__item-title">{{ __('sbr.why.g2_i3_title') }}</h4><p class="sbr-why__item-desc">{{ __('sbr.why.g2_i3_desc') }}</p></div></div>
                    </div>
                </div>

                {{-- Group 3 — light card --}}
                <div class="sbr-why__group">
                    <div class="sbr-why__group-header">
                        <div class="sbr-why__group-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-orange)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                        </div>
                        <h3 class="sbr-why__group-title">{{ __('sbr.why.g3_title') }}</h3>
                    </div>
                    <div class="sbr-why__items">
                        <div class="sbr-why__item">{!! $chevron !!}<div><h4 class="sbr-why__item-title">{{ __('sbr.why.g3_i1_title') }}</h4><p class="sbr-why__item-desc">{{ __('sbr.why.g3_i1_desc') }}</p></div></div>
                        <div class="sbr-why__item">{!! $chevron !!}<div><h4 class="sbr-why__item-title">{{ __('sbr.why.g3_i2_title') }}</h4><p class="sbr-why__item-desc">{{ __('sbr.why.g3_i2_desc') }}</p></div></div>
                        <div class="sbr-why__item">{!! $chevron !!}<div><h4 class="sbr-why__item-title">{{ __('sbr.why.g3_i3_title') }}</h4><p class="sbr-why__item-desc">{{ __('sbr.why.g3_i3_desc') }}</p></div></div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- ═══ 5. PARTNER CTA ═══════════════════════════════════════════════════════ --}}
    <section class="sbr-analytics" id="sbr-contact">
        <div class="container container-1230">
            <div class="sbr-analytics__inner">
                <div class="sbr-analytics__text">
                    <span class="sbr-eyebrow sbr-eyebrow--orange">{{ __('sbr.cta.eyebrow') }}</span>
                    <h2 class="sbr-analytics__title">{{ __('sbr.cta.title') }}</h2>
                    <p class="sbr-analytics__desc">{{ __('sbr.cta.desc') }}</p>
                    <div class="sbr-analytics__btns">
                        <a href="#" onclick="openContactPopup();return false;" class="sbr-btn-primary">{{ __('sbr.cta.btn1') }}</a>
                        <a href="{{ route(app()->getLocale().'.wireless-casambi') }}" class="sbr-btn-ghost-white">
                            {{ __('sbr.cta.btn2') }}
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </a>
                    </div>
                </div>
                <div class="sbr-analytics__deco" aria-hidden="true">
                    <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg" width="200" height="200">
                        <circle cx="100" cy="100" r="80" stroke="rgba(255,86,35,0.12)" stroke-width="1"/>
                        <circle cx="100" cy="100" r="55" stroke="rgba(255,86,35,0.10)" stroke-width="1"/>
                        <circle cx="100" cy="100" r="30" stroke="rgba(255,86,35,0.15)" stroke-width="1"/>
                        <circle cx="100" cy="100" r="6" fill="rgba(255,86,35,0.4)"/>
                        <line x1="100" y1="20" x2="100" y2="180" stroke="rgba(255,86,35,0.06)" stroke-width="1"/>
                        <line x1="20" y1="100" x2="180" y2="100" stroke="rgba(255,86,35,0.06)" stroke-width="1"/>
                    </svg>
                </div>
            </div>
        </div>
    </section>

@push('scripts')
<script>
(function () {
    var els = document.querySelectorAll('.sbr-benefit, .sbr-feat, .sbr-why__group');
    if (!els.length) return;
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (e.isIntersecting) {
                e.target.classList.add('sbr-visible');
                io.unobserve(e.target);
            }
        });
    }, { threshold: 0.1 });
    els.forEach(function (el) { io.observe(el); });
})();
</script>
@endpush

@endsection
