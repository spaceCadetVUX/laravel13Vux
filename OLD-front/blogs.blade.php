@extends('front.layouts.frontend', [
    'seo' => [
        'title'          => __('blog.meta_title'),
        'description'    => __('blog.meta_description'),
        'keywords'       => __('blog.keywords'),
        'og_title'       => __('blog.og_title'),
        'og_description' => __('blog.og_description'),
        'image'          => ($ogImg = \App\Models\Setting::get('default_og_image'))
                                ? (str_starts_with($ogImg, 'http') ? $ogImg : asset($ogImg))
                                : asset('images/casambi/0-hero-banner.jpg'),
        'canonical'      => url(request()->path()),
        'robots'         => 'index, follow',
        'type'           => 'website',
        'hreflangs'      => [
            'vi' => switch_locale_url('vi'),
            'en' => switch_locale_url('en'),
        ],
    ]
])

@push('head')
@php $bcLocale = app()->getLocale(); @endphp
<x-breadcrumb-schema :items="[
    ['name' => $bcLocale === 'vi' ? 'Trang chủ' : 'Home', 'url' => route($bcLocale . '.index')],
    ['name' => $bcLocale === 'vi' ? 'Tin tức' : 'Blog'],
]" />
@endpush

@section('content')

{{-- ── Hero ──────────────────────────────────────────────────── --}}
<section class="shop-hero position-relative overflow-hidden">
    <img src="{{ asset('images/casambi/bbc.jpg') }}" alt="Blog Hero Background" class="shop-hero-bg w-100 h-100 position-absolute top-0 start-0 object-fit-cover" style="z-index: 0; filter: brightness(1);">
    <div class="container position-relative h-100 d-flex align-items-center" style="z-index: 2;">
        <div>
            <p class="font-xs text-white fw-bold letter-wide m-0" style="margin-bottom: 6px !important;">{{ $bcLocale === 'vi' ? 'TIN TỨC & BÀI VIẾT' : 'NEWS & ARTICLES' }}</p>
            <p class="font-xs text-white-50 m-0" style="margin-bottom: 20px !important;">{{ $bcLocale === 'vi' ? 'Cập nhật kiến thức, xu hướng và câu chuyện từ chúng tôi' : 'Insights, trends and stories from our team' }}</p>
            <h1 class="shop-hero-title text-white fw-black m-0" style="font-size: clamp(3rem, 8vw, 8rem); line-height: 0.9; letter-spacing: 0.05em; text-transform: uppercase;">
                <span class="d-block" style="font-size: clamp(0.9rem, 1.5vw, 1.4rem); letter-spacing: 0.2em; font-weight: 500; margin-bottom: 4px; opacity: 0.75;">{{ $bcLocale === 'vi' ? 'CASAMBI' : 'CASAMBI' }}</span>
                BLOG
            </h1>
        </div>
    </div>
</section>

{{-- ── Search & Breadcrumb ─────────────────────────────────── --}}
<section class="blog-hero" style="padding-top: 0; background: none;">
    <div class="container">
        <div class="blog-hero__inner" style="padding-top: 2rem;">
            <form class="blog-hero__search" action="{{ route(current_locale() . '.blog.search') }}" method="GET">
                <input type="text" name="q" placeholder="{{ $bcLocale === 'vi' ? 'Tìm kiếm bài viết...' : 'Search articles...' }}" value="{{ request('q') }}">
                <button type="submit">
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
                        <path d="M18.9999 19L14.6499 14.65M17 9C17 13.4183 13.4183 17 9 17C4.58172 17 1 13.4183 1 9C1 4.58172 4.58172 1 9 1C13.4183 1 17 4.58172 17 9Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</section>

{{-- ── Category Filter Pills ───────────────────────────────── --}}
@if(isset($blogCategories) && $blogCategories->count() > 0)
<div class="blog-filter-bar">
    <div class="container">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="{{ route(current_locale() . '.blog.index') }}"
               class="blog-filter-pill {{ !request('blog_category') ? 'active' : '' }}">
                {{ $bcLocale === 'vi' ? 'Tất cả' : 'All' }}
            </a>
            @foreach($blogCategories as $rootCat)
                <a href="{{ route(current_locale() . '.blog.index') }}?blog_category[]={{ $rootCat->slug }}"
                   class="blog-filter-pill {{ in_array($rootCat->slug, (array) request('blog_category')) ? 'active' : '' }}">
                    {{ $rootCat->name }}
                    <span class="pill-count">({{ $rootCat->total_blog_count }})</span>
                </a>
                @foreach($rootCat->children as $child)
                    <a href="{{ route(current_locale() . '.blog.index') }}?blog_category[]={{ $child->slug }}"
                       class="blog-filter-pill {{ in_array($child->slug, (array) request('blog_category')) ? 'active' : '' }}">
                        {{ $child->name }}
                        <span class="pill-count">({{ $child->blog_count }})</span>
                    </a>
                @endforeach
            @endforeach
        </div>
    </div>
