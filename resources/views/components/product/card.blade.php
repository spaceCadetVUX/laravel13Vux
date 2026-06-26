@props([
    'image',
    'name',
    'price'     => null,
    'oldPrice'  => null,
    'category'  => null,
    'brand'     => null,
    'onSale'    => false,
    'discount'  => null,
    'alt'       => null,
    'url'       => null,
])

<a href="{{ $url ?? '#' }}" class="pc-wrap" aria-label="{{ $name }}">
    <div class="pc-img-area">
        <img src="{{ $image ?: asset('images/casambi/product-placeholder.jpg') }}"
             alt="{{ $alt ?? $name }}"
             onerror="this.src='{{ asset('images/casambi/product-placeholder.jpg') }}'"
             class="pc-img">
        @if($onSale && $discount)
            <span class="pc-badge">-{{ $discount }}%</span>
        @endif
    </div>

    <div class="pc-body">
        <span class="pc-category">{{ $category ?? $brand }}</span>
        <p class="pc-name">{{ $name }}</p>
        <div class="pc-bottom">
            <div class="pc-price-col">
                @if($price)
                    <span class="pc-price">{{ $price }}</span>
                    @if($oldPrice)
                        <span class="pc-old-price">{{ $oldPrice }}</span>
                    @endif
                @else
                    <span class="pc-contact-price">{{ app()->getLocale() === 'vi' ? 'Liên hệ báo giá' : 'Contact for price' }}</span>
                @endif
            </div>
            <button class="pc-cart-btn" onclick="event.preventDefault()" aria-label="Thêm vào giỏ">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <path d="M16 10a4 4 0 01-8 0"/>
                </svg>
            </button>
        </div>
    </div>
</a>
