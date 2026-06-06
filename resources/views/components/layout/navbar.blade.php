<div id="header-sticky" class="tp-header-area tp-header-ptb tp-header-blur sticky-white-bg header-transparent tp-header-border">
        <div class="container container-1750">
            <div class="row align-items-center">
                <div class="col-xl-2 col-lg-6 col-6">
                    <div class="tp-header-logo">
                        <a href="{{ route(current_locale() . '.index') }}">
                            <img data-width="130" class="logo-white" src="{{ asset('images/casambi/casambiwhite.svg') }}" alt="logo casambi việt nam">
                            <img data-width="130" class="logo-black d-none" src="{{ asset('images/casambi/casambiblack.svg') }}" alt="logo casambi việt nam">
                        </a>
                    </div>
                </div>
                <div class="col-xl-7 d-none d-xl-block">
                    <div class="tp-header-box text-center">
                        <div class="tp-header-menu tp-header-dropdown dropdown-white-bg">
                            <nav>
                                <ul>
                                    <li class="has-dropdown">
                                        <a href="#">{{ __('nav.page_button') }}</a>
                                        <ul class="tp-submenu submenu">
                                            <li><a href="{{ route(current_locale() . '.dali-casambi') }}">{{ __('nav.dali') }}</a></li>
                                            <li><a href="{{ route(current_locale() . '.wireless-casambi') }}">{{ __('nav.wireless') }}</a></li>
                                        </ul>
                                    </li>
                                    <li>
                                        <a href="{{ route(current_locale() . '.solutions-by-role') }}">{{ __('nav.solutions') }}</a>
                                    </li>
                                    <li>
                                        <a href="{{ route(current_locale() . '.product.shop') }}">{{ __('nav.products') }}</a>
                                    </li>
                                    <li>
                                        <a href="{{ route(current_locale() . '.blog.index') }}">{{ __('nav.blog') }}</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-6">
                    <div class="tp-header-right d-flex align-items-center justify-content-end">
                        <div class="tp-header-search d-none d-xl-flex ml-30">
                            <button class="tp-search-open-btn" aria-label="Search" style="background:none;border:none;cursor:pointer;padding:8px;color:#fff;opacity:0.9;transition:opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.9'">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M9 17C13.4183 17 17 13.4183 17 9C17 4.58172 13.4183 1 9 1C4.58172 1 1 4.58172 1 9C1 13.4183 4.58172 17 9 17Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M19 19L14.65 14.65" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                        </div>
                        <div class="tp-header-lang d-none d-xl-flex">
                            <a class="{{ current_locale() === 'vi' ? 'active' : '' }}" href="{{ switch_locale_url('vi') }}" aria-label="Switch to Vietnamese" style="min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;">Vi</a>
                            <a class="{{ current_locale() === 'en' ? 'active' : '' }}" href="{{ switch_locale_url('en') }}" aria-label="Switch to English" style="min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;">En</a>
                        </div>
                        <div class="tp-header-btn-box ml-25 d-none d-md-flex">
                            <a href="#contact" onclick="openContactPopup();return false;" class="tp-btn-black btn-red-bg">
                                <span class="tp-btn-black-filter-blur">
                                    <svg width="0" height="0">
                                        <defs>
                                            <filter id="buttonFilter">
                                                <feGaussianBlur in="SourceGraphic" stdDeviation="5" result="blur"></feGaussianBlur>
                                                <feColorMatrix in="blur" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 19 -9"></feColorMatrix>
                                                <feComposite in="SourceGraphic" in2="buttonFilter" operator="atop"></feComposite>
                                                <feBlend in="SourceGraphic" in2="buttonFilter"></feBlend>
                                            </filter>
                                        </defs>
                                    </svg>
                                </span>
                                <span class="tp-btn-black-filter d-inline-flex align-items-center" style="filter: url(#buttonFilter)">
                                    <span class="tp-btn-black-text">{{ __('nav.contact') }}</span>
                                    <span class="tp-btn-black-circle">
                                        <svg width="10" height="10" viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M1 9L9 1M9 1H1M9 1V9" stroke="currentcolor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                </span>
                            </a>
                        </div>
                        <div class="tp-header-bar ml-20 d-xl-none">
                            <button class="tp-offcanvas-open-btn">
                                <i></i>
                                <i></i>
                                <i></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>



        <div class="tp-offcanvas-area">
        <div class="tp-offcanvas-wrapper @@class">
            <div class="tp-offcanvas-top d-flex align-items-center justify-content-between">
                <div class="tp-offcanvas-logo">
                    <a  href="{{ route(current_locale() . '.index') }}">
                        <img class="logo-1" data-width="120" src="{{ asset('images/casambi/casambiblack.svg') }}" alt="Casambi">
                        <img class="logo-2" data-width="120" src="{{ asset('images/casambi/casambiwhite.svg') }}" alt="Casambi">
                    </a>
                </div>
                <div class="tp-offcanvas-close">
                    <button class="tp-offcanvas-close-btn" aria-label="Close menu">
                        <svg width="37" height="38" viewBox="0 0 37 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9.19141 9.80762L27.5762 28.1924" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M9.19141 28.1924L27.5762 9.80761" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="tp-offcanvas-main">
                <div class="tp-offcanvas-menu d-xl-none">
                    <nav>
                        <ul>
                            <li>
                                <a href="{{ route(current_locale() . '.index') }}">{{ __('nav.home_button') }}</a>
                            </li>
                            <li class="has-dropdown">
                                <a href="#">{{ __('nav.page_button') }}</a>
                                <ul class="tp-submenu submenu">
                                    <li><a href="{{ route(current_locale() . '.dali-casambi') }}">{{ __('nav.dali') }}</a></li>
                                    <li><a href="{{ route(current_locale() . '.wireless-casambi') }}">{{ __('nav.wireless') }}</a></li>
                                </ul>
                            </li>
                            <li>
                                <a href="{{ route(current_locale() . '.solutions-by-role') }}">{{ __('nav.solutions') }}</a>
                            </li>
                            <li class="has-dropdown">
                                <a href="{{ route(current_locale() . '.product.shop') }}">{{ __('nav.products') }}</a>
                                <ul class="tp-submenu submenu">
                                    <li><a href="{{ route(current_locale() . '.product.shop') }}">{{ __('nav.all_Products') }}</a></li>
                                    <li><a href="{{ route(current_locale() . '.product.category') }}">{{ __('nav.category') }}</a></li>
                                </ul>
                            </li>
                            <li>
                                <a href="{{ route(current_locale() . '.blog.index') }}">{{ __('nav.blog') }}</a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <div class="tp-offcanvas-lang d-xl-none">
                    <a href="{{ switch_locale_url('vi') }}" class="offcanvas-lang-link {{ current_locale() === 'vi' ? 'active' : '' }}">VI</a>
                    <span class="offcanvas-lang-divider">/</span>
                    <a href="{{ switch_locale_url('en') }}" class="offcanvas-lang-link {{ current_locale() === 'en' ? 'active' : '' }}">EN</a>
                </div>
                <div class="tp-offcanvas-contact">
                    <h3 class="tp-offcanvas-title sm">Information</h3>
                    @php
                        $ocEmail   = \App\Models\Setting::get('contact_email');
                        $ocPhone   = \App\Models\Setting::get('contact_phone_display')
                                  ?: \App\Models\Setting::get('contact_phone');
                        $ocAddress = \App\Models\Setting::get('contact_address');
                        $ocMaps    = \App\Models\Setting::get('contact_maps_url');
                    @endphp
                    <ul>
                        @if($ocPhone)
                        <li><a href="tel:{{ $ocPhone }}">{{ $ocPhone }}</a></li>
                        @endif
                        @if($ocEmail)
                        <li><a href="mailto:{{ $ocEmail }}">{{ $ocEmail }}</a></li>
                        @endif
                        @if($ocAddress)
                        <li>
                            @if($ocMaps)
                                <a href="{{ $ocMaps }}" target="_blank" rel="noopener">{{ $ocAddress }}</a>
                            @else
                                {{ $ocAddress }}
                            @endif
                        </li>
                        @endif
                    </ul>
                </div>
                <div class="tp-offcanvas-social">
                    <h3 class="tp-offcanvas-title sm">Follow Us</h3>
                    @php
                        $ocSocials = [
                            'instagram' => ['key' => 'social_instagram', 'icon' => 'bi-instagram',  'label' => 'Instagram'],
                            'facebook'  => ['key' => 'social_facebook',  'icon' => 'bi-facebook',   'label' => 'Facebook'],
                            'tiktok'    => ['key' => 'social_tiktok',    'icon' => 'bi-tiktok',     'label' => 'TikTok'],
                            'youtube'   => ['key' => 'social_youtube',   'icon' => 'bi-youtube',    'label' => 'YouTube'],
                            'zalo'      => ['key' => 'social_zalo',      'icon' => 'bi-chat-dots',  'label' => 'Zalo'],
                        ];
                    @endphp
                    <ul>
                        @foreach($ocSocials as $s)
                            @php $ocUrl = \App\Models\Setting::get($s['key']); @endphp
                            @if($ocUrl)
                            <li>
                                <a href="{{ $ocUrl }}" aria-label="{{ $s['label'] }}" target="_blank" rel="noopener">
                                    <i class="bi {{ $s['icon'] }}"></i>
                                </a>
                            </li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="body-overlay"></div>

