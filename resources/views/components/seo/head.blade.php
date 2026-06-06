@props([
    'seoMeta'             => null,
    'currentUrl'          => null,
    'alternateUrls'       => [],
    'fallbackTitle'       => '',
    'fallbackDescription' => '',
    'fallbackImage'       => null,
    'ogType'              => 'website',
])
@php
    $resolvedCurrentUrl  = $currentUrl ?: request()->fullUrl();
    $resolvedCanonical   = $seoMeta?->canonical_url ?: request()->url();
@endphp
<title>{{ $seoMeta?->meta_title ?? $fallbackTitle }} — {{ config('app.name') }}</title>
<meta name="description" content="{{ $seoMeta?->meta_description ?? $fallbackDescription }}">
<meta name="robots" content="{{ $seoMeta?->robots ?? 'index, follow' }}">

{{-- Canonical — prefer admin-configured value, fall back to current URL without query string --}}
<link rel="canonical" href="{{ $resolvedCanonical }}" />

{{-- Hreflang --}}
@foreach($alternateUrls as $lang => $url)
    <link rel="alternate" hreflang="{{ $lang }}" href="{{ $url }}" />
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ $alternateUrls['vi'] ?? $resolvedCurrentUrl }}" />

{{-- Open Graph --}}
@php
    $ogTitle       = $seoMeta?->og_title       ?: ($seoMeta?->meta_title        ?: $fallbackTitle);
    $ogDescription = $seoMeta?->og_description  ?: ($seoMeta?->meta_description   ?: $fallbackDescription);
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
<meta property="og:url"         content="{{ $resolvedCurrentUrl }}" />
@if($ogImage)
<meta property="og:image"       content="{{ $ogImage }}" />
<meta property="og:image:alt"   content="{{ $ogTitle }}" />
@endif

{{-- Twitter Card --}}
<meta name="twitter:card"        content="{{ $seoMeta?->twitter_card ?? 'summary_large_image' }}" />
<meta name="twitter:title"       content="{{ $seoMeta?->twitter_title ?: $ogTitle }}" />
<meta name="twitter:description" content="{{ $seoMeta?->twitter_description ?: $ogDescription }}" />
@if($ogImage)
<meta name="twitter:image"       content="{{ $ogImage }}" />
@endif

{{-- Keywords --}}
@if($seoMeta?->meta_keywords)
<meta name="keywords" content="{{ $seoMeta->meta_keywords }}" />
@endif
