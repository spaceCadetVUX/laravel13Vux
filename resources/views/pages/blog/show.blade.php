@extends('layouts.frontend')

@push('head')
@php
    $bcLocale = app()->getLocale();
    $bcItems  = [
        ['name' => $bcLocale === 'vi' ? 'Trang chủ' : 'Home', 'url' => route($bcLocale . '.index')],
        ['name' => $bcLocale === 'vi' ? 'Tin tức' : 'Blog',   'url' => route($bcLocale . '.blog.index')],
    ];
    if ($blog->category && $blog->category_slug) {
        $bcItems[] = ['name' => $blog->category, 'url' => route($bcLocale . '.blog.category', $blog->category_slug)];
    }
    $bcItems[] = ['name' => $blog->title];
@endphp
<x-breadcrumb-schema :items="$bcItems" />

{{-- Article extra OG tags --}}
<meta property="article:published_time" content="{{ $blog->published_at->toIso8601String() }}">
<meta property="article:modified_time" content="{{ $blog->updated_at->toIso8601String() }}">
@if($blog->category)
<meta property="article:section" content="{{ $blog->category }}">
@endif
@if($blog->tags)
    @foreach($blog->tags as $tag)
    <meta property="article:tag" content="{{ $tag }}">
    @endforeach
@endif

{{-- BlogPosting JSON-LD --}}
@php
    $logoRaw    = \App\Models\Setting::get('site_logo');
    $logoUrl    = $logoRaw
        ? (str_starts_with($logoRaw, 'http') ? $logoRaw : url(asset($logoRaw)))
        : url(asset('assets/img/logo/logo.png'));

    $authorModel = $blog->author;
    $authorName  = $authorModel?->name;
    if ($authorModel && filled($authorModel->slug) && strtolower($authorName) !== 'admin') {
        $authorBaseUrl = rtrim((string) config('app.url'), '/');
        $blogAuthor = ['@type' => 'Person', 'name' => $authorName,
            '@id' => $authorBaseUrl . '/authors/' . $authorModel->slug . '#person',
            'url' => $authorBaseUrl . '/authors/' . $authorModel->slug,
        ];
        if ($authorModel->avatar_url) $blogAuthor['image'] = $authorModel->avatar_url;
        if (filled($authorModel->title)) $blogAuthor['jobTitle'] = $authorModel->title;
        $sameAs = $authorModel->same_as;
        if (!empty($sameAs)) $blogAuthor['sameAs'] = count($sameAs) === 1 ? $sameAs[0] : $sameAs;
    } else {
        $blogAuthor = ['@type' => 'Organization', 'name' => 'Casambi Vietnam',
            '@id' => config('app.url') . '/#organization'];
    }

    $blogUrl = route(app()->getLocale() . '.blog.show', [$blog->category_slug, $blog->slug]);

    $blogSchema = [
        '@context'         => 'https://schema.org',
        '@type'            => 'BlogPosting',
        'headline'         => $blog->title,
        'description'      => $blog->seo_description ?? $blog->excerpt ?? '',
        'url'              => $blogUrl,
        'datePublished'    => $blog->published_at->toIso8601String(),
        'dateModified'     => $blog->updated_at->toIso8601String(),
        'author'           => $blogAuthor,
        'publisher'        => [
            '@type' => 'Organization',
            'name'  => 'Casambi Vietnam',
            '@id'   => config('app.url') . '/#organization',
            'logo'  => ['@type' => 'ImageObject', 'url' => $logoUrl],
        ],
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $blogUrl],
        'wordCount'        => str_word_count(strip_tags($blog->content ?? '')),
    ];
    if ($blog->featured_image) $blogSchema['image'] = asset($blog->featured_image);
    if ($blog->category)       $blogSchema['articleSection'] = $blog->category;
    if ($blog->tags)           $blogSchema['keywords']       = implode(', ', $blog->tags);

    $faqSchema = null;
    if (!empty($blog->faqs) && is_array($blog->faqs)) {
        $faqEntities = [];
        foreach ($blog->faqs as $faq) {
            $q = trim($faq['question'] ?? '');
            $a = trim($faq['answer']   ?? '');
            if ($q && $a) {
                $faqEntities[] = [
                    '@type'          => 'Question',
                    'name'           => $q,
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
                ];
            }
        }
        if (!empty($faqEntities)) {
            $faqSchema = [
                '@context'   => 'https://schema.org',
                '@type'      => 'FAQPage',
                'mainEntity' => $faqEntities,
            ];
        }
    }

    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