<!-- Search Modal Area -->
<div class="tp-search-area" id="tpSearchArea">
    <div class="tp-search-close">
        <button class="tp-search-close-btn" id="tpSearchCloseBtn" aria-label="Close Search">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-8 col-lg-10">
                <div class="tp-search-content">
                    <h3 class="tp-search-title mb-40">{{ __('header.search_title') ?? 'Search' }}</h3>
                    <div class="tp-search-form-wrapper p-relative">
                        <form action="{{ route(current_locale() . '.product.shop') }}" method="GET" id="headerSearchForm">
                            <div class="tp-search-input-box position-relative">
                                <input type="text" name="q" id="header-search-input" class="tp-search-input" placeholder="{{ __('header.search_placeholder') ?? 'What are you looking for?' }}" autocomplete="off" data-autocomplete-url="{{ route(current_locale() . '.product.autocomplete') }}" data-shop-url="{{ route(current_locale() . '.product.shop') }}">
                                <button type="submit" class="tp-search-submit-btn" aria-label="Search">
                                    <i class="bi bi-search"></i>
                                </button>
                                <div id="header-autocomplete-dropdown" class="autocomplete-dropdown" style="display:none; position: absolute; top: 100%; left: 0; right: 0; background: white; z-index: 100; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); margin-top: 10px;"></div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Contact Popup ────────────────────────────────────────── --}}
@include('components.layout.contact-popup')

@push('scripts')
<script src="{{ asset('assets/js/header-search.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchArea = document.getElementById('tpSearchArea');
    const closeBtn = document.getElementById('tpSearchCloseBtn');

    if (searchArea && closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            searchArea.classList.remove('opened');
        });

        searchArea.addEventListener('click', function(e) {
            if (e.target === searchArea || e.target.classList.contains('container') || e.target.classList.contains('row')) {
                searchArea.classList.remove('opened');
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && searchArea.classList.contains('opened')) {
                searchArea.classList.remove('opened');
            }
        });
    }

    // Search open button
    document.querySelectorAll('.tp-search-open-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (searchArea) {
                searchArea.classList.add('opened');
                var input = document.getElementById('header-search-input');
                if (input) input.focus();
            }
        });
    });
});
</script>
@endpush
