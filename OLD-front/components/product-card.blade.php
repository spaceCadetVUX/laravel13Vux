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
                 style="width:100%;aspect-ratio:3/4;object-fit:cover;display:block;">
            <a href="{{ $url ?? '#' }}" class="product-quick-add">{{ $quickAddText }}</a>
            @if($tag && $tagLabel)
                <span class="product-tag {{ $tag }}">{{ $tagLabel }}</span>
            @endif
        </div>
        <p class="product-name">{{ $name }}</p>
        {{-- Price hidden intentionally --}}
    </a>
</div>
