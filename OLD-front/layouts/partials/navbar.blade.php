<div id="header-sticky" class="tp-header-area tp-header-ptb tp-header-blur sticky-white-bg header-transparent tp-header-border">
        <div class="container container-1750">
            <div class="row align-items-center">
                <div class="col-xl-2 col-lg-6 col-6">
                    <div class="tp-header-logo">
                        <a href="{{ route('home') }}">
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
                                    <!-- home -->
                                    <!--<li>-->
                                    <!--    <a href="{{ route('home') }}">{{ __('nav.home_button') }}</a>-->
                                    <!--</li>-->
                                    <!-- pages -->
                                    <li class="has-dropdown">
                                        <a href="#">{{ __('nav.page_button') }}</a>
                                        <ul class="tp-submenu submenu">
                                            <li><a href="{{ route(current_locale() . '.dali-casambi') }}">{{ __('nav.dali') }}</a></li>
                                            <li><a href="{{ route(current_locale() . '.wireless-casambi') }}">{{ __('nav.wireless') }}</a></li>
                                        </ul>
                                    </li>
                                    <!-- solutions by role -->
                                    <li>
                                        <a href="{{ route(current_locale() . '.solutions-by-role') }}">{{ __('nav.solutions') }}</a>
                                    </li>
                                    <!-- product page -->
                                    <!-- <li class="has-dropdown">
                                        <a href="#">{{ __('nav.products') }}</a>
                                        <div class="tp-megamenu-wrapper mega-menu megamenu-white-bg">
                                            <div class="row gx-0">
                                                <div class="col-xl-2">
                                                    <div class="tp-megamenu-list">
                                                        <h4 class="tp-megamenu-title">Phân loại A</h4>
                                                        <ul>
                                                            <li><a href="">child</a></li>
                                                            <li><a href="">child</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <div class="col-xl-2">
                                                    <div class="tp-megamenu-list">
                                                        <h4 class="tp-megamenu-title">Phân loại A</h4>
                                                        <ul>
                                                            <li><a href="">child</a></li>
                                                            <li><a href="">child</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <div class="col-xl-2">
                                                    <div class="tp-megamenu-list">
                                                        <h4 class="tp-megamenu-title">Phân loại A</h4>
                                                        <ul>
                                                            <li><a href="">child</a></li>
                                                            <li><a href="">child</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li> -->
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
                    <a  href="{{ route('home') }}">
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
                <div class="tp-offcanvas-content d-none d-xl-block">
                    <h3 class="tp-offcanvas-title">Hello There!</h3>
                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, </p>
                </div>
                <div class="tp-offcanvas-menu d-xl-none">
                    <nav>
                        <ul>
                            <li>
                                <a href="{{ route('home') }}">{{ __('nav.home_button') }}</a>
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
@php
    $cpPhone   = \App\Models\Setting::get('contact_phone_display') ?: \App\Models\Setting::get('contact_phone');
    $cpEmail   = \App\Models\Setting::get('contact_email');
    $cpAddress = \App\Models\Setting::get('contact_address');
    $cpMaps    = \App\Models\Setting::get('contact_maps_url');
    $cpHours   = \App\Models\Setting::get('contact_working_hours');
    $cpSocials = [
        ['key' => 'social_instagram', 'icon' => 'bi-instagram', 'label' => 'Instagram'],
        ['key' => 'social_facebook',  'icon' => 'bi-facebook',  'label' => 'Facebook'],
        ['key' => 'social_tiktok',    'icon' => 'bi-tiktok',    'label' => 'TikTok'],
        ['key' => 'social_youtube',   'icon' => 'bi-youtube',   'label' => 'YouTube'],
        ['key' => 'social_zalo',      'icon' => 'bi-chat-dots', 'label' => 'Zalo'],
    ];
@endphp

