<div style="padding: 60px;"></div>
<footer class="site-footer">
    <div class="container">
        <div class="row pt-5 pb-4 g-4">

            {{-- Col 1: Brand --}}
            <div class="col-xl-5 col-lg-5 col-md-12">
                <div class="footer-brand-col">
                    @php $siteLogo = \App\Models\Setting::get('site_logo'); @endphp
                    <a href="{{ route(current_locale() . '.index') }}" class="d-inline-block mb-3">
                        <img src="{{ asset('images/casambi/casambiwhite.svg') }}" alt="logo casambi" style="width: 200px; height: auto;">
                    </a>
                    @php
                        $footerProfile  = \App\Models\BusinessProfile::instance();
                        $footerLocale   = app()->getLocale();
                        $footerTagline  = $footerLocale === 'en'
                            ? ($footerProfile->extra['tagline_en'] ?? $footerProfile->tagline ?? '')
                            : ($footerProfile->tagline ?? '');
                    @endphp
                    @if($footerTagline)
                    <p class="footer-tagline">{{ $footerTagline }}</p>
                    @endif
                    @php
                        $footerSocials = [
                            'instagram' => ['key' => 'social_instagram', 'icon' => 'bi-instagram',  'label' => 'Instagram'],
                            'facebook'  => ['key' => 'social_facebook',  'icon' => 'bi-facebook',   'label' => 'Facebook'],
                            'tiktok'    => ['key' => 'social_tiktok',    'icon' => 'bi-tiktok',     'label' => 'TikTok'],
                            'youtube'   => ['key' => 'social_youtube',   'icon' => 'bi-youtube',    'label' => 'YouTube'],
                            'zalo'      => ['key' => 'social_zalo',      'icon' => 'bi-chat-dots',  'label' => 'Zalo'],
                        ];
                    @endphp
                    <div class="footer-socials">
                        @foreach($footerSocials as $s)
                            @php $url = \App\Models\Setting::get($s['key']); @endphp
                            @if($url)
                            <a href="{{ $url }}" class="footer-social-btn" aria-label="{{ $s['label'] }}" target="_blank" rel="noopener">
                                <i class="bi {{ $s['icon'] }}"></i>
                            </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Col 2: Pages --}}
            <div class="col-xl-2 col-lg-2 col-6 offset-xl-2 offset-lg-2">
                <div class="footer-col">
                    <h3 class="footer-col-heading">{{ __('footer.pages_heading') }}</h4>
                    <ul class="footer-links">
                        <li><a href="{{ route(current_locale() . '.index') }}">{{ __('footer.page_home') }}</a></li>
                        <li><a href="{{ route(current_locale() . '.dali-casambi') }}">{{ __('footer.page_dali') }}</a></li>
                        <li><a href="{{ route(current_locale() . '.wireless-casambi') }}">{{ __('footer.page_wireless') }}</a></li>
                        <li><a href="{{ route(current_locale() . '.solutions-by-role') }}">{{ __('nav.solutions') }}</a></li>
                        <li><a href="{{ route(current_locale() . '.product.shop') }}">{{ __('footer.page_shop') }}</a></li>
                        <li><a href="{{ route(current_locale() . '.blog.index') }}">{{ __('footer.page_blog') }}</a></li>
                    </ul>
                </div>
            </div>

            {{-- Col 4: Help & Contact --}}
            <div class="col-xl-3 col-lg-3 col-md-12">
                <div class="footer-col">
                    <h3 class="footer-col-heading">{{ __('footer.help_heading') }}</h4>
                    <ul class="footer-links">
                        <li><a href="#">{{ __('footer.help_faq') }}</a></li>
                        <li><a href="#">{{ __('footer.help_support') }}</a></li>
                    </ul>
                    @php
                        $contactEmail   = \App\Models\Setting::get('contact_email');
                        $contactPhone   = \App\Models\Setting::get('contact_phone_display')
                                       ?: \App\Models\Setting::get('contact_phone');
                        $contactAddress = \App\Models\Setting::get('contact_address');
                        $contactMaps    = \App\Models\Setting::get('contact_maps_url');
                    @endphp
                    <div class="footer-contact">
                        @if($contactEmail)
                        <p><i class="bi bi-envelope"></i> <a href="mailto:{{ $contactEmail }}" style="color:inherit;">{{ $contactEmail }}</a></p>
                        @endif
                        @if($contactPhone)
                        <p><i class="bi bi-telephone"></i> <a href="tel:{{ $contactPhone }}" style="color:inherit;">{{ $contactPhone }}</a></p>
                        @endif
                        @if($contactAddress)
                        <p>
                            <i class="bi bi-geo-alt"></i>
                            @if($contactMaps)
                                <a href="{{ $contactMaps }}" target="_blank" rel="noopener" style="color:inherit;">{{ $contactAddress }}</a>
                            @else
                                {{ $contactAddress }}
                            @endif
                        </p>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Bottom bar --}}
    <div class="footer-bottom">
        <div class="container">
            <div class="footer-bottom-inner" style="align-items: center;">
                <p class="footer-copy" style="margin:0;">{!! __('footer.copyright', ['year' => date('Y'), 'name' => config('app.name')]) !!}</p>
                <div class="footer-legal">
                    <a href="#">{{ __('footer.privacy') }}</a>
                    <a href="#">{{ __('footer.terms') }}</a>
                </div>
            </div>
        </div>
    </div>

</footer>
