@php
    $wirelessOgRaw   = __('wireless.seo.og_image');
    $defaultOgRaw    = \App\Models\Setting::get('default_og_image');
    $wirelessOgImage = ($wirelessOgRaw && $wirelessOgRaw !== 'wireless.seo.og_image')
        ? (str_starts_with($wirelessOgRaw, 'http') ? $wirelessOgRaw : asset($wirelessOgRaw))
        : ($defaultOgRaw
            ? (str_starts_with($defaultOgRaw, 'http') ? $defaultOgRaw : asset($defaultOgRaw))
            : asset('images/casambi/hero-wireless.jpg'));
@endphp
@extends('front.layouts.frontend', [
    'seo' => [
        'title'          => __('wireless.seo.title'),
        'description'    => __('wireless.seo.description'),
        'keywords'       => __('wireless.seo.keywords'),
        'og_title'       => __('wireless.seo.og_title'),
        'og_description' => __('wireless.seo.og_description'),
        'image'          => $wirelessOgImage,
        'canonical'      => url(request()->path()),
        'robots'         => 'index, follow',
        'type'           => 'website',
        'hreflangs'      => [
            'en' => switch_locale_url('en'),
            'vi' => switch_locale_url('vi'),
        ]
    ]
])

{{-- Wireless Page Schema: Organization + WebPage + Service --}}
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
        'name'        => __('wireless.seo.title'),
        'description' => __('wireless.seo.description'),
        'inLanguage'  => $locale === 'vi' ? 'vi-VN' : 'en-US',
        'isPartOf'    => ['@type' => 'WebSite', 'url' => $appUrl],
        'publisher'   => ['@type' => 'Organization', 'name' => $appName, 'url' => $appUrl],
    ];

    $serviceSchema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Service',
        'name'        => $locale === 'vi' ? 'Điều Khiển Chiếu Sáng Không Dây Casambi' : 'Casambi Wireless Lighting Control',
        'description' => __('wireless.seo.description'),
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
    $wirelessFaqs = $locale === 'vi' ? [
        ['q' => 'Casambi Wireless là gì?',
         'a' => 'Casambi Wireless là hệ thống điều khiển ánh sáng thông minh không dây sử dụng công nghệ Bluetooth Mesh, cho phép điều khiển toàn bộ hệ thống chiếu sáng qua smartphone hoặc tablet mà không cần hub trung tâm.'],
        ['q' => 'Casambi có cần hub hoặc gateway không?',
         'a' => 'Không. Casambi hoạt động theo mô hình mesh peer-to-peer, các thiết bị tự kết nối với nhau. Chỉ cần gateway khi muốn điều khiển từ xa qua internet hoặc tích hợp với hệ thống BMS.'],
        ['q' => 'Phạm vi kết nối Bluetooth của Casambi là bao nhiêu?',
         'a' => 'Trong điều kiện thông thường, mỗi thiết bị Casambi có phạm vi kết nối khoảng 10–30 mét. Nhờ công nghệ Mesh, tín hiệu được chuyển tiếp qua các node, phủ sóng toàn bộ tòa nhà.'],
        ['q' => 'Casambi hỗ trợ dimming và thay đổi màu sắc không?',
         'a' => 'Có. Casambi hỗ trợ điều chỉnh độ sáng (dimming), thay đổi nhiệt độ màu (CCT) và màu sắc RGB/RGBW tùy theo loại đèn được kết nối.'],
        ['q' => 'Casambi Wireless phù hợp với công trình nào?',
         'a' => 'Casambi phù hợp với văn phòng, khách sạn, trung tâm thương mại, nhà ở cao cấp, showroom và các công trình công nghiệp cần hệ thống chiếu sáng thông minh linh hoạt.'],
    ] : [
        ['q' => 'What is Casambi Wireless?',
         'a' => 'Casambi Wireless is a smart wireless lighting control system using Bluetooth Mesh technology, allowing full lighting system control via smartphone or tablet without a central hub.'],
        ['q' => 'Does Casambi require a hub or gateway?',
         'a' => 'No. Casambi operates on a peer-to-peer mesh model where devices connect directly to each other. A gateway is only needed for remote access over the internet or BMS integration.'],
        ['q' => 'What is the Bluetooth range of Casambi?',
         'a' => 'Each Casambi device typically has a range of 10–30 meters. Thanks to Mesh technology, signals are relayed through nodes to cover an entire building.'],
        ['q' => 'Does Casambi support dimming and color changing?',
         'a' => 'Yes. Casambi supports dimming, color temperature adjustment (CCT), and RGB/RGBW color control depending on the connected luminaire type.'],
        ['q' => 'What types of projects is Casambi Wireless suitable for?',
         'a' => 'Casambi is suitable for offices, hotels, shopping centers, high-end residences, showrooms, and industrial facilities requiring flexible smart lighting systems.'],
    ];

    $faqSchema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => array_map(fn($faq) => [
            '@type'          => 'Question',
            'name'           => $faq['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a']],
        ], $wirelessFaqs),
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
                'name'     => $locale === 'vi' ? 'Wireless Casambi' : 'Casambi Wireless',
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
                <path d="M11 6L6 1L1 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
    </div>

    <!-- ═══ 1. HERO — Full bleed image · content bottom ════════════════════════ -->

    <section class="wlc2-hero">
        <div class="wlc2-hero__bg"></div>

        <!-- top bar -->
        <div class="wlc2-hero__topbar">
            <div class="container container-1230">
                <div class="wlc2-hero__topbar-inner">
                    <span class="wlc2-hero__eyebrow"><span></span>{{ __('wireless.hero.eyebrow') }}</span>
                    <div class="wlc2-hero__topstats">
                        <div class="wlc2-hero__topstat">
                            <div class="wlc2-hero__topstat-num">250K+</div>
                            <div class="wlc2-hero__topstat-lbl">{{ __('wireless.hero.stat1_lbl') }}</div>
                        </div>
                        <div class="wlc2-hero__topstat-divider"></div>
                        <div class="wlc2-hero__topstat">
                            <div class="wlc2-hero__topstat-num">6M+</div>
                            <div class="wlc2-hero__topstat-lbl">{{ __('wireless.hero.stat2_lbl') }}</div>
                        </div>
                        <div class="wlc2-hero__topstat-divider"></div>
                        <div class="wlc2-hero__topstat">
                            <div class="wlc2-hero__topstat-num">100s</div>
                            <div class="wlc2-hero__topstat-lbl">{{ __('wireless.hero.stat3_lbl') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- bottom content -->
        <div class="wlc2-hero__content">
            <div class="container container-1230">
                <div class="wlc2-hero__chips">
                    @foreach(explode('|', __('wireless.hero.chips')) as $chip)
                        <span class="wlc2-hero__chip">{{ $chip }}</span>
                    @endforeach
                </div>
                <div class="wlc2-hero__content-inner">
                    <h1 class="wlc2-hero__title">{!! __('wireless.hero.title') !!}</h1>
                    <div class="wlc2-hero__right-col">
                        <p class="wlc2-hero__desc">{{ __('wireless.hero.desc') }}</p>
                        <a class="wlc2-hero__cta">
                            {{ __('wireless.hero.cta') }}
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>



    <!-- ═══ 3. 4 PILLARS — Bento Grid layout ══════════════════════════════════ -->
    <section class="wlc2-bento">
        <div class="container container-1230">
            <div class="wlc2-bento__head">
                <h2>{{ __('wireless.bento.heading') }}</h2>
                <p>{{ __('wireless.bento.subheading') }}</p>
            </div>
            <div class="wlc2-bento-grid">
                <!-- Large dark card -->
                <div class="wlc2-bento-cell wlc2-bento-cell--lg">
                    <span class="wlc2-bento-cell__num">01</span>
                    <div class="wlc2-bento-cell__icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fe5723" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                    </div>
                    <span class="wlc2-bento-cell__tag">{{ __('wireless.bento.c1_tag') }}</span>
                    <h3>{{ __('wireless.bento.c1_title') }}</h3>
                    <p>{{ __('wireless.bento.c1_desc') }}</p>
                </div>
                <!-- Accent tall card -->
                <div class="wlc2-bento-cell wlc2-bento-cell--accent">
                    <span class="wlc2-bento-cell__num">02</span>
                    <div class="wlc2-bento-cell__icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    </div>
                    <span class="wlc2-bento-cell__tag">{{ __('wireless.bento.c2_tag') }}</span>
                    <h3>{{ __('wireless.bento.c2_title') }}</h3>
                    <p>{{ __('wireless.bento.c2_desc') }}</p>
                </div>
                <!-- Small light card -->
                <div class="wlc2-bento-cell wlc2-bento-cell--sm">
                    <span class="wlc2-bento-cell__num wlc2-bento-cell__num--dark">03</span>
                    <div class="wlc2-bento-cell__icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fe5723" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    </div>
                    <span class="wlc2-bento-cell__tag">{{ __('wireless.bento.c3_tag') }}</span>
                    <h3>{{ __('wireless.bento.c3_title') }}</h3>
                    <p>{{ __('wireless.bento.c3_desc') }}</p>
                </div>
                <!-- Blue card -->
                <div class="wlc2-bento-cell wlc2-bento-cell--sm2">
                    <span class="wlc2-bento-cell__num wlc2-bento-cell__num--dark">04</span>
                    <div class="wlc2-bento-cell__icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#3188ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                    </div>
                    <span class="wlc2-bento-cell__tag">{{ __('wireless.bento.c4_tag') }}</span>
                    <h3>{{ __('wireless.bento.c4_title') }}</h3>
                    <p>{{ __('wireless.bento.c4_desc') }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══ 4. SOLUTIONS — Tabbed by Role ════════════════════════════════════ -->
    <section class="wlc2-roles">
        <div class="container container-1230">
            <div class="wlc2-roles__head">
                <h2>{{ __('wireless.roles.heading') }}</h2>
                <p>{{ __('wireless.roles.subheading') }}</p>
            </div>
            <div class="wlc2-role-tabs">
                <button class="wlc2-role-tab active" onclick="wlcTab(this,'designers')">{{ __('wireless.roles.tab1') }}</button>
                <button class="wlc2-role-tab" onclick="wlcTab(this,'engineers')">{{ __('wireless.roles.tab2') }}</button>
                <button class="wlc2-role-tab" onclick="wlcTab(this,'managers')">{{ __('wireless.roles.tab3') }}</button>
            </div>
            <!-- Tab: Designers -->
            <div class="wlc2-role-panel active" id="wlc-tab-designers">
                <div class="wlc2-role-panel__img">
                    <!-- IMAGE: Lighting designer working on a project -->
                    <img src="{{ asset('images/casambi/Lighting-Designers.png') }}" alt="Lighting Designers" onerror="this.style.display='none'">
                    <span class="wlc2-role-panel__img-badge">{{ __('wireless.roles.r1_badge') }}</span>
                </div>
                <div class="wlc2-role-panel__text">
                    <h3>{{ __('wireless.roles.r1_title') }}</h3>
                    <p>{{ __('wireless.roles.r1_desc') }}</p>
                    <ul class="wlc2-role-list">
                        <li>
                            <div class="wlc2-role-list__dot"></div>
                            <div><strong>{{ __('wireless.roles.r1_li1_strong') }}</strong><span>{{ __('wireless.roles.r1_li1_span') }}</span></div>
                        </li>
                        <li>
                            <div class="wlc2-role-list__dot"></div>
                            <div><strong>{{ __('wireless.roles.r1_li2_strong') }}</strong><span>{{ __('wireless.roles.r1_li2_span') }}</span></div>
                        </li>
                        <li>
                            <div class="wlc2-role-list__dot"></div>
                            <div><strong>{{ __('wireless.roles.r1_li3_strong') }}</strong><span>{{ __('wireless.roles.r1_li3_span') }}</span></div>
                        </li>
                    </ul>
                    <a class="wlc2-role-cta">{{ __('wireless.roles.r1_cta') }} <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
                </div>
            </div>
            <!-- Tab: Engineers -->
            <div class="wlc2-role-panel" id="wlc-tab-engineers">
                <div class="wlc2-role-panel__img">
                    <!-- IMAGE: Electrical engineer / installer on site -->
                    <img src="{{ asset('images/casambi/Fastest-Installation.jpg') }}" alt="Electrical Engineers" onerror="this.style.display='none'">
                    <span class="wlc2-role-panel__img-badge">{{ __('wireless.roles.r2_badge') }}</span>
                </div>
                <div class="wlc2-role-panel__text">
                    <h3>{{ __('wireless.roles.r2_title') }}</h3>
                    <p>{{ __('wireless.roles.r2_desc') }}</p>
                    <ul class="wlc2-role-list">
                        <li>
                            <div class="wlc2-role-list__dot"></div>
                            <div><strong>{{ __('wireless.roles.r2_li1_strong') }}</strong><span>{{ __('wireless.roles.r2_li1_span') }}</span></div>
                        </li>
                        <li>
                            <div class="wlc2-role-list__dot"></div>
                            <div><strong>{{ __('wireless.roles.r2_li2_strong') }}</strong><span>{{ __('wireless.roles.r2_li2_span') }}</span></div>
                        </li>
                        <li>
                            <div class="wlc2-role-list__dot"></div>
                            <div><strong>{{ __('wireless.roles.r2_li3_strong') }}</strong><span>{{ __('wireless.roles.r2_li3_span') }}</span></div>
                        </li>
                    </ul>
                    <a class="wlc2-role-cta">{{ __('wireless.roles.r2_cta') }} <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
                </div>
            </div>
            <!-- Tab: Managers -->
            <div class="wlc2-role-panel" id="wlc-tab-managers">
                <div class="wlc2-role-panel__img">
                    <!-- IMAGE: Facility manager using Casambi app on tablet -->
                    <img src="{{ asset('images/casambi/yourfingertips.jpg') }}" alt="Facility Managers" onerror="this.style.display='none'">
                    <span class="wlc2-role-panel__img-badge">{{ __('wireless.roles.r3_badge') }}</span>
                </div>
                <div class="wlc2-role-panel__text">
                    <h3>{{ __('wireless.roles.r3_title') }}</h3>
                    <p>{{ __('wireless.roles.r3_desc') }}</p>
                    <ul class="wlc2-role-list">
                        <li>
                            <div class="wlc2-role-list__dot"></div>
                            <div><strong>{{ __('wireless.roles.r3_li1_strong') }}</strong><span>{{ __('wireless.roles.r3_li1_span') }}</span></div>
                        </li>
                        <li>
                            <div class="wlc2-role-list__dot"></div>
                            <div><strong>{{ __('wireless.roles.r3_li2_strong') }}</strong><span>{{ __('wireless.roles.r3_li2_span') }}</span></div>
                        </li>
                        <li>
                            <div class="wlc2-role-list__dot"></div>
                            <div><strong>{{ __('wireless.roles.r3_li3_strong') }}</strong><span>{{ __('wireless.roles.r3_li3_span') }}</span></div>
                        </li>
                    </ul>
                    <a class="wlc2-role-cta">{{ __('wireless.roles.r3_cta') }} <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
                </div>
            </div>
        </div>
    </section>
    <script>
        function wlcTab(btn,id){
            document.querySelectorAll('.wlc2-role-tab').forEach(function(t){t.classList.remove('active');});
            document.querySelectorAll('.wlc2-role-panel').forEach(function(p){p.classList.remove('active');});
            btn.classList.add('active');
            document.getElementById('wlc-tab-'+id).classList.add('active');
        }
    </script>


    <!-- ═══ 7. APPLICATIONS — Mosaic grid ══════════════════════════════════ -->
    <section class="wlc2-mosaic">
        <div class="container container-1230">
            <div class="wlc2-mosaic__head">
                <h2>{{ __('wireless.mosaic.heading') }}</h2>
                <p>{{ __('wireless.mosaic.subheading') }}</p>
            </div>
            <div class="wlc2-mosaic-grid">
                <div class="wlc2-tile">
                    <img class="wlc2-tile__img" src="{{ asset('images/casambi/office.jpeg') }}" alt="" onerror="this.style.display='none'">
                    <div class="wlc2-tile__ov"></div>
                    <div class="wlc2-tile__label">
                        <p class="wlc2-tile__name">{{ __('wireless.mosaic.t1_name') }}</p>
                        <p class="wlc2-tile__sub">{{ __('wireless.mosaic.t1_sub') }}</p>
                    </div>
                </div>
                <div class="wlc2-tile">
                    <img class="wlc2-tile__img" src="{{ asset('images/casambi/retail.jpg') }}" alt="" onerror="this.style.display='none'">
                    <div class="wlc2-tile__ov"></div>
                    <div class="wlc2-tile__label">
                        <p class="wlc2-tile__name">{{ __('wireless.mosaic.t2_name') }}</p>
                        <p class="wlc2-tile__sub">{{ __('wireless.mosaic.t2_sub') }}</p>
                    </div>
                </div>
                <div class="wlc2-tile">
                    <img class="wlc2-tile__img" src="{{ asset('images/casambi/industrial.jpg') }}" alt="" onerror="this.style.display='none'">
                    <div class="wlc2-tile__ov"></div>
                    <div class="wlc2-tile__label">
                        <p class="wlc2-tile__name">{{ __('wireless.mosaic.t3_name') }}</p>
                        <p class="wlc2-tile__sub">{{ __('wireless.mosaic.t3_sub') }}</p>
                    </div>
                </div>
                <div class="wlc2-tile">
                    <img class="wlc2-tile__img" src="{{ asset('images/casambi/outdoor.jpeg') }}" alt="" onerror="this.style.display='none'">
                    <div class="wlc2-tile__ov"></div>
                    <div class="wlc2-tile__label">
                        <p class="wlc2-tile__name">{{ __('wireless.mosaic.t4_name') }}</p>
                        <p class="wlc2-tile__sub">{{ __('wireless.mosaic.t4_sub') }}</p>
                    </div>
                </div>
                <div class="wlc2-tile">
                    <img class="wlc2-tile__img" src="{{ asset('images/casambi/hospitality.jpeg') }}" alt="" onerror="this.style.display='none'">
                    <div class="wlc2-tile__ov"></div>
                    <div class="wlc2-tile__label">
                        <p class="wlc2-tile__name">{{ __('wireless.mosaic.t5_name') }}</p>
                        <p class="wlc2-tile__sub">{{ __('wireless.mosaic.t5_sub') }}</p>
                    </div>
                </div>
                <div class="wlc2-tile">
                    <img class="wlc2-tile__img" src="{{ asset('images/casambi/museum.jpg') }}" alt="" onerror="this.style.display='none'">
                    <div class="wlc2-tile__ov"></div>
                    <div class="wlc2-tile__label">
                        <p class="wlc2-tile__name">{{ __('wireless.mosaic.t6_name') }}</p>
                        <p class="wlc2-tile__sub">{{ __('wireless.mosaic.t6_sub') }}</p>
                    </div>
                </div>
                <div class="wlc2-tile">
                    <img class="wlc2-tile__img" src="{{ asset('images/casambi/healcare.jpeg') }}" alt="" onerror="this.style.display='none'">
                    <div class="wlc2-tile__ov"></div>
                    <div class="wlc2-tile__label">
                        <p class="wlc2-tile__name">{{ __('wireless.mosaic.t7_name') }}</p>
                        <p class="wlc2-tile__sub">{{ __('wireless.mosaic.t7_sub') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══ 8. CASE STUDIES — Timeline horizontal ═══════════════════════════ -->
    <section class="wlc2-cases">
        <div class="container container-1230">
            <div class="wlc2-cases__head">
                <h2>{{ __('wireless.cases.heading') }}</h2>
                <p>{{ __('wireless.cases.subheading') }}</p>
            </div>
            <div class="wlc2-cases-timeline">
                <div class="wlc2-case-item">
                    <div class="wlc2-case-img">
                        <img src="{{ asset('images/casambi/bbc.jpg') }}" alt="BBC" onerror="this.style.display='none'">
                        <span class="wlc2-case-cat">{{ __('wireless.cases.c1_cat') }}</span>
                    </div>
                    <div class="wlc2-case-body">
                        <span class="wlc2-case-body__num">{{ __('wireless.cases.c1_num') }}</span>
                        <h3>{{ __('wireless.cases.c1_title') }}</h3>
                        <p>{{ __('wireless.cases.c1_desc') }}</p>
                        <div class="wlc2-case-stats">
                            <div><span class="wlc2-case-stat__val">{{ __('wireless.cases.c1_stat1_val') }}</span><span class="wlc2-case-stat__label">{{ __('wireless.cases.c1_stat1_lbl') }}</span></div>
                            <div><span class="wlc2-case-stat__val">{{ __('wireless.cases.c1_stat2_val') }}</span><span class="wlc2-case-stat__label">{{ __('wireless.cases.c1_stat2_lbl') }}</span></div>
                        </div>
                    </div>
                </div>
                <div class="wlc2-case-item">
                    <div class="wlc2-case-img">
                        <img src="{{ asset('images/casambi/Swatch Melbourne.jpg') }}" alt="Swatch Melbourne" onerror="this.style.display='none'">
                        <span class="wlc2-case-cat">{{ __('wireless.cases.c2_cat') }}</span>
                    </div>
                    <div class="wlc2-case-body">
                        <span class="wlc2-case-body__num">{{ __('wireless.cases.c2_num') }}</span>
                        <h3>{{ __('wireless.cases.c2_title') }}</h3>
                        <p>{{ __('wireless.cases.c2_desc') }}</p>
                        <div class="wlc2-case-stats">
                            <div><span class="wlc2-case-stat__val">{{ __('wireless.cases.c2_stat1_val') }}</span><span class="wlc2-case-stat__label">{{ __('wireless.cases.c2_stat1_lbl') }}</span></div>
                            <div><span class="wlc2-case-stat__val">{{ __('wireless.cases.c2_stat2_val') }}</span><span class="wlc2-case-stat__label">{{ __('wireless.cases.c2_stat2_lbl') }}</span></div>
                        </div>
                    </div>
                </div>
                <div class="wlc2-case-item">
                    <div class="wlc2-case-img">
                        <img src="{{ asset('images/casambi/Helsinki Airport.jpeg') }}" alt="Helsinki Airport" onerror="this.style.display='none'">
                        <span class="wlc2-case-cat">{{ __('wireless.cases.c3_cat') }}</span>
                    </div>
                    <div class="wlc2-case-body">
                        <span class="wlc2-case-body__num">{{ __('wireless.cases.c3_num') }}</span>
                        <h3>{{ __('wireless.cases.c3_title') }}</h3>
                        <p>{{ __('wireless.cases.c3_desc') }}</p>
                        <div class="wlc2-case-stats">
                            <div><span class="wlc2-case-stat__val">{{ __('wireless.cases.c3_stat1_val') }}</span><span class="wlc2-case-stat__label">{{ __('wireless.cases.c3_stat1_lbl') }}</span></div>
                            <div><span class="wlc2-case-stat__val">{{ __('wireless.cases.c3_stat2_val') }}</span><span class="wlc2-case-stat__label">{{ __('wireless.cases.c3_stat2_lbl') }}</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- faq area start -->
    <div class="app-faq-area p-relative pb-120">
        <div class="app-faq-shape" data-speed=".8">
            <img src="{{ asset('assets/img/home-10/faq/faq-shape-1.png') }}" alt="">
        </div>
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
                                <div class="accordion-items">
                                    <h2 class="accordion-header">
                                        <button class="accordion-buttons" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                            {{ __('dali.faq.q1') }}
                                            <span class="accordion-icon"></span>
                                        </button>
                                    </h2>
                                    <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#accordionExample">
                                        <div class="accordion-body">
                                            <p>{{ __('dali.faq.a1') }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-items">
                                    <h2 class="accordion-header">
                                        <button class="accordion-buttons collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                            {{ __('dali.faq.q2') }}
                                            <span class="accordion-icon"></span>
                                        </button>
                                    </h2>
                                    <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
                                        <div class="accordion-body">
                                            <p>{{ __('dali.faq.a2') }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-items">
                                    <h2 class="accordion-header">
                                        <button class="accordion-buttons collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                            {{ __('dali.faq.q3') }}
                                            <span class="accordion-icon"></span>
                                        </button>
                                    </h2>
                                    <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
                                        <div class="accordion-body">
                                            <p>{{ __('dali.faq.a3') }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-items">
                                    <h2 class="accordion-header">
                                        <button class="accordion-buttons collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                            {{ __('dali.faq.q4') }}
                                            <span class="accordion-icon"></span>
                                        </button>
                                    </h2>
                                    <div id="collapseFour" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
                                        <div class="accordion-body">
                                            <p>{{ __('dali.faq.a4') }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-items">
                                    <h2 class="accordion-header">
                                        <button class="accordion-buttons collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                                            {{ __('dali.faq.q5') }}
                                            <span class="accordion-icon"></span>
                                        </button>
                                    </h2>
                                    <div id="collapseFive" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
                                        <div class="accordion-body">
                                            <p>{{ __('dali.faq.a5') }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-items">
                                    <h2 class="accordion-header">
                                        <button class="accordion-buttons collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSix" aria-expanded="false" aria-controls="collapseSix">
                                            {{ __('dali.faq.q6') }}
                                            <span class="accordion-icon"></span>
                                        </button>
                                    </h2>
                                    <div id="collapseSix" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
                                        <div class="accordion-body">
                                            <p>{{ __('dali.faq.a6') }}</p>
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
    <!-- faq area end -->

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
                                <!-- IMAGE: Casambi App screenshot or DALI controller photo -->
                                <div class="cta-slide-in cta-slide-left z-index-1">
                                    <img class="app-cta-thumb-1" src="{{ asset('images/casambi/casambiapp.webp') }}" alt="Casambi App">
                                </div>
                                <!-- IMAGE: Casambi Pro dashboard screenshot -->
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

@push('scripts')
    <script src="{{ asset('assets/js/atropos.js') }}"></script>
    <style>
    .cta-slide-in {
        opacity: 0;
        transition: opacity 0.8s ease, transform 0.8s ease;
    }
    .cta-slide-left  { transform: translateX(-60px); }
    .cta-slide-right { transform: translateX(60px); }
    .cta-slide-in.visible {
        opacity: 1;
        transform: translateX(0);
    }
    .cta-slide-right.visible { transition-delay: 0.2s; }
    </style>
    <script>
    (function () {
        var els = document.querySelectorAll('.cta-slide-in');
        if (!els.length) return;
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.2 });
        els.forEach(function (el) { observer.observe(el); });
    })();
    </script>
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