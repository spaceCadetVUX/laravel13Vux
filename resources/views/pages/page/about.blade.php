@php
    $locale   = app()->getLocale();
    $appName  = config('app.name');
    $appUrl   = config('app.url');
    $ogImage  = $data['ogImage']
        ? (str_starts_with($data['ogImage'], 'http') ? $data['ogImage'] : asset($data['ogImage']))
        : asset('images/casambi/0-hero-banner.jpg');
@endphp

@extends('layouts.frontend')

@push('head')
@php
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

    $aboutPageSchema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'AboutPage',
        'name'        => $data['seoTitle'],
        'description' => $data['seoDescription'] ?: $data['description'],
        'url'         => url()->current(),
        'inLanguage'  => $locale === 'vi' ? 'vi-VN' : 'en-US',
        'publisher'   => [
            '@type' => 'Organization',
            'name'  => $appName,
            'url'   => $appUrl,
            'logo'  => ['@type' => 'ImageObject', 'url' => $logoUrl],
        ],
    ];

    $orgSchema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Organization',
        'name'        => $appName,
        'url'         => $appUrl,
        'logo'        => ['@type' => 'ImageObject', 'url' => $logoUrl],
        'description' => $data['description'],
    ];

    if ($data['foundedYear'])    $orgSchema['foundingDate'] = $data['foundedYear'];
    if (!empty($socialLinks))    $orgSchema['sameAs']       = $socialLinks;

    $contactEmail   = \App\Models\Setting::get('contact_email');
    $contactPhone   = \App\Models\Setting::get('contact_phone');
    $contactAddress = \App\Models\Setting::get('contact_address');
    $contactCity    = \App\Models\Setting::get('contact_city');
    $contactCountry = \App\Models\Setting::get('contact_country');
    $contactPostal  = \App\Models\Setting::get('contact_postal_code');

    if ($contactEmail) $orgSchema['email']     = $contactEmail;
    if ($contactPhone) $orgSchema['telephone'] = $contactPhone;

    if ($contactAddress || $contactCity || $contactCountry) {
        $orgSchema['address'] = array_filter([
            '@type'           => 'PostalAddress',
            'streetAddress'   => $contactAddress,
            'addressLocality' => $contactCity,
            'addressCountry'  => $contactCountry,
            'postalCode'      => $contactPostal,
        ]);
    }

    if (!empty($data['certifications'])) {
        $orgSchema['knowsAbout'] = array_values($data['certifications']);
    }

    $bcItems = [
        ['name' => $locale === 'vi' ? 'Trang chủ' : 'Home', 'url' => route($locale . '.index')],
        ['name' => $locale === 'vi' ? 'Giới thiệu' : 'About Us'],
    ];

    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
@endphp
<script type="application/ld+json">{!! json_encode($aboutPageSchema, $jsonFlags) !!}</script>
<script type="application/ld+json">{!! json_encode($orgSchema, $jsonFlags) !!}</script>
<x-breadcrumb-schema :items="$bcItems" />
@php
    $aboutFaqs = $locale === 'vi' ? [
        ['q' => 'Casambi Vietnam được thành lập khi nào?',
         'a' => 'Casambi Vietnam là đối tác chính thức được ủy quyền bởi Casambi Technologies (Phần Lan), hoạt động tại thị trường Việt Nam với sứ mệnh mang hệ thống điều khiển chiếu sáng thông minh đến các công trình trong nước.'],
        ['q' => 'Casambi Vietnam có chứng nhận gì?',
         'a' => 'Casambi Vietnam là đại lý ủy quyền chính thức (Authorized Partner) của Casambi Technologies — thương hiệu điều khiển chiếu sáng hàng đầu từ Phần Lan, được ứng dụng tại hơn 170 quốc gia trên thế giới.'],
        ['q' => 'Casambi Vietnam hỗ trợ dự án ở những tỉnh thành nào?',
         'a' => 'Casambi Vietnam hỗ trợ tư vấn, thiết kế và triển khai giải pháp chiếu sáng thông minh trên toàn quốc, tập trung tại TP. Hồ Chí Minh, Hà Nội và các tỉnh thành trọng điểm.'],
        ['q' => 'Đội ngũ kỹ thuật của Casambi Vietnam có kinh nghiệm không?',
         'a' => 'Đội ngũ kỹ thuật của Casambi Vietnam được đào tạo trực tiếp bởi Casambi Technologies, có kinh nghiệm triển khai nhiều dự án chiếu sáng thông minh quy mô lớn tại Việt Nam.'],
    ] : [
        ['q' => 'When was Casambi Vietnam established?',
         'a' => 'Casambi Vietnam is an officially authorized partner of Casambi Technologies (Finland), operating in the Vietnamese market with the mission of bringing smart lighting control systems to local projects.'],
        ['q' => 'What certifications does Casambi Vietnam hold?',
         'a' => 'Casambi Vietnam is an official Authorized Partner of Casambi Technologies — a leading lighting control brand from Finland, deployed in over 170 countries worldwide.'],
        ['q' => 'Which regions does Casambi Vietnam support?',
         'a' => 'Casambi Vietnam provides consulting, design, and implementation of smart lighting solutions nationwide, with a focus on Ho Chi Minh City, Hanoi, and key provinces.'],
        ['q' => 'Is the Casambi Vietnam technical team experienced?',
         'a' => 'Our technical team is trained directly by Casambi Technologies and has experience deploying large-scale smart lighting projects across Vietnam.'],
    ];
    $aboutFaqSchema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => array_map(fn($f) => [
            '@type'          => 'Question',
            'name'           => $f['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
        ], $aboutFaqs),
    ];
@endphp
<script type="application/ld+json">{!! json_encode($aboutFaqSchema, $jsonFlags) !!}</script>
@endpush

@section('content')

<div class="container py-5">

    <div class="row justify-content-center">
        <div class="col-lg-8">

            <h1 class="mb-4">{{ $locale === 'vi' ? 'Giới thiệu' : 'About Us' }}</h1>

            @if($data['description'])
                <div class="mb-4">
                    {!! nl2br(e($data['description'])) !!}
                </div>
            @endif

            @if($data['foundedYear'])
                <p><strong>{{ $locale === 'vi' ? 'Năm thành lập' : 'Founded' }}:</strong> {{ $data['foundedYear'] }}</p>
            @endif

            @if(!empty($data['certifications']))
                <h2 class="h5 mt-4 mb-3">{{ $locale === 'vi' ? 'Chứng chỉ & Đối tác' : 'Certifications & Partners' }}</h2>
                <ul>
                    @foreach($data['certifications'] as $cert)
                        <li>{{ trim($cert) }}</li>
                    @endforeach
                </ul>
            @endif

        </div>
    </div>

</div>

{{-- FAQ Section --}}
<section class="home-faq-section">
    <div class="container">
        <h2 class="home-faq__title">{{ $locale === 'vi' ? 'Câu hỏi thường gặp' : 'Frequently Asked Questions' }}</h2>
        <div class="home-faq__list">
            @foreach($aboutFaqs as $faq)
            <details class="home-faq__item">
                <summary class="home-faq__question">{{ $faq['q'] }}</summary>
                <div class="home-faq__answer">{{ $faq['a'] }}</div>
            </details>
            @endforeach
        </div>
    </div>
</section>

@endsection
