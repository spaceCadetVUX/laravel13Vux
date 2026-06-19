@props([
    'image',
    'name',
    'price',
    'oldPrice' => null,
    'tag' => null,
    'tagLabel' => null,
    'quickAddText' => __('index.products.quick_add'),
    'alt' => null,
    'url' => null,
])

<div {{ isset($attributes) ? $attributes->merge(['class' => 'product-card']) : 'class="product-card"' }}>
    <a href="{{ $url ?? '#' }}" class="product-card-link" aria-label="{{ $name }}">
        <div class="product-img-wrap">
            <img src="{{ $image ?: asset('images/casambi/product-placeholder.jpg') }}"
                 alt="{{ $alt ?? $name }}"
                 onerror="this.src='{{ asset('images/casambi/product-placeholder.jpg') }}'"
                 style="width:100%;aspect-ratio:1/1;object-fit:contain;display:block;background:#f8f8f8;padding:12px;">
            <a href="{{ $url ?? '#' }}" class="product-quick-add">{{ $quickAddText }}</a>
            @if($tag && $tagLabel)
                <span class="product-tag {{ $tag }}">{{ $tagLabel }}</span>
            @endif
        </div>
        <p class="product-name">{{ $name }}</p>
        @if($price)
        <div class="product-price-row">
            <span class="product-price-current">{{ $price }}</span>
            @if($oldPrice)
                <span class="product-price-old">{{ $oldPrice }}</span>
            @endif
        </div>
        @endif
    </a>
</div>