@endphp
<script type="application/ld+json">{!! json_encode($blogSchema, $jsonFlags) !!}</script>
@if($faqSchema)
<script type="application/ld+json">{!! json_encode($faqSchema, $jsonFlags) !!}</script>
@endif

{{-- JSON-LD schemas from DB (BreadcrumbList, Article, etc. synced by Observer) --}}
{{-- Skip FAQPage if already output inline above to avoid duplicates --}}
@foreach($jsonldSchemas as $schema)
@if(($schema['@type'] ?? '') !== 'FAQPage' || !$faqSchema)
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endif
@endforeach

{{-- Force dark nav on blog detail — no hero overlay, transparent white nav looks broken --}}
<style>
#header-sticky:not(.header-sticky) {
    background: rgba(255, 255, 255, 0.97) !important;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08) !important;
}
#header-sticky:not(.header-sticky) .tp-header-menu nav ul > li > a {
    color: #0a0a0a !important;
}
#header-sticky:not(.header-sticky) .tp-header-menu nav ul > li > a:hover {
    opacity: 0.65;
}
#header-sticky:not(.header-sticky) .logo-white {
    display: none !important;
}
#header-sticky:not(.header-sticky) .logo-black {
    display: inline-block !important;
}
#header-sticky:not(.header-sticky) .tp-search-open-btn {
    color: #0a0a0a !important;
}
#header-sticky:not(.header-sticky) .tp-header-lang a {
    color: #0a0a0a !important;
}
#header-sticky:not(.header-sticky) .tp-offcanvas-open-btn i {
    background-color: #0a0a0a !important;
}
</style>
@endpush

