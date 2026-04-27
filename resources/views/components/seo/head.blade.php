<title>{{ $seoMeta?->meta_title ?? $fallbackTitle }} — {{ config('app.name') }}</title>
<meta name="description" content="{{ $seoMeta?->meta_description ?? $fallbackDescription }}">

{{-- Canonical — always self-referencing --}}
<link rel="canonical" href="{{ $currentUrl }}" />

{{-- Hreflang — appears on both language versions --}}
@foreach($alternateUrls as $lang => $url)
    <link rel="alternate" hreflang="{{ $lang }}" href="{{ $url }}" />
@endforeach
{{-- x-default → vi --}}
<link rel="alternate" hreflang="x-default" href="{{ $alternateUrls['vi'] ?? $currentUrl }}" />

@if($seoMeta?->og_title)
    <meta property="og:title" content="{{ $seoMeta->og_title }}" />
    <meta property="og:description" content="{{ $seoMeta->og_description }}" />
    <meta property="og:url" content="{{ $currentUrl }}" />
@endif
