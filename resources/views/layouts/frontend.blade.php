<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    {{-- SEO: title, meta, og, canonical, hreflang ──────────────────────────── --}}
    @php $_alternateUrls = view()->shared('alternateUrls', []); @endphp
    <x-seo.head
        :seoMeta="$seoMeta ?? null"
        :currentUrl="url()->current()"
        :alternateUrls="$_alternateUrls"
        :fallbackTitle="$fallbackTitle ?? config('app.name')"
        :fallbackDescription="$fallbackDescription ?? ''"
        :fallbackImage="$fallbackImage ?? null"
        :ogType="$ogType ?? 'website'"
    />
    {{-- Google Analytics --}}
    @include('components.layout.ga')
    {{-- Google Search Console Verification --}}
    @php $gscVerification = \App\Models\Setting::get('google_search_console'); @endphp
    @if($gscVerification)
    <meta name="google-site-verification" content="{{ $gscVerification }}" />
    @endif
    {{-- Bing Webmaster Tools Verification --}}
    <meta name="msvalidate.01" content="BE8FDF5FCE7C392F768C9CC5EAC49DEB" />
    {{-- Favicon --}}
    @php
        $faviconRaw = \App\Models\Setting::get('site_favicon');
        $faviconUrl = $faviconRaw
            ? (str_starts_with($faviconRaw, 'http') ? $faviconRaw : asset($faviconRaw))
            : asset('images/casambi/favicon.svg');
    @endphp
    <link rel="icon" href="{{ $faviconUrl }}">
    <link rel="shortcut icon" href="{{ $faviconUrl }}">
    {{-- AI crawler discovery --}}
    <link rel="llms" href="{{ url('/llms.txt') }}">
    <link rel="llms-full" href="{{ url('/llms-full.txt') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0a0a0a">
    <meta http-equiv="x-dns-prefetch-control" content="on">
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.css') }}">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Vite Assets -->
    <link rel="stylesheet" href="{{ asset('assets/css/frontend.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}">
    <script defer src="{{ asset('assets/js/frontend.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('assets/css/slick.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/swiper-bundle.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/magnific-popup.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/atropos.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/casambi.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/dali.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/wireless.css') }}">

    {{-- Page specific head content (JSON-LD, etc.) --}}
    @stack('head')
</head>

<body>

{{-- Navbar nằm ngoài smooth-content để không bị ScrollSmoother cuốn theo --}}
@include('components.layout.navbar')

{{-- $noScrollSmoother = true trên các trang nặng (shop, product, blog) để bỏ GSAP ScrollSmoother --}}
<div id="{{ ($noScrollSmoother ?? false) ? 'page-wrapper' : 'smooth-wrapper' }}">
    <div id="{{ ($noScrollSmoother ?? false) ? 'page-content' : 'smooth-content' }}">

        {{-- Page content --}}
        @yield('content')

        {{-- Footer --}}
        @include('components.layout.footer')

    </div>
</div>


{{-- Scroll to Top Button --}}
<button id="scrollTop" aria-label="Scroll to top">
    <i class="bi bi-arrow-up"></i>
</button>

<!-- GSAP -->
<script src="{{ asset('assets/js/vendor/jquery.js') }}"></script>
<script src="{{ asset('assets/js/gsap.min.js') }}"></script>
<script src="{{ asset('assets/js/ScrollTrigger.min.js') }}"></script>
<script src="{{ asset('assets/js/ScrollToPlugin.min.js') }}"></script>
<script src="{{ asset('assets/js/ScrollSmoother.min.js') }}"></script>
<script src="{{ asset('assets/js/bootstrap-bundle.js') }}"></script>
<script src="{{ asset('assets/js/swiper-bundle.js') }}"></script>
<script src="{{ asset('assets/js/plugin.js') }}"></script>
<script src="{{ asset('assets/js/scroll-magic.js') }}"></script>
<script src="{{ asset('assets/js/hover-effect.umd.js') }}"></script>
<script src="{{ asset('assets/js/magnific-popup.js') }}"></script>
<script src="{{ asset('assets/js/parallax-slider.js') }}"></script>
<script src="{{ asset('assets/js/nice-select.js') }}"></script>
<script src="{{ asset('assets/js/purecounter.js') }}"></script>
<script src="{{ asset('assets/js/isotope-pkgd.js') }}"></script>
<script src="{{ asset('assets/js/imagesloaded-pkgd.js') }}"></script>
<script src="{{ asset('assets/js/main.js') }}"></script>
{{-- Trigger sticky header check — supports both native scroll and GSAP ScrollSmoother --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    var header = document.getElementById('header-sticky');
    if (!header) return;
    function checkSticky() {
        var scrollY = window.scrollY || window.pageYOffset;
        var wrapper = document.getElementById('smooth-wrapper');
        if (wrapper && wrapper.scrollTop > 0) scrollY = wrapper.scrollTop;
        if (scrollY >= 20) {
            header.classList.add('header-sticky');
        } else {
            header.classList.remove('header-sticky');
        }
    }
    checkSticky();
    window.addEventListener('scroll', checkSticky);
    var wrapper = document.getElementById('smooth-wrapper');
    if (wrapper) wrapper.addEventListener('scroll', checkSticky);
});
</script>

{{-- Page specific scripts --}}
@stack('scripts')

</body>
</html>
