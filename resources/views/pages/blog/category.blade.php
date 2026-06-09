@extends('layouts.frontend')

@push('head')
@php $bcLocale = app()->getLocale(); @endphp

{{-- BreadcrumbList JSON-LD --}}
<x-breadcrumb-schema :items="[
    ['name' => $bcLocale === 'vi' ? 'Trang chủ' : 'Home',     'url' => route($bcLocale . '.index')],
    ['name' => $bcLocale === 'vi' ? 'Tin tức'   : 'Blog',     'url' => route($bcLocale . '.blog.index')],
    ['name' => $translation->name],
]" />

{{-- JSON-LD schemas from DB (CollectionPage, BreadcrumbList, etc.) --}}
@foreach($jsonldSchemas as $schema)
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endforeach
@endpush

@section('content')

{{-- ── Hero ──────────────────────────────────────────────────── --}}
<section class="shop-hero position-relative overflow-hidden">
    <img src="{{ asset('images/casambi/bbc.jpg') }}" alt="{{ $translation->name }}" class="shop-hero-bg w-100 h-100 position-absolute top-0 start-0 object-fit-cover" style="z-index: 0; filter: brightness(1);">
    <div class="container position-relative h-100 d-flex align-items-center" style="z-index: 2;">
        <div>
            <p class="font-xs text-white fw-bold letter-wide m-0" style="margin-bottom: 6px !important;">{{ $bcLocale === 'vi' ? 'DANH MỤC' : 'CATEGORY' }}</p>
            <p class="font-xs text-white-50 m-0" style="margin-bottom: 20px !important;">
                @if($translation->description)
                    {{ Str::limit(strip_tags($translation->description), 100) }}
                @else
                    {{ $bcLocale === 'vi' ? 'Bài viết theo chủ đề' : 'Articles by topic' }}
                @endif
            </p>
            <h1 class="shop-hero-title text-white fw-black m-0" style="font-size: clamp(2.5rem, 7vw, 7rem); line-height: 0.9; letter-spacing: 0.03em; text-transform: uppercase;">
                <span class="d-block" style="font-size: clamp(0.9rem, 1.5vw, 1.4rem); letter-spacing: 0.2em; font-weight: 500; margin-bottom: 4px; opacity: 0.75;">{{ $bcLocale === 'vi' ? 'BLOG' : 'BLOG' }}</span>
                {{ $translation->name }}
            </h1>
        </div>
    </div>
</section>

{{-- ── Search bar ───────────────────────────────────────────── --}}
<section class="blog-hero" style="padding-top: 0; background: none;">
    <div class="container">
        <div class="blog-hero__inner" style="padding-top: 2rem;">
            <form class="blog-hero__search" action="{{ route($bcLocale . '.blog.index') }}" method="GET">
                <input type="text" name="q" placeholder="{{ $bcLocale === 'vi' ? 'Tìm kiếm bài viết...' : 'Search articles...' }}">
                <button type="submit">
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
                        <path d="M18.9999 19L14.6499 14.65M17 9C17 13.4183 13.4183 17 9 17C4.58172 17 1 13.4183 1 9C1 4.58172 4.58172 1 9 1C13.4183 1 17 4.58172 17 9Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</section>

{{-- ── Category / Subcategory pills ────────────────────────── --}}
<div class="blog-filter-bar">
    <div class="container">
        <div class="d-flex align-items-center gap-2 flex-wrap">

            {{-- All → back to blog index --}}
            <a href="{{ route($bcLocale . '.blog.index') }}" class="blog-filter-pill">
                {{ $bcLocale === 'vi' ? 'Tất cả' : 'All' }}
            </a>

            @if($blogCategory->parent_id)
                {{-- Child category: show parent pill --}}
                <a href="{{ route($bcLocale . '.blog.category', $blogCategory->parent->translations->first()?->slug ?? $blogCategory->parent->slug) }}"
                   class="blog-filter-pill">
                    {{ $blogCategory->parent->translations->first()?->name ?? $blogCategory->parent->name }}
                </a>
                <a href="{{ route($bcLocale . '.blog.category', $translation->slug) }}"
                   class="blog-filter-pill active">
                    {{ $translation->name }}
                    <span class="pill-count">({{ $blogs->total() }})</span>
                </a>
            @else
                {{-- Root category: active + subcategory pills --}}
                <a href="{{ route($bcLocale . '.blog.category', $translation->slug) }}"
                   class="blog-filter-pill active">
                    {{ $translation->name }}
                    <span class="pill-count">({{ $blogs->total() }})</span>
                </a>
                @foreach($blogCategory->children as $child)
                    <a href="{{ route($bcLocale . '.blog.category', $child->slug) }}"
                       class="blog-filter-pill">
                        {{ $child->name }}
                        <span class="pill-count">({{ $child->blog_count }})</span>
                    </a>
                @endforeach
            @endif

        </div>
    </div>
