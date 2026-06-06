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

{{-- ── Styles ──────────────────────────────────────────────── --}}
<style>
.cp-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.55);
    backdrop-filter: blur(4px);
    z-index: 9998;
    transition: opacity .3s ease;
}

.cp-popup {
    position: fixed;
    top: 50%; left: 50%;
    width: min(480px, 92vw);
    background: #111;
    border-radius: 20px;
    z-index: 9999;
    overflow: hidden;
    box-shadow: 0 32px 80px rgba(0,0,0,.6);
    transition: opacity .3s ease, transform .3s ease;
}

/* Close */
.cp-close {
    position: absolute; top: 16px; right: 16px;
    width: 36px; height: 36px;
    background: rgba(255,255,255,.08);
    border: none; border-radius: 50%;
    color: #fff; font-size: 14px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: background .2s;
    z-index: 2;
}
.cp-close:hover { background: rgba(255,86,35,.8); }

/* Header */
.cp-header {
    background: linear-gradient(135deg, #ff5623 0%, #c73d12 100%);
    padding: 36px 32px 28px;
}
.cp-logo { height: 28px; width: auto; margin-bottom: 10px; display: block; }
.cp-tagline { color: rgba(255,255,255,.8); font-size: 13px; margin: 0; line-height: 1.5; }

/* Body */
.cp-body { padding: 24px 28px 28px; }

/* Info list */
.cp-info-list { display: flex; flex-direction: column; gap: 14px; }
.cp-info-item {
    display: flex; align-items: flex-start; gap: 14px;
    text-decoration: none; color: inherit;
    transition: opacity .2s;
}
.cp-info-item:hover { opacity: .8; }
.cp-info-icon {
    width: 38px; height: 38px; flex-shrink: 0;
    background: rgba(255,86,35,.12);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #ff5623; font-size: 15px;
}
.cp-info-item > div { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.cp-info-label { font-size: 11px; color: rgba(255,255,255,.45); text-transform: uppercase; letter-spacing: .06em; }
.cp-info-value { font-size: 14px; color: #fff; font-weight: 500; line-height: 1.4; word-break: break-word; }

/* Divider */
.cp-divider { height: 1px; background: rgba(255,255,255,.08); margin: 22px 0; }

/* Social */
.cp-social-label { font-size: 11px; color: rgba(255,255,255,.45); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 12px; }
.cp-social-list { display: flex; flex-wrap: wrap; gap: 8px; }
.cp-social-btn {
    display: flex; align-items: center; gap: 7px;
    padding: 8px 14px;
    background: rgba(255,255,255,.06);
    border-radius: 999px;
    color: #fff; text-decoration: none; font-size: 13px;
    transition: background .2s, color .2s;
    border: 1px solid rgba(255,255,255,.08);
}
.cp-social-btn:hover { background: #ff5623; color: #fff; border-color: #ff5623; }
.cp-social-btn i { font-size: 14px; }
</style>

{{-- ── Script ──────────────────────────────────────────────── --}}
<script>
function openContactPopup() {
    const popup   = document.getElementById('contact-popup');
    const overlay = document.getElementById('contact-popup-overlay');
    if (!popup || !overlay) return;

    // Reset & show
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