@section('content')
<div style="width:100%;height:72px;"></div>
<div class="blog-detail-wrap">
    <div class="container">
        <div class="row gx-lg-5">

            {{-- ── Main Article ── --}}
            <main class="col-lg-8">

                {{-- Breadcrumb --}}
                <nav class="blog-breadcrumb" aria-label="breadcrumb">
                    <a href="{{ route(current_locale() . '.index') }}">{{ $bcLocale === 'vi' ? 'Trang chủ' : 'Home' }}</a>
                    <span class="sep">›</span>
                    <a href="{{ route(current_locale() . '.blog.index') }}">Blog</a>
                    @if($blog->category && $blog->category_slug)
                    <span class="sep">›</span>
                    <a href="{{ route(current_locale() . '.blog.category', $blog->category_slug) }}">{{ $blog->category }}</a>
                    @endif
                    <span class="sep">›</span>
                    <span class="current">{{ Str::limit($blog->title, 40) }}</span>
                </nav>

                {{-- Article Header --}}
                <header class="blog-article-header">
                    @if($blog->category)
                    <div class="blog-article-category">
                        <svg width="11" height="11" viewBox="0 0 15 14" fill="none">
                            <path d="M4.39012 4.13048H4.39847M13.6056 8.14369L8.74375 12.6328C8.61780 12.7492 8.46823 12.8415 8.30359 12.9046C8.13896 12.9676 7.96248 13 7.78426 13C7.60604 13 7.42956 12.9676 7.26493 12.9046C7.10029 12.8415 6.95072 12.7492 6.82477 12.6328L1 7.2609V1H7.78087L13.6056 6.37811C13.8582 6.61273 14 6.93009 14 7.2609C14 7.59171 13.8582 7.90908 13.6056 8.14369Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        {{ $blog->category }}
                    </div>
                    @endif

                    <h1 class="blog-article-title">{{ $blog->title }}</h1>

                    <div class="blog-article-meta">
                        {{-- Author chip --}}
                        @if($blog->author && strtolower($blog->author->name) !== 'admin')
                        @php
                            $authorRoute = $bcLocale === 'vi' ? 'vi.author.show' : 'en.author.show';
                        @endphp
                        <a href="{{ route($authorRoute, $blog->author->slug) }}" class="blog-article-meta-author">
                            <strong>{{ $blog->author->name }}</strong>
                        </a>
                        @endif

                        <div class="blog-article-meta-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            {{ $blog->formatted_published_date }}
                        </div>
                        @if($blog->reading_time)
                        <div class="blog-article-meta-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                            </svg>
                            {{ $blog->reading_time }}
                        </div>
                        @endif
                    </div>
                </header>

                {{-- Featured Image --}}
                @if($blog->featured_image)
                <img src="{{ asset($blog->featured_image) }}" alt="{{ $blog->title }}" class="blog-article-img">
                @endif

                {{-- Content --}}
                <div class="blog-article-content">
                    <div class="table-responsive">
                        {!! $blog->content !!}
                    </div>
                </div>

                {{-- FAQs --}}
                @if(!empty($blog->faqs) && is_array($blog->faqs))
                @php $visibleFaqs = array_filter($blog->faqs, fn($f) => !empty($f['question']) && !empty($f['answer'])); @endphp
                @if(count($visibleFaqs) > 0)
                <div class="blog-faq">
                    <h2 class="blog-faq__title">{{ $bcLocale === 'vi' ? 'Câu hỏi thường gặp' : 'Frequently Asked Questions' }}</h2>
                    <div class="blog-faq__list">
                        @foreach($visibleFaqs as $faq)
                        <details class="blog-faq__item">
                            <summary class="blog-faq__question">{{ $faq['question'] }}</summary>
                            <div class="blog-faq__answer">{{ $faq['answer'] }}</div>
                        </details>
                        @endforeach
                    </div>
                </div>
                @endif
                @endif

                {{-- Tags --}}
                @if($blog->tags && count($blog->tags) > 0)
                <div class="blog-tags">
                    <span class="blog-tags__label">Tags:</span>
                    @foreach($blog->tags as $tag)
                    <a href="{{ route(current_locale() . '.blog.index') }}?q={{ urlencode($tag) }}" class="blog-tags__item">{{ $tag }}</a>
                    @endforeach
                </div>
                @endif

                {{-- Share --}}
                <div class="blog-share">
                    <span class="blog-share__label">{{ $bcLocale === 'vi' ? 'Chia sẻ:' : 'Share:' }}</span>
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($blog->canonical_url ?? url()->current()) }}" target="_blank" rel="noopener" class="blog-share__btn">
                        <i class="bi bi-facebook"></i>
                    </a>
                    <a href="https://twitter.com/intent/tweet?url={{ urlencode($blog->canonical_url ?? url()->current()) }}&text={{ urlencode($blog->title) }}" target="_blank" rel="noopener" class="blog-share__btn">
                        <i class="bi bi-twitter-x"></i>
                    </a>
                    <a href="https://www.linkedin.com/shareArticle?mini=true&url={{ urlencode($blog->canonical_url ?? url()->current()) }}&title={{ urlencode($blog->title) }}" target="_blank" rel="noopener" class="blog-share__btn">
                        <i class="bi bi-linkedin"></i>
                    </a>
                    <a href="mailto:?subject={{ urlencode($blog->title) }}&body={{ urlencode($blog->canonical_url ?? url()->current()) }}" class="blog-share__btn">
                        <i class="bi bi-envelope"></i>
                    </a>
                </div>

                {{-- Related Posts --}}
                @if(isset($relatedPosts) && $relatedPosts->count() > 0)
                <section>
                    <h3 class="blog-related-title">{{ $bcLocale === 'vi' ? 'Bài viết liên quan' : 'Related Posts' }}</h3>
                    <div class="row g-4">
                        @foreach($relatedPosts as $related)
                        <div class="col-sm-6">
                            <div class="blog-card-mini">
                                <a href="{{ route(current_locale() . '.blog.show', [$related->category_slug, $related->slug]) }}" class="blog-card-mini__img d-block">
                                    @if($related->featured_image)
                                    <img src="{{ asset($related->featured_image) }}" alt="{{ $related->title }}" loading="lazy">
                                    @else
                                    <div style="width:100%;height:100%;background:var(--color-off-white);"></div>
                                    @endif
                                </a>
                                @if($related->category)
                                <div class="blog-card-mini__cat">{{ $related->category }}</div>
                                @endif
                                <a href="{{ route(current_locale() . '.blog.show', [$related->category_slug, $related->slug]) }}" class="blog-card-mini__title d-block">{{ Str::limit($related->title, 65) }}</a>
                                <a href="{{ route(current_locale() . '.blog.show', [$related->category_slug, $related->slug]) }}" class="blog-card-mini__read">
                                    {{ $bcLocale === 'vi' ? 'Đọc thêm' : 'Read more' }}
                                    <svg width="10" height="10" viewBox="0 0 12 12" fill="none"><path d="M1 11L11 1M11 1H1M11 1V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </a>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </section>
                @endif

            </main>

            {{-- ── Sidebar ── --}}
            <aside class="col-lg-4 mt-5 mt-lg-0">
                <div class="blog-sidebar">

                    {{-- Search --}}
                    <div class="sidebar-section">
                        <h4 class="sidebar-section__title">{{ $bcLocale === 'vi' ? 'Tìm kiếm' : 'Search' }}</h4>
                        <form class="sidebar-search-wrap" action="{{ route(current_locale() . '.blog.index') }}" method="GET">
                            <input type="text" name="q" placeholder="{{ $bcLocale === 'vi' ? 'Tìm kiếm bài viết...' : 'Search articles...' }}" value="{{ request('q') }}">
                            <button type="submit">
                                <svg width="14" height="14" viewBox="0 0 20 20" fill="none"><path d="M18.9999 19L14.6499 14.65M17 9C17 13.4183 13.4183 17 9 17C4.58172 17 1 13.4183 1 9C1 4.58172 4.58172 1 9 1C13.4183 1 17 4.58172 17 9Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                        </form>
                    </div>

                    {{-- Categories --}}
                    @if(isset($categories) && $categories->count() > 0)
                    <div class="sidebar-section">
                        <h4 class="sidebar-section__title">{{ $bcLocale === 'vi' ? 'Danh mục' : 'Categories' }}</h4>
                        <ul class="sidebar-cat-list">
                            @foreach($categories as $cat)
                            <li>
                                <a href="{{ route(current_locale() . '.blog.category', $cat->slug) }}"
                                   class="{{ url()->current() == route(current_locale() . '.blog.category', $cat->slug) ? 'active' : '' }}">
                                    <span>{{ $cat->name }}</span>
                                    <svg width="10" height="10" viewBox="0 0 12 12" fill="none"><path d="M1 11L11 1M11 1H1M11 1V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </a>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    {{-- Latest Posts --}}
                    @if(isset($latestPosts) && $latestPosts->count() > 0)
                    <div class="sidebar-section">
                        <h4 class="sidebar-section__title">{{ $bcLocale === 'vi' ? 'Bài viết mới nhất' : 'Latest Posts' }}</h4>
                        <div class="d-flex flex-column gap-4">
                            @foreach($latestPosts as $latest)
                            <div class="blog-card">
                                <a href="{{ route(current_locale() . '.blog.show', [$latest->category_slug, $latest->slug]) }}" class="blog-card__img-wrap d-block">
                                    @if($latest->featured_image)
                                        <img src="{{ asset($latest->featured_image) }}" alt="{{ $latest->title }}" loading="lazy">
                                    @else
                                        <div class="blog-card__img-placeholder"></div>
                                    @endif
                                </a>
                                @if($latest->category)
                                <div class="blog-card__category">
                                    <svg width="10" height="10" viewBox="0 0 15 14" fill="none"><path d="M4.39012 4.13048H4.39847M13.6056 8.14369L8.74375 12.6328C8.61780 12.7492 8.46823 12.8415 8.30359 12.9046C8.13896 12.9676 7.96248 13 7.78426 13C7.60604 13 7.42956 12.9676 7.26493 12.9046C7.10029 12.8415 6.95072 12.7492 6.82477 12.6328L1 7.2609V1H7.78087L13.6056 6.37811C13.8582 6.61273 14 6.93009 14 7.2609C14 7.59171 13.8582 7.90908 13.6056 8.14369Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    {{ $latest->category }}
                                </div>
                                @endif
                                <h3 class="blog-card__title" style="font-size: 0.9rem;">
                                    <a href="{{ route(current_locale() . '.blog.show', [$latest->category_slug, $latest->slug]) }}">{{ Str::limit($latest->title, 60) }}</a>
                                </h3>
                                <div class="d-flex align-items-center justify-content-between mt-auto">
                                    <a href="{{ route(current_locale() . '.blog.show', [$latest->category_slug, $latest->slug]) }}" class="blog-card__read-more">
                                        {{ $bcLocale === 'vi' ? 'Đọc thêm' : 'Read more' }}
                                        <svg width="10" height="10" viewBox="0 0 12 12" fill="none"><path d="M1 11L11 1M11 1H1M11 1V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </a>
                                    <div class="blog-card__date">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                        {{ $latest->formatted_published_date }}
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Tags --}}
                    @if(isset($allTags) && $allTags->count() > 0)
                    <div class="sidebar-section">
                        <h4 class="sidebar-section__title">Tags</h4>
                        <div class="sidebar-tags">
                            @foreach($allTags as $tag)
                            <a href="{{ route(current_locale() . '.blog.index') }}?q={{ urlencode($tag) }}" class="sidebar-tag-item">{{ $tag }}</a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                </div>
            </aside>

        </div>
    </div>
</div>

@endsection