</div>

{{-- ── Blog Grid ───────────────────────────────────────────── --}}
<div class="container pb-5">

    <div class="row g-4">
        @forelse($blogs as $blog)
        <div class="col-md-6 col-lg-4">
            <div class="blog-card">

                <a href="{{ route($bcLocale . '.blog.show', [$blog->category_slug, $blog->slug]) }}" class="blog-card__img-wrap d-block">
                    @if($blog->featured_image)
                        <img src="{{ asset($blog->featured_image) }}" alt="{{ $blog->title }}" loading="lazy">
                    @else
                        <div class="blog-card__img-placeholder"></div>
                    @endif
                </a>

                @if($blog->category)
                <div class="blog-card__category">
                    <svg width="11" height="11" viewBox="0 0 15 14" fill="none">
                        <path d="M4.39012 4.13048H4.39847M13.6056 8.14369L8.74375 12.6328C8.61780 12.7492 8.46823 12.8415 8.30359 12.9046C8.13896 12.9676 7.96248 13 7.78426 13C7.60604 13 7.42956 12.9676 7.26493 12.9046C7.10029 12.8415 6.95072 12.7492 6.82477 12.6328L1 7.2609V1H7.78087L13.6056 6.37811C13.8582 6.61273 14 6.93009 14 7.2609C14 7.59171 13.8582 7.90908 13.6056 8.14369Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    {{ $blog->category }}
                </div>
                @endif

                <h2 class="blog-card__title">
                    <a href="{{ route($bcLocale . '.blog.show', [$blog->category_slug, $blog->slug]) }}">{{ Str::limit($blog->title, 65) }}</a>
                </h2>

                @if($blog->excerpt)
                <p class="blog-card__excerpt">{{ strip_tags($blog->excerpt) }}</p>
                @endif

                <div class="d-flex align-items-center justify-content-between mt-auto">
                    <a href="{{ route($bcLocale . '.blog.show', [$blog->category_slug, $blog->slug]) }}" class="blog-card__read-more">
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

{{-- ── Category Description / Rich Content ─────────────────── --}}
@if($translation->description || $translation->rich_content)
@php
    $richHtml = null;
    if ($translation->rich_content) {
        $decoded = json_decode($translation->rich_content, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['type'])) {
            try {
                $richHtml = (new \Tiptap\Editor([
                    'extensions' => [
                        new \Tiptap\Extensions\StarterKit,
                        new \Tiptap\Nodes\Image,
                    ],
                ]))->setContent($decoded)->getHTML();
                if (trim(strip_tags($richHtml)) === '') $richHtml = null;
            } catch (\Throwable) {
                $richHtml = null;
            }
        } else {
            $richHtml = $translation->rich_content;
        }
    }
@endphp
<div class="container" style="padding-top: 2.5rem; padding-bottom: 1rem;">
    <div style="max-width: 780px; margin: 0 auto;">
        @if($translation->description)
        <p class="text-muted" style="font-size: 1rem; line-height: 1.75;">{{ $translation->description }}</p>
        @endif
        @if($richHtml)
        <div class="blog-rich-content mt-3">
            {!! $richHtml !!}
        </div>
        @endif
    </div>
</div>
@endif

{{-- ── FAQ ──────────────────────────────────────────────────── --}}
@if(!empty($faqs))
<div class="container" style="padding-top: 1rem; padding-bottom: 2rem;">
    <div style="max-width: 780px; margin: 0 auto;">
        <h2 style="font-size: 1.1rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 1.25rem;">
            {{ $bcLocale === 'vi' ? 'Câu hỏi thường gặp' : 'Frequently Asked Questions' }}
        </h2>
        <div class="accordion" id="faqAccordion">
            @foreach($faqs as $i => $faq)
            <div class="accordion-item border-0 border-bottom">
                <h3 class="accordion-header">
                    <button class="accordion-button {{ $i > 0 ? 'collapsed' : '' }} ps-0 fw-semibold"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#faq{{ $i }}"
                            style="font-size: 0.9rem; background: none; box-shadow: none;">
                        {{ $faq['question'] ?? '' }}
                    </button>
                </h3>
                <div id="faq{{ $i }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}" data-bs-parent="#faqAccordion">
                    <div class="accordion-body ps-0 text-muted" style="font-size: 0.875rem; line-height: 1.7;">
                        {{ $faq['answer'] ?? '' }}
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

@endsection
