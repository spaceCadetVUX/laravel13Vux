@props([
    'seoMeta'             => null,
    'currentUrl'          => '',
    'alternateUrls'       => [],
    'fallbackTitle'       => '',
    'fallbackDescription' => '',
    'fallbackImage'       => null,
    'ogType'              => 'website',
])
<title>{{ $seoMeta?->meta_title ?? $fallbackTitle }} — {{ config('app.name') }}</title>
<meta name="description" content="{{ $seoMeta?->meta_description ?? $fallbackDescription }}">
<meta name="robots" content="{{ $seoMeta?->robots ?? 'index, follow' }}">

{{-- Canonical — prefer admin-configured value, fall back to current URL --}}
<link rel="canonical" href="{{ $seoMeta?->canonical_url ?? $currentUrl }}" />

{{-- Hreflang --}}
@foreach($alternateUrls as $lang => $url)
    <link rel="alternate" hreflang="{{ $lang }}" href="{{ $url }}" />
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ $alternateUrls['vi'] ?? $currentUrl }}" />

{{-- Open Graph --}}
@php
    $ogTitle       = $seoMeta?->og_title       ?: ($seoMeta?->meta_title       ?: $fallbackTitle);
    $ogDescription = $seoMeta?->og_description  ?: ($seoMeta?->meta_description  ?: $fallbackDescription);
    $ogImagePath   = $seoMeta?->og_image;
    $ogImage       = $ogImagePath
        ? url(\Illuminate\Support\Facades\Storage::url($ogImagePath))
        : $fallbackImage;
    $resolvedOgType = $seoMeta?->og_type?->value ?: $ogType;
    $ogLocale       = match(app()->getLocale()) {
        'vi'    => 'vi_VN',
        'en'    => 'en_US',
        default => app()->getLocale(),
    };
@endphp
<meta property="og:type"        content="{{ $resolvedOgType }}" />
<meta property="og:locale"      content="{{ $ogLocale }}" />
<meta property="og:site_name"   content="{{ config('app.name') }}" />
<meta property="og:title"       content="{{ $ogTitle }}" />
<meta property="og:description" content="{{ $ogDescription }}" />
<meta property="og:url"         content="{{ $currentUrl }}" />
@if($ogImage)
<meta property="og:image"       content="{{ $ogImage }}" />
@endif