</div>
@else
<div style="margin-bottom: 3rem;"></div>
@endif

{{-- ── Blog Grid ───────────────────────────────────────────── --}}
<div class="container pb-5">

    @if(isset($searchTerm) || isset($category))
    <p class="font-xs text-uppercase letter-wide mb-4" style="color: var(--color-silver);">
        @if(isset($searchTerm))
            {{ $bcLocale === 'vi' ? 'Kết quả tìm kiếm:' : 'Results for:' }} "{{ $searchTerm }}" — {{ $blogs->total() }} {{ $bcLocale === 'vi' ? 'bài viết' : 'posts' }}
        @elseif(isset($category))
            {{ $bcLocale === 'vi' ? 'Danh mục:' : 'Category:' }} {{ $category }} — {{ $blogs->total() }} {{ $bcLocale === 'vi' ? 'bài viết' : 'posts' }}
        @endif
    </p>
    @endif

    <div class="row g-4">
        @forelse($blogs as $blog)
        <div class="col-md-6 col-lg-4">
            <div class="blog-card">

                {{-- Image --}}
                <a href="{{ route(current_locale() . '.blog.show', $blog->slug) }}" class="blog-card__img-wrap d-block">
                    @if($blog->featured_image)
                        <img src="{{ asset($blog->featured_image) }}" alt="{{ $blog->title }}" loading="lazy">
                    @else
                        <div class="blog-card__img-placeholder"></div>
                    @endif
                </a>

                {{-- Category --}}
                @if($blog->category)
                <div class="blog-card__category">
                    <svg width="11" height="11" viewBox="0 0 15 14" fill="none">
                        <path d="M4.39012 4.13048H4.39847M13.6056 8.14369L8.74375 12.6328C8.61780 12.7492 8.46823 12.8415 8.30359 12.9046C8.13896 12.9676 7.96248 13 7.78426 13C7.60604 13 7.42956 12.9676 7.26493 12.9046C7.10029 12.8415 6.95072 12.7492 6.82477 12.6328L1 7.2609V1H7.78087L13.6056 6.37811C13.8582 6.61273 14 6.93009 14 7.2609C14 7.59171 13.8582 7.90908 13.6056 8.14369Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    {{ $blog->category }}
                </div>
                @endif

                {{-- Title --}}
                <h2 class="blog-card__title">
                    <a href="{{ route(current_locale() . '.blog.show', $blog->slug) }}">{{ Str::limit($blog->title, 65) }}</a>
                </h2>

                {{-- Excerpt --}}
                @if($blog->excerpt)
                <p class="blog-card__excerpt">{{ strip_tags($blog->excerpt) }}</p>
                @endif

                {{-- Read More + Date --}}
                <div class="d-flex align-items-center justify-content-between mt-auto">
                    <a href="{{ route(current_locale() . '.blog.show', $blog->slug) }}" class="blog-card__read-more">
                        {{ $bcLocale === 'vi' ? 'Đọc thêm' : 'Read more' }}
                        <svg width="11" height="11" viewBox="0 0 12 12" fill="none">
                            <path d="M1 11L11 1M11 1H1M11 1V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                    <div class="blog-card__date">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        {{ $blog->formatted_published_date }}
                    </div>
                </div>

            </div>
        </div>
        @empty
        <div class="col-12 blog-empty">
            <h4>{{ $bcLocale === 'vi' ? 'Chưa có bài viết nào' : 'No posts yet' }}</h4>
            <p>{{ $bcLocale === 'vi' ? 'Hãy quay lại sau để đọc những bài viết mới nhất!' : 'Check back soon for the latest posts!' }}</p>
        </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($blogs->hasPages())
    <div class="blog-pagination">
        @if($blogs->onFirstPage())
            <span class="disabled">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
            </span>
        @else
            <a href="{{ $blogs->previousPageUrl() }}" rel="prev">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
        @endif

        @foreach($blogs->getUrlRange(1, $blogs->lastPage()) as $page => $url)
            @if($page == $blogs->currentPage())
                <span class="current">{{ $page }}</span>
            @else
                <a href="{{ $url }}">{{ $page }}</a>
            @endif
        @endforeach

        @if($blogs->hasMorePages())
            <a href="{{ $blogs->nextPageUrl() }}" rel="next">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </a>
        @else
            <span class="disabled">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </span>
        @endif
    </div>
    @endif

</div>

@endsection

@push('scripts')
<script>
window.blogIndexRoute = "{{ route(current_locale() . '.blog.index') }}";
</script>
@endpush
