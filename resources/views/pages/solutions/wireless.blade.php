@php
    $wirelessOgRaw   = __('wireless.seo.og_image');
    $defaultOgRaw    = \App\Models\Setting::get('default_og_image');
    $wirelessOgImage = ($wirelessOgRaw && $wirelessOgRaw !== 'wireless.seo.og_image')
        ? (str_starts_with($wirelessOgRaw, 'http') ? $wirelessOgRaw : asset($wirelessOgRaw))
        : ($defaultOgRaw
            ? (str_starts_with($defaultOgRaw, 'http') ? $defaultOgRaw : asset($defaultOgRaw))
            : asset('images/casambi/hero-wireless.jpg'));
@endphp
@extends('layouts.frontend')

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

    $organizationSchema = ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $appName, 'url' => $appUrl, 'logo' => ['@type' => 'ImageObject', 'url' => $logoUrl]];
    if (!empty($socialLinks)) $organizationSchema['sameAs'] = $socialLinks;

    $webPageSchema = ['@context' => 'https://schema.org', '@type' => 'WebPage', 'url' => $pageUrl, 'name' => __('wireless.seo.title'), 'description' => __('wireless.seo.description'), 'inLanguage' => $locale === 'vi' ? 'vi-VN' : 'en-US', 'isPartOf' => ['@type' => 'WebSite', 'url' => $appUrl], 'publisher' => ['@type' => 'Organization', 'name' => $appName, 'url' => $appUrl]];

    $serviceSchema = ['@context' => 'https://schema.org', '@type' => 'Service', 'name' => $locale === 'vi' ? 'Điều Khiển Chiếu Sáng Không Dây Casambi' : 'Casambi Wireless Lighting Control', 'description' => __('wireless.seo.description'), 'url' => $pageUrl, 'provider' => ['@type' => 'Organization', 'name' => $appName, 'url' => $appUrl], 'areaServed' => 'VN', 'serviceType' => 'Smart Lighting Control'];

    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
@endphp
<script type="application/ld+json">{!! json_encode($organizationSchema, $jsonFlags) !!}</script>
<script type="application/ld+json">{!! json_encode($webPageSchema, $jsonFlags) !!}</script>
<script type="application/ld+json">{!! json_encode($serviceSchema, $jsonFlags) !!}</script>
@php
    $wirelessFaqs = $locale === 'vi' ? [
        ['q' => 'Casambi Wireless là gì?', 'a' => 'Casambi Wireless là hệ thống điều khiển ánh sáng thông minh không dây sử dụng công nghệ Bluetooth Mesh, cho phép điều khiển toàn bộ hệ thống chiếu sáng qua smartphone hoặc tablet mà không cần hub trung tâm.'],
        ['q' => 'Casambi có cần hub hoặc gateway không?', 'a' => 'Không. Casambi hoạt động theo mô hình mesh peer-to-peer, các thiết bị tự kết nối với nhau. Chỉ cần gateway khi muốn điều khiển từ xa qua internet hoặc tích hợp với hệ thống BMS.'],
        ['q' => 'Phạm vi kết nối Bluetooth của Casambi là bao nhiêu?', 'a' => 'Trong điều kiện thông thường, mỗi thiết bị Casambi có phạm vi kết nối khoảng 10–30 mét. Nhờ công nghệ Mesh, tín hiệu được chuyển tiếp qua các node, phủ sóng toàn bộ tòa nhà.'],
        ['q' => 'Casambi hỗ trợ dimming và thay đổi màu sắc không?', 'a' => 'Có. Casambi hỗ trợ điều chỉnh độ sáng (dimming), thay đổi nhiệt độ màu (CCT) và màu sắc RGB/RGBW tùy theo loại đèn được kết nối.'],
        ['q' => 'Casambi Wireless phù hợp với công trình nào?', 'a' => 'Casambi phù hợp với văn phòng, khách sạn, trung tâm thương mại, nhà ở cao cấp, showroom và các công trình công nghiệp cần hệ thống chiếu sáng thông minh linh hoạt.'],
    ] : [
        ['q' => 'What is Casambi Wireless?', 'a' => 'Casambi Wireless is a smart wireless lighting control system using Bluetooth Mesh technology, allowing full lighting system control via smartphone or tablet without a central hub.'],
        ['q' => 'Does Casambi require a hub or gateway?', 'a' => 'No. Casambi operates on a peer-to-peer mesh model where devices connect directly to each other. A gateway is only needed for remote access over the internet or BMS integration.'],
        ['q' => 'What is the Bluetooth range of Casambi?', 'a' => 'Each Casambi device typically has a range of 10–30 meters. Thanks to Mesh technology, signals are relayed through nodes to cover an entire building.'],
        ['q' => 'Does Casambi support dimming and color changing?', 'a' => 'Yes. Casambi supports dimming, color temperature adjustment (CCT), and RGB/RGBW color control depending on the connected luminaire type.'],
        ['q' => 'What types of projects is Casambi Wireless suitable for?', 'a' => 'Casambi is suitable for offices, hotels, shopping centers, high-end residences, showrooms, and industrial facilities requiring flexible smart lighting systems.'],
    ];

    $faqSchema = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($faq) => ['@type' => 'Question', 'name' => $faq['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a']]], $wirelessFaqs)];

    $breadcrumbSchema = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => $locale === 'vi' ? 'Trang chủ' : 'Home', 'item' => $appUrl],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $locale === 'vi' ? 'Wireless Casambi' : 'Casambi Wireless', 'item' => $pageUrl],
    ]];
