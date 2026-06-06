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
    // og_image is stored as an absolute URL by the admin — use it directly.
    // Only wrap through storage if it looks like a relative path (no scheme).
    $ogImage = $ogImagePath
        ? (str_starts_with($ogImagePath, 'http') ? $ogImagePath : url(\Illuminate\Support\Facades\Storage::disk('public')->url($ogImagePath)))
        : $fallbackImage;
    $resolvedOgType  = $seoMeta?->og_type?->value ?: $ogType;
    $currentLocale   = app()->getLocale();
    $ogLocale        = match($currentLocale) {
        'vi'    => 'vi_VN',
        'en'    => 'en_US',
        default => $currentLocale,
    };
    $ogLocaleAlternates = collect($alternateUrls)
        ->keys()
        ->filter(fn ($l) => $l !== $currentLocale && $l !== 'x-default')
        ->map(fn ($l) => match($l) { 'vi' => 'vi_VN', 'en' => 'en_US', default => $l })
        ->values();
@endphp
<meta property="og:type"        content="{{ $resolvedOgType }}" />
<meta property="og:locale"      content="{{ $ogLocale }}" />
@foreach($ogLocaleAlternates as $altLocale)
<meta property="og:locale:alternate" content="{{ $altLocale }}" />
@endforeach
<meta property="og:site_name"   content="{{ config('app.name') }}" />
<meta property="og:title"       content="{{ $ogTitle }}" />
<meta property="og:description" content="{{ $ogDescription }}" />
<meta property="og:url"         content="{{ $currentUrl }}" />
@if($ogImage)
<meta property="og:image"       content="{{ $ogImage }}" />
@endif
