@props([
    'image',
    'name',
    'price'     => null,
    'oldPrice'  => null,
    'brand'     => null,
    'onSale'    => false,
    'discount'  => null,
    'alt'       => null,
    'url'       => null,
])

<a href="{{ $url ?? '#' }}" class="pc-wrap" aria-label="{{ $name }}">
    {{-- Image area --}}
    <div class="pc-img-area">
        <img src="{{ $image ?: asset('images/casambi/product-placeholder.jpg') }}"
             alt="{{ $alt ?? $name }}"
             onerror="this.src='{{ asset('images/casambi/product-placeholder.jpg') }}'"
             class="pc-img">
        @if($onSale && $discount)
            <span class="pc-badge">-{{ $discount }}%</span>
        @endif
    </div>

    {{-- Info --}}
    <div class="pc-info">
        @if($brand)
            <span class="pc-brand">{{ $brand }}</span>
        @endif
        <p class="pc-name">{{ $name }}</p>
        @if($price)
            <div class="pc-price-row">
                <span class="pc-price">{{ $price }}</span>
                @if($oldPrice)
                    <span class="pc-old-price">{{ $oldPrice }}</span>
                @endif
            </div>
        @endif
    </div>

    {{-- Footer --}}
    <div class="pc-footer">
        <span class="pc-cta">{{ $locale ?? app()->getLocale() === 'vi' ? 'Xem chi tiết' : 'View details' }}</span>
        <span class="pc-arrow">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </span>
    </div>
</a>