@endphp
<script type="application/ld+json">{!! json_encode($faqSchema, $jsonFlags) !!}</script>
<script type="application/ld+json">{!! json_encode($breadcrumbSchema, $jsonFlags) !!}</script>
@endpush


@section('content')

    <div id="magic-cursor" class="cursor-white-bg"><div id="ball"></div></div>
    <div class="back-to-top-wrapper">
        <button id="back_to_top" type="button" class="back-to-top-btn">
            <svg width="12" height="7" viewBox="0 0 12 7" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11 6L6 1L1 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
        </button>
    </div>

    <section class="wlc2-hero">
        <div class="wlc2-hero__bg"></div>
        <div class="wlc2-hero__topbar">
            <div class="container container-1230">
                <div class="wlc2-hero__topbar-inner">
                    <span class="wlc2-hero__eyebrow"><span></span>{{ __('wireless.hero.eyebrow') }}</span>
                    <div class="wlc2-hero__topstats">
                        <div class="wlc2-hero__topstat"><div class="wlc2-hero__topstat-num">250K+</div><div class="wlc2-hero__topstat-lbl">{{ __('wireless.hero.stat1_lbl') }}</div></div>
                        <div class="wlc2-hero__topstat-divider"></div>
                        <div class="wlc2-hero__topstat"><div class="wlc2-hero__topstat-num">6M+</div><div class="wlc2-hero__topstat-lbl">{{ __('wireless.hero.stat2_lbl') }}</div></div>
                        <div class="wlc2-hero__topstat-divider"></div>
                        <div class="wlc2-hero__topstat"><div class="wlc2-hero__topstat-num">100s</div><div class="wlc2-hero__topstat-lbl">{{ __('wireless.hero.stat3_lbl') }}</div></div>
                    </div>
                </div>
            </div>
        </div>
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

    <section class="wlc2-bento">
        <div class="container container-1230">
            <div class="wlc2-bento__head">
                <h2>{{ __('wireless.bento.heading') }}</h2>
                <p>{{ __('wireless.bento.subheading') }}</p>
            </div>
            <div class="wlc2-bento-grid">
                <div class="wlc2-bento-cell wlc2-bento-cell--lg">
                    <span class="wlc2-bento-cell__num">01</span>
                    <div class="wlc2-bento-cell__icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fe5723" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></div>
                    <span class="wlc2-bento-cell__tag">{{ __('wireless.bento.c1_tag') }}</span>
                    <h3>{{ __('wireless.bento.c1_title') }}</h3>
                    <p>{{ __('wireless.bento.c1_desc') }}</p>
                </div>
                <div class="wlc2-bento-cell wlc2-bento-cell--accent">
                    <span class="wlc2-bento-cell__num">02</span>
                    <div class="wlc2-bento-cell__icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></div>
                    <span class="wlc2-bento-cell__tag">{{ __('wireless.bento.c2_tag') }}</span>
                    <h3>{{ __('wireless.bento.c2_title') }}</h3>
                    <p>{{ __('wireless.bento.c2_desc') }}</p>
                </div>
                <div class="wlc2-bento-cell wlc2-bento-cell--sm">
                    <span class="wlc2-bento-cell__num wlc2-bento-cell__num--dark">03</span>
                    <div class="wlc2-bento-cell__icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fe5723" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></div>
                    <span class="wlc2-bento-cell__tag">{{ __('wireless.bento.c3_tag') }}</span>
                    <h3>{{ __('wireless.bento.c3_title') }}</h3>
                    <p>{{ __('wireless.bento.c3_desc') }}</p>
                </div>
                <div class="wlc2-bento-cell wlc2-bento-cell--sm2">
                    <span class="wlc2-bento-cell__num wlc2-bento-cell__num--dark">04</span>
                    <div class="wlc2-bento-cell__icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#3188ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></div>
                    <span class="wlc2-bento-cell__tag">{{ __('wireless.bento.c4_tag') }}</span>
                    <h3>{{ __('wireless.bento.c4_title') }}</h3>
                    <p>{{ __('wireless.bento.c4_desc') }}</p>
                </div>
            </div>
        </div>
    </section>

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
            <div class="wlc2-role-panel active" id="wlc-tab-designers">
                <div class="wlc2-role-panel__img"><img src="{{ asset('images/casambi/Lighting-Designers.png') }}" alt="Lighting Designers"><span class="wlc2-role-panel__img-badge">{{ __('wireless.roles.r1_badge') }}</span></div>
                <div class="wlc2-role-panel__text">
                    <h3>{{ __('wireless.roles.r1_title') }}</h3><p>{{ __('wireless.roles.r1_desc') }}</p>
                    <ul class="wlc2-role-list">
                        <li><div class="wlc2-role-list__dot"></div><div><strong>{{ __('wireless.roles.r1_li1_strong') }}</strong><span>{{ __('wireless.roles.r1_li1_span') }}</span></div></li>
                        <li><div class="wlc2-role-list__dot"></div><div><strong>{{ __('wireless.roles.r1_li2_strong') }}</strong><span>{{ __('wireless.roles.r1_li2_span') }}</span></div></li>
                        <li><div class="wlc2-role-list__dot"></div><div><strong>{{ __('wireless.roles.r1_li3_strong') }}</strong><span>{{ __('wireless.roles.r1_li3_span') }}</span></div></li>
                    </ul>
                    <a class="wlc2-role-cta">{{ __('wireless.roles.r1_cta') }} <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
                </div>
            </div>
            <div class="wlc2-role-panel" id="wlc-tab-engineers">
                <div class="wlc2-role-panel__img"><img src="{{ asset('images/casambi/Fastest-Installation.jpg') }}" alt="Electrical Engineers"><span class="wlc2-role-panel__img-badge">{{ __('wireless.roles.r2_badge') }}</span></div>
                <div class="wlc2-role-panel__text">
                    <h3>{{ __('wireless.roles.r2_title') }}</h3><p>{{ __('wireless.roles.r2_desc') }}</p>
                    <ul class="wlc2-role-list">
                        <li><div class="wlc2-role-list__dot"></div><div><strong>{{ __('wireless.roles.r2_li1_strong') }}</strong><span>{{ __('wireless.roles.r2_li1_span') }}</span></div></li>
                        <li><div class="wlc2-role-list__dot"></div><div><strong>{{ __('wireless.roles.r2_li2_strong') }}</strong><span>{{ __('wireless.roles.r2_li2_span') }}</span></div></li>
                        <li><div class="wlc2-role-list__dot"></div><div><strong>{{ __('wireless.roles.r2_li3_strong') }}</strong><span>{{ __('wireless.roles.r2_li3_span') }}</span></div></li>
                    </ul>
                    <a class="wlc2-role-cta">{{ __('wireless.roles.r2_cta') }} <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
                </div>
            </div>
            <div class="wlc2-role-panel" id="wlc-tab-managers">
                <div class="wlc2-role-panel__img"><img src="{{ asset('images/casambi/yourfingertips.jpg') }}" alt="Facility Managers"><span class="wlc2-role-panel__img-badge">{{ __('wireless.roles.r3_badge') }}</span></div>
                <div class="wlc2-role-panel__text">
                    <h3>{{ __('wireless.roles.r3_title') }}</h3><p>{{ __('wireless.roles.r3_desc') }}</p>
                    <ul class="wlc2-role-list">
                        <li><div class="wlc2-role-list__dot"></div><div><strong>{{ __('wireless.roles.r3_li1_strong') }}</strong><span>{{ __('wireless.roles.r3_li1_span') }}</span></div></li>
                        <li><div class="wlc2-role-list__dot"></div><div><strong>{{ __('wireless.roles.r3_li2_strong') }}</strong><span>{{ __('wireless.roles.r3_li2_span') }}</span></div></li>
                        <li><div class="wlc2-role-list__dot"></div><div><strong>{{ __('wireless.roles.r3_li3_strong') }}</strong><span>{{ __('wireless.roles.r3_li3_span') }}</span></div></li>
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

    <section class="wlc2-mosaic">
        <div class="container container-1230">
            <div class="wlc2-mosaic__head">
                <h2>{{ __('wireless.mosaic.heading') }}</h2>
                <p>{{ __('wireless.mosaic.subheading') }}</p>
            </div>
            <div class="wlc2-mosaic-grid">
                @foreach([
                    ['office.jpeg','t1'],['retail.jpg','t2'],['industrial.jpg','t3'],
                    ['outdoor.jpeg','t4'],['hospitality.jpeg','t5'],['museum.jpg','t6'],['healcare.jpeg','t7']
                ] as [$img,$key])
                <div class="wlc2-tile">
                    <img class="wlc2-tile__img" src="{{ asset('images/casambi/' . $img) }}" alt="" onerror="this.style.display='none'">
                    <div class="wlc2-tile__ov"></div>
                    <div class="wlc2-tile__label">
                        <p class="wlc2-tile__name">{{ __('wireless.mosaic.' . $key . '_name') }}</p>
                        <p class="wlc2-tile__sub">{{ __('wireless.mosaic.' . $key . '_sub') }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="wlc2-cases">
        <div class="container container-1230">
            <div class="wlc2-cases__head">
                <h2>{{ __('wireless.cases.heading') }}</h2>
                <p>{{ __('wireless.cases.subheading') }}</p>
            </div>
            <div class="wlc2-cases-timeline">
                @foreach([['bbc.jpg','c1'],['Swatch Melbourne.jpg','c2'],['Helsinki Airport.jpeg','c3']] as [$img,$c])
                <div class="wlc2-case-item">
                    <div class="wlc2-case-img">
                        <img src="{{ asset('images/casambi/' . $img) }}" alt="{{ __('wireless.cases.' . $c . '_title') }}" onerror="this.style.display='none'">
                        <span class="wlc2-case-cat">{{ __('wireless.cases.' . $c . '_cat') }}</span>
                    </div>
                    <div class="wlc2-case-body">
                        <span class="wlc2-case-body__num">{{ __('wireless.cases.' . $c . '_num') }}</span>
                        <h3>{{ __('wireless.cases.' . $c . '_title') }}</h3>
                        <p>{{ __('wireless.cases.' . $c . '_desc') }}</p>
                        <div class="wlc2-case-stats">
                            <div><span class="wlc2-case-stat__val">{{ __('wireless.cases.' . $c . '_stat1_val') }}</span><span class="wlc2-case-stat__label">{{ __('wireless.cases.' . $c . '_stat1_lbl') }}</span></div>
                            <div><span class="wlc2-case-stat__val">{{ __('wireless.cases.' . $c . '_stat2_val') }}</span><span class="wlc2-case-stat__label">{{ __('wireless.cases.' . $c . '_stat2_lbl') }}</span></div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

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
                            <div class="accordion" id="accordionWireless">
                                @foreach(['q1','q2','q3','q4','q5','q6'] as $i => $k)
                                <div class="accordion-items">
                                    <h2 class="accordion-header">
                                        <button class="accordion-buttons{{ $i === 0 ? '' : ' collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#wl-faq-{{ $i }}" aria-expanded="{{ $i === 0 ? 'true' : 'false' }}">
                                            {{ __('dali.faq.' . $k) }}<span class="accordion-icon"></span>
                                        </button>
                                    </h2>
                                    <div id="wl-faq-{{ $i }}" class="accordion-collapse collapse{{ $i === 0 ? ' show' : '' }}" data-bs-parent="#accordionWireless">
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
                            <div class="app-cta-store-box d-flex align-items-center tp_fade_anim">
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

@endsection
