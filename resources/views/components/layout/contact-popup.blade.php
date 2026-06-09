@php
    $cpEmail   = \App\Models\Setting::get('contact_email');
    $cpPhone   = \App\Models\Setting::get('contact_phone_display') ?: \App\Models\Setting::get('contact_phone');
    $cpAddress = \App\Models\Setting::get('contact_address');
    $cpMaps    = \App\Models\Setting::get('contact_maps_url');
    $cpHours   = \App\Models\Setting::get('contact_working_hours');

    $cpSocials = [
        ['key' => 'social_instagram', 'icon' => 'bi-instagram',  'label' => 'Instagram'],
        ['key' => 'social_facebook',  'icon' => 'bi-facebook',   'label' => 'Facebook'],
        ['key' => 'social_tiktok',    'icon' => 'bi-tiktok',     'label' => 'TikTok'],
        ['key' => 'social_youtube',   'icon' => 'bi-youtube',    'label' => 'YouTube'],
        ['key' => 'social_zalo',      'icon' => 'bi-chat-dots',  'label' => 'Zalo'],
    ];
@endphp

<style>
#contact-popup { position: relative; }

.cp-close {
    position: absolute; top: 14px; right: 14px;
    background: rgba(255,255,255,0.1); border: none; color: #fff;
    width: 34px; height: 34px; border-radius: 50%;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    font-size: 13px; z-index: 2; transition: background .2s;
}
.cp-close:hover { background: rgba(255,255,255,0.2); }

.cp-header {
    background: #0a0a0a;
    padding: 28px 28px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.07);
}
.cp-logo { height: 26px; display: block; margin-bottom: 8px; }
.cp-tagline { color: rgba(255,255,255,0.45); font-size: .78rem; margin: 0; letter-spacing: .02em; }

.cp-body { padding: 20px 24px 24px; }

