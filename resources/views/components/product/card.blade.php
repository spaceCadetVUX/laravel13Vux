@props([
    'image',
    'name',
    'price'     => null,
    'oldPrice'  => null,
    'brand'     => null,
    'onSale'    => false,
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
        @if($onSale)
            <span class="pc-badge">{{ __('shop.labels.badge_sale') }}</span>
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
</a>
