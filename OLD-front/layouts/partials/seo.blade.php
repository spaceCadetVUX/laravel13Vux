{{-- Basic Meta --}}
<title>{{ $seo['title'] ?? config('app.name') }}</title>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<meta name="description" content="{{ $seo['description'] ?? '' }}">
<meta name="keywords" content="{{ $seo['keywords'] ?? '' }}">
<meta name="author" content="{{ config('app.name') }}">

{{-- Canonical URL --}}
<link rel="canonical" href="{{ $seo['canonical'] ?? url()->current() }}">

{{-- Robots --}}
<meta name="robots" content="{{ $seo['robots'] ?? 'index, follow' }}">



{{-- Open Graph (Facebook / LinkedIn) --}}
<meta property="og:type" content="{{ $seo['type'] ?? 'website' }}">
<meta property="og:title" content="{{ $seo['og_title'] ?? $seo['title'] ?? config('app.name') }}">
<meta property="og:description" content="{{ $seo['og_description'] ?? $seo['description'] ?? '' }}">
<meta property="og:image" content="{{ $seo['image'] ?? asset('images/default-og.jpg') }}">
<meta property="og:url" content="{{ $seo['canonical'] ?? url()->current() }}">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:locale" content="{{ ['vi' => 'vi_VN', 'en' => 'en_US'][app()->getLocale()] ?? app()->getLocale() }}">



{{-- Twitter Card --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seo['title'] ?? config('app.name') }}">
<meta name="twitter:description" content="{{ $seo['description'] ?? '' }}">
<meta name="twitter:image" content="{{ $seo['image'] ?? asset('images/default-og.jpg') }}">



{{-- Multilingual hreflang --}}
@if(!empty($seo['hreflangs']))
    @foreach($seo['hreflangs'] as $lang => $url)
        <link rel="alternate" hreflang="{{ $lang }}" href="{{ $url }}">
    @endforeach
    {{-- x-default: trỏ về bản tiếng Việt (ngôn ngữ chính) --}}
    @if(!empty($seo['hreflangs']['vi']))
        <link rel="alternate" hreflang="x-default" href="{{ $seo['hreflangs']['vi'] }}">
    @endif
@endif