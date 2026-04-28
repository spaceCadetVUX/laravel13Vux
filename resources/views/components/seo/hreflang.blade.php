@props(['alternateUrls' => [], 'currentUrl' => null])
@foreach($alternateUrls as $lang => $url)
    <link rel="alternate" hreflang="{{ $lang }}" href="{{ $url }}" />
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ $alternateUrls['vi'] ?? ($currentUrl ?? url()->current()) }}" />