<div class="tp-contact-popup-area" id="tpContactPopupArea">
    <div class="tp-contact-popup-close">
        <button class="tp-contact-popup-close-btn" id="tpContactPopupCloseBtn" aria-label="Close">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="tp-contact-popup-box">
        {{-- Header --}}
        <div class="tp-contact-popup-header">
            <img src="{{ asset('images/casambi/casambiwhite.svg') }}" alt="Casambi" style="height:26px;width:auto;display:block;margin-bottom:10px;">
            <p style="color:rgba(255,255,255,.75);font-size:13px;margin:0;">{{ __('footer.tagline') }}</p>
        </div>
        {{-- Info --}}
        <div class="tp-contact-popup-info">
            @if($cpPhone)
            <a href="tel:{{ $cpPhone }}" class="tp-contact-popup-item">
                <span class="tp-contact-popup-icon"><i class="bi bi-telephone-fill"></i></span>
                <div>
                    <span class="tp-contact-popup-label">{{ app()->getLocale() === 'vi' ? 'Điện thoại' : 'Phone' }}</span>
                    <span class="tp-contact-popup-value">{{ $cpPhone }}</span>
                </div>
            </a>
            @endif
            @if($cpEmail)
            <a href="mailto:{{ $cpEmail }}" class="tp-contact-popup-item">
                <span class="tp-contact-popup-icon"><i class="bi bi-envelope-fill"></i></span>
                <div>
                    <span class="tp-contact-popup-label">Email</span>
                    <span class="tp-contact-popup-value">{{ $cpEmail }}</span>
                </div>
            </a>
            @endif
            @if($cpAddress)
            <a href="{{ $cpMaps ?: '#' }}" {{ $cpMaps ? 'target="_blank" rel="noopener"' : '' }} class="tp-contact-popup-item">
                <span class="tp-contact-popup-icon"><i class="bi bi-geo-alt-fill"></i></span>
                <div>
                    <span class="tp-contact-popup-label">{{ app()->getLocale() === 'vi' ? 'Địa chỉ' : 'Address' }}</span>
                    <span class="tp-contact-popup-value">{{ $cpAddress }}</span>
                </div>
            </a>
            @endif
            @if($cpHours)
            <div class="tp-contact-popup-item">
                <span class="tp-contact-popup-icon"><i class="bi bi-clock-fill"></i></span>
                <div>
                    <span class="tp-contact-popup-label">{{ app()->getLocale() === 'vi' ? 'Giờ làm việc' : 'Working hours' }}</span>
                    <span class="tp-contact-popup-value">{{ $cpHours }}</span>
                </div>
            </div>
            @endif
        </div>
        {{-- Divider --}}
        <div style="height:1px;background:rgba(0,0,0,.08);margin:20px 0;"></div>
        {{-- Social --}}
        <p class="tp-contact-popup-label" style="margin-bottom:12px;padding:0 28px;">{{ app()->getLocale() === 'vi' ? 'Theo dõi chúng tôi' : 'Follow us' }}</p>
        <div class="tp-contact-popup-socials">
            @foreach($cpSocials as $s)
                @php $cpUrl = \App\Models\Setting::get($s['key']); @endphp
                @if($cpUrl)
                <a href="{{ $cpUrl }}" target="_blank" rel="noopener" aria-label="{{ $s['label'] }}">
                    <i class="bi {{ $s['icon'] }}"></i>
                    <span>{{ $s['label'] }}</span>
                </a>
                @endif
            @endforeach
        </div>
    </div>
</div>

<style>
.tp-contact-popup-area {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,.45);
    backdrop-filter: blur(4px);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: opacity .35s ease, visibility .35s ease;
}
.tp-contact-popup-area.opened {
    opacity: 1;
    visibility: visible;
}
.tp-contact-popup-close {
    position: absolute;
    top: 24px; right: 24px;
}
.tp-contact-popup-close-btn {
    width: 42px; height: 42px;
    background: rgba(0,0,0,.08);
    border: 1px solid rgba(0,0,0,.1);
    border-radius: 50%;
    color: #333; font-size: 15px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: background .2s, color .2s;
}
.tp-contact-popup-close-btn:hover { background: #ff5623; border-color: #ff5623; color: #fff; }
.tp-contact-popup-box {
    background: #fff;
    border-radius: 20px;
    width: 460px;
    max-width: 92vw;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 24px 80px rgba(0,0,0,.18);
    transform: translateY(16px) scale(.97);
    transition: transform .35s ease;
}
.tp-contact-popup-area.opened .tp-contact-popup-box {
    transform: translateY(0) scale(1);
}
.tp-contact-popup-header {
    background: linear-gradient(135deg, #ff5623, #c73d12);
    padding: 32px 28px 24px;
    border-radius: 20px 20px 0 0;
}
.tp-contact-popup-info {
    padding: 24px 28px 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.tp-contact-popup-item {
    display: flex; align-items: flex-start; gap: 14px;
    text-decoration: none; color: inherit;
}
.tp-contact-popup-item:hover .tp-contact-popup-value { color: #ff5623; }
.tp-contact-popup-icon {
    width: 38px; height: 38px; flex-shrink: 0;
    background: rgba(255,86,35,.1);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #ff5623; font-size: 15px;
}
.tp-contact-popup-label {
    display: block;
    font-size: 11px; color: rgba(0,0,0,.4);
    text-transform: uppercase; letter-spacing: .06em;
}
.tp-contact-popup-value {
    display: block;
    font-size: 14px; color: #111; font-weight: 500;
    line-height: 1.4; word-break: break-word;
    transition: color .2s;
}
.tp-contact-popup-socials {
    padding: 0 28px 28px;
    display: flex; flex-wrap: wrap; gap: 8px;
}
.tp-contact-popup-socials a {
    display: flex; align-items: center; gap: 7px;
    padding: 8px 14px;
    background: #f5f5f5;
    border: 1px solid #e8e8e8;
    border-radius: 999px;
    color: #333; text-decoration: none; font-size: 13px;
    transition: background .2s, border-color .2s, color .2s;
}
.tp-contact-popup-socials a:hover { background: #ff5623; border-color: #ff5623; color: #fff; }
.tp-contact-popup-socials a i { font-size: 14px; }
</style>

@push('scripts')
<script src="{{ asset('assets/js/header-search.js') }}"></script>
<script>
(function() {
    const area     = document.getElementById('tpContactPopupArea');
    const closeBtn = document.getElementById('tpContactPopupCloseBtn');
    if (!area) return;

    window.openContactPopup = function() {
        area.classList.add('opened');
        document.body.style.overflow = 'hidden';
    };

    closeBtn && closeBtn.addEventListener('click', function() {
        area.classList.remove('opened');
        document.body.style.overflow = '';
    });

    area.addEventListener('click', function(e) {
        if (e.target === area) {
            area.classList.remove('opened');
            document.body.style.overflow = '';
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && area.classList.contains('opened')) {
            area.classList.remove('opened');
            document.body.style.overflow = '';
        }
    });
})();
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchArea = document.getElementById('tpSearchArea');
    const closeBtn = document.getElementById('tpSearchCloseBtn');
    const searchInput = document.getElementById('header-search-input');

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
});
</script>
@endpush