.cp-info-list { display: flex; flex-direction: column; gap: 14px; }
.cp-info-item {
    display: flex; align-items: flex-start; gap: 13px;
    color: inherit; text-decoration: none; transition: opacity .2s;
}
.cp-info-item:hover { opacity: .78; }
.cp-info-icon {
    width: 36px; height: 36px; flex-shrink: 0;
    background: rgba(255,255,255,0.07); border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 14px;
}
.cp-info-label {
    display: block; color: rgba(255,255,255,0.42);
    font-size: .68rem; letter-spacing: .07em; text-transform: uppercase; margin-bottom: 2px;
}
.cp-info-value { display: block; color: #fff; font-size: .875rem; font-weight: 500; }

.cp-divider { height: 1px; background: rgba(255,255,255,0.07); margin: 18px 0; }

.cp-social-label {
    color: rgba(255,255,255,0.42); font-size: .68rem;
    letter-spacing: .07em; text-transform: uppercase; margin-bottom: 10px;
}
.cp-social-list { display: flex; flex-wrap: wrap; gap: 8px; }
.cp-social-btn {
    display: inline-flex; align-items: center; gap: 7px;
    background: rgba(255,255,255,0.08); border-radius: 50px;
    padding: 7px 14px; color: #fff; text-decoration: none;
    font-size: .8rem; transition: background .2s;
}
.cp-social-btn:hover { background: rgba(255,255,255,0.16); color: #fff; }
</style>

{{-- ── Overlay ────────────────────────────────────────────── --}}
<div id="contact-popup-overlay" style="display:none" onclick="closeContactPopup()"></div>

{{-- ── Popup ───────────────────────────────────────────────── --}}
<div id="contact-popup" style="display:none" role="dialog" aria-modal="true" aria-label="Contact">

    {{-- Close --}}
    <button class="cp-close" onclick="closeContactPopup()" aria-label="Close">
        <i class="bi bi-x-lg"></i>
    </button>

    {{-- Header --}}
    <div class="cp-header">
        <img src="{{ asset('images/casambi/casambiwhite.svg') }}" alt="Casambi" class="cp-logo">
        <p class="cp-tagline">{{ __('footer.tagline') }}</p>
    </div>

    {{-- Body --}}
    <div class="cp-body">

        {{-- Contact info --}}
        <div class="cp-info-list">
            @if($cpPhone)
            <a href="tel:{{ $cpPhone }}" class="cp-info-item">
                <span class="cp-info-icon"><i class="bi bi-telephone-fill"></i></span>
                <div>
                    <span class="cp-info-label">{{ app()->getLocale() === 'vi' ? 'Điện thoại' : 'Phone' }}</span>
                    <span class="cp-info-value">{{ $cpPhone }}</span>
                </div>
            </a>
            @endif

            @if($cpEmail)
            <a href="mailto:{{ $cpEmail }}" class="cp-info-item">
                <span class="cp-info-icon"><i class="bi bi-envelope-fill"></i></span>
                <div>
                    <span class="cp-info-label">Email</span>
                    <span class="cp-info-value">{{ $cpEmail }}</span>
                </div>
            </a>
            @endif

            @if($cpAddress)
            <a href="{{ $cpMaps ?: '#' }}" target="{{ $cpMaps ? '_blank' : '_self' }}" rel="noopener" class="cp-info-item">
                <span class="cp-info-icon"><i class="bi bi-geo-alt-fill"></i></span>
                <div>
                    <span class="cp-info-label">{{ app()->getLocale() === 'vi' ? 'Địa chỉ' : 'Address' }}</span>
                    <span class="cp-info-value">{{ $cpAddress }}</span>
                </div>
            </a>
            @endif

            @if($cpHours)
            <div class="cp-info-item">
                <span class="cp-info-icon"><i class="bi bi-clock-fill"></i></span>
                <div>
                    <span class="cp-info-label">{{ app()->getLocale() === 'vi' ? 'Giờ làm việc' : 'Working hours' }}</span>
                    <span class="cp-info-value">{{ $cpHours }}</span>
                </div>
            </div>
            @endif
        </div>

        {{-- Divider --}}
        <div class="cp-divider"></div>

        {{-- Social --}}
        <div class="cp-social-section">
            <p class="cp-social-label">{{ app()->getLocale() === 'vi' ? 'Theo dõi chúng tôi' : 'Follow us' }}</p>
            <div class="cp-social-list">
                @foreach($cpSocials as $s)
                    @php $url = \App\Models\Setting::get($s['key']); @endphp
                    @if($url)
                    <a href="{{ $url }}" class="cp-social-btn" target="_blank" rel="noopener" aria-label="{{ $s['label'] }}">
                        <i class="bi {{ $s['icon'] }}"></i>
                        <span>{{ $s['label'] }}</span>
                    </a>
                    @endif
                @endforeach
            </div>
        </div>

    </div>
</div>

<script>
function openContactPopup() {
    const popup   = document.getElementById('contact-popup');
    const overlay = document.getElementById('contact-popup-overlay');
    if (!popup || !overlay) return;

    overlay.removeAttribute('style');
    popup.removeAttribute('style');

    overlay.style.cssText = [
        'display:block',
        'position:fixed',
        'top:0;left:0;right:0;bottom:0',
        'background:rgba(0,0,0,.6)',
        'backdrop-filter:blur(3px)',
        'z-index:999998',
        'opacity:1',
    ].join(';');

    popup.style.cssText = [
        'display:block',
        'position:fixed',
        'top:50%',
        'left:50%',
        'transform:translate(-50%,-50%)',
        'width:480px',
        'max-width:92vw',
        'background:#111',
        'border-radius:20px',
        'z-index:999999',
        'overflow:hidden',
        'box-shadow:0 32px 80px rgba(0,0,0,.7)',
    ].join(';');

    document.body.style.overflow = 'hidden';
}
function closeContactPopup() {
    const popup   = document.getElementById('contact-popup');
    const overlay = document.getElementById('contact-popup-overlay');
    if (popup)   { popup.style.display   = 'none'; }
    if (overlay) { overlay.style.display = 'none'; }
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeContactPopup();
});
</script>
