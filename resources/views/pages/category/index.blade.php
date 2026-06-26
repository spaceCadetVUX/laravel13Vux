@extends('layouts.frontend')

@section('title', $fallbackTitle)
@section('meta_description', $fallbackDescription)

@section('content')

<section class="cat-index-hero">
    <div class="container">
        <p class="cat-index-hero__label">{{ $locale === 'vi' ? 'SẢN PHẨM' : 'PRODUCTS' }}</p>
        <h1 class="cat-index-hero__title">{{ $fallbackTitle }}</h1>
        @if($locale === 'vi')
        <p class="cat-index-hero__sub">Khám phá toàn bộ danh mục thiết bị chiếu sáng thông minh Casambi.</p>
        @else
        <p class="cat-index-hero__sub">Browse the full range of Casambi smart lighting product categories.</p>
        @endif
    </div>
</section>

<section class="cat-index-grid">
    <div class="container">
        @if($categories->isEmpty())
            <p class="cat-index-empty">{{ $locale === 'vi' ? 'Chưa có danh mục nào.' : 'No categories available yet.' }}</p>
        @else
        <div class="row g-4">
            @foreach($categories as $tr)
            @php $cat = $tr->category; @endphp
            <div class="col-6 col-md-4 col-lg-3">
                <a href="{{ route(current_locale() . '.category.show', $tr->slug) }}" class="cat-card">
                    <div class="cat-card__img-wrap">
                        @if($cat->image_path)
                            <img src="{{ asset('storage/' . $cat->image_path) }}"
                                 alt="{{ $tr->name }}"
                                 loading="lazy"
                                 width="320" height="200">
                        @else
                            <div class="cat-card__img-placeholder">
                                <i class="fa-light fa-grid-2"></i>
                            </div>
                        @endif
                    </div>
                    <div class="cat-card__body">
                        <h2 class="cat-card__name">{{ $tr->name }}</h2>
                        @if($tr->description)
                        <p class="cat-card__desc">{{ Str::limit(strip_tags($tr->description), 80) }}</p>
                        @endif
                        @if(($cat->product_count ?? 0) > 0)
                        <span class="cat-card__count">
                            {{ $cat->product_count }} {{ $locale === 'vi' ? 'sản phẩm' : 'products' }}
                        </span>
                        @endif
                    </div>
                </a>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>

@endsection

@push('head')
<style>
.cat-index-hero {
    padding: 64px 0 40px;
    background: var(--color-off-white);
    border-bottom: 1px solid var(--color-border);
}
.cat-index-hero__label {
    font-size: 0.65rem;
    font-weight: var(--fw-bold);
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--color-accent-dark);
    margin-bottom: 0.5rem;
}
.cat-index-hero__title {
    font-size: clamp(1.8rem, 4vw, 3rem);
    font-weight: var(--fw-black);
    letter-spacing: -0.02em;
    margin-bottom: 0.75rem;
    color: var(--color-dark);
}
.cat-index-hero__sub {
    color: var(--color-mid);
    font-size: 0.95rem;
    max-width: 520px;
    margin: 0;
}
.cat-index-grid {
    padding: 56px 0 80px;
}
.cat-index-empty {
    color: var(--color-mid);
    font-size: 0.9rem;
    padding: 40px 0;
}
.cat-card {
    display: flex;
    flex-direction: column;
    background: #fff;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg, 10px);
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    transition: box-shadow 0.2s, transform 0.2s;
    height: 100%;
}
.cat-card:hover {
    box-shadow: 0 8px 28px rgba(0,0,0,0.09);
    transform: translateY(-3px);
    color: inherit;
}
.cat-card__img-wrap {
    aspect-ratio: 16/9;
    overflow: hidden;
    background: var(--color-off-white);
}
.cat-card__img-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.35s;
}
.cat-card:hover .cat-card__img-wrap img { transform: scale(1.04); }
.cat-card__img-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-border);
    font-size: 2.5rem;
}
.cat-card__body {
    padding: 1rem 1.1rem 1.2rem;
    display: flex;
    flex-direction: column;
    flex: 1;
}
.cat-card__name {
    font-size: 0.95rem;
    font-weight: var(--fw-semibold);
    margin: 0 0 0.35rem;
    color: var(--color-dark);
    line-height: 1.3;
}
.cat-card__desc {
    font-size: 0.78rem;
    color: var(--color-mid);
    margin: 0 0 0.6rem;
    line-height: 1.5;
    flex: 1;
}
.cat-card__count {
    font-size: 0.7rem;
    font-weight: var(--fw-semibold);
    color: var(--color-accent-dark);
    letter-spacing: 0.04em;
}
</style>
@endpush
