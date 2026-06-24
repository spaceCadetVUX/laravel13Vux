@extends('layouts.frontend')

@push('head')
@php $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT; @endphp
<script type="application/ld+json">{!! json_encode($personSchema, $jsonFlags) !!}</script>

{{-- Force light nav (no hero) --}}
<style>
#header-sticky:not(.header-sticky) {
    background: rgba(255,255,255,0.97) !important;
    border-bottom: 1px solid rgba(0,0,0,0.08) !important;
}
#header-sticky:not(.header-sticky) .tp-header-menu nav ul > li > a { color: #0a0a0a !important; }
#header-sticky:not(.header-sticky) .logo-white { display: none !important; }
#header-sticky:not(.header-sticky) .logo-black { display: inline-block !important; }
#header-sticky:not(.header-sticky) .tp-search-open-btn { color: #0a0a0a !important; }
#header-sticky:not(.header-sticky) .tp-header-lang a { color: #0a0a0a !important; }
#header-sticky:not(.header-sticky) .tp-offcanvas-open-btn i { background-color: #0a0a0a !important; }
</style>
@endpush

@section('content')
<div style="width:100%;height:72px;"></div>

<div class="author-page-wrap">
    <div class="container">

        {{-- Author card --}}
        <div class="author-profile-card">
            @if($author->avatar_url)
            <img src="{{ $author->avatar_url }}" alt="{{ $author->name }}" class="author-profile-avatar">
            @else
            <div class="author-profile-avatar author-profile-avatar--placeholder">
                {{ mb_strtoupper(mb_substr($author->name, 0, 1)) }}
            </div>
            @endif

            <div class="author-profile-info">
                <h1 class="author-profile-name">{{ $author->name }}</h1>
                @if($author->title)
                <p class="author-profile-title">{{ $author->title }}</p>
                @endif

                @if($author->bio)
                <p class="author-profile-bio">{{ $author->bio }}</p>
                @endif

                @if($author->expertise && count($author->expertise) > 0)
                <div class="author-profile-expertise">
                    @foreach($author->expertise as $skill)
                    <span class="author-expertise-tag">{{ $skill }}</span>
                    @endforeach
                </div>
                @endif

                <div class="author-profile-socials">
                    @if($author->website)
                    <a href="{{ $author->website }}" class="author-social-btn" target="_blank" rel="noopener" aria-label="Website">
                        <i class="bi bi-globe"></i>
                    </a>
                    @endif
                    @if($author->linkedin)
                    <a href="{{ $author->linkedin }}" class="author-social-btn" target="_blank" rel="noopener" aria-label="LinkedIn">
                        <i class="bi bi-linkedin"></i>
                    </a>
                    @endif
                    @if($author->twitter)
                    <a href="{{ $author->twitter }}" class="author-social-btn" target="_blank" rel="noopener" aria-label="Twitter / X">
                        <i class="bi bi-twitter-x"></i>
                    </a>
                    @endif
                    @if($author->facebook)
                    <a href="{{ $author->facebook }}" class="author-social-btn" target="_blank" rel="noopener" aria-label="Facebook">
                        <i class="bi bi-facebook"></i>
                    </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Posts by author --}}
        @if($posts->count() > 0)
        <section class="author-posts-section">
            <h2 class="author-posts-heading">
                {{ $locale === 'vi' ? 'Bài viết của tác giả' : 'Articles by this author' }}
                <span class="author-posts-count">{{ $posts->total() }}</span>
            </h2>

            <div class="row g-4">
                @foreach($posts as $post)
                @php
                    $catTr   = $post->blogPost?->blogCategory?->translations->first();
                    $catSlug = $catTr?->slug ?? $post->blogPost?->blogCategory?->slug;
                    $catName = $catTr?->name ?? $post->blogPost?->blogCategory?->name;
                    $postUrl = $catSlug
                        ? route(current_locale() . '.blog.show', [$catSlug, $post->slug])
                        : '#';
                    $imgSrc  = $post->featured_image
                        ? asset('storage/' . ltrim($post->featured_image, '/'))
                        : null;
                    $readMins = max(1, (int) ceil(str_word_count(strip_tags($post->excerpt ?? '')) / 200));
                @endphp
                <div class="col-md-6 col-lg-4">
                    <article class="blog-card">
                        <a href="{{ $postUrl }}" class="blog-card__img-wrap d-block">
                            @if($imgSrc)
                            <img src="{{ $imgSrc }}" alt="{{ $post->title }}" loading="lazy">
                            @else
                            <div class="blog-card__img-placeholder"></div>
                            @endif
                        </a>
                        @if($catName)
                        <div class="blog-card__category">{{ $catName }}</div>
                        @endif
                        <h2 class="blog-card__title">
                            <a href="{{ $postUrl }}">{{ \Str::limit($post->title, 65) }}</a>
                        </h2>
                        @if($post->excerpt)
                        <p class="blog-card__excerpt">{{ strip_tags($post->excerpt) }}</p>
                        @endif
                        <div class="d-flex align-items-center justify-content-between mt-auto">
                            <a href="{{ $postUrl }}" class="blog-card__read-more">
                                {{ $locale === 'vi' ? 'Đọc thêm' : 'Read more' }}
                                <svg width="11" height="11" viewBox="0 0 12 12" fill="none"><path d="M1 11L11 1M11 1H1M11 1V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                            @if($post->published_at)
                            <div class="blog-card__date">{{ \Carbon\Carbon::parse($post->published_at)->translatedFormat('d M, Y') }}</div>
                            @endif
                        </div>
                    </article>
                </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($posts->hasPages())
            <div class="d-flex justify-content-center mt-5">
                {{ $posts->links() }}
            </div>
            @endif
        </section>
        @else
        <p class="author-no-posts">
            {{ $locale === 'vi' ? 'Chưa có bài viết nào.' : 'No articles yet.' }}
        </p>
        @endif

    </div>
</div>

<style>
.author-page-wrap {
    padding-top: 3rem;
    padding-bottom: 5rem;
}

.author-profile-card {
    display: flex;
    gap: 2.5rem;
    align-items: flex-start;
    padding: 2.5rem 0 3rem;
    border-bottom: 1px solid var(--color-border);
    margin-bottom: 3rem;
}

@media (max-width: 767px) {
    .author-profile-card { flex-direction: column; gap: 1.5rem; }
}

.author-profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    border: 3px solid var(--color-border);
}

.author-profile-avatar--placeholder {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: var(--color-accent);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: 800;
    flex-shrink: 0;
}

.author-profile-name {
    font-size: clamp(1.5rem, 3vw, 2rem);
    font-weight: 800;
    color: var(--color-black);
    letter-spacing: -0.02em;
    margin-bottom: 0.25rem;
}

.author-profile-title {
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--color-accent);
    margin-bottom: 0.75rem;
}

.author-profile-bio {
    font-size: 0.95rem;
    color: var(--color-mid);
    line-height: 1.7;
    max-width: 640px;
    margin-bottom: 1rem;
}

.author-profile-expertise {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-bottom: 1rem;
}

.author-expertise-tag {
    padding: 0.2rem 0.65rem;
    background: var(--color-off-white);
    border: 1px solid var(--color-border);
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--color-charcoal);
    letter-spacing: 0.04em;
}

.author-profile-socials {
    display: flex;
    gap: 0.5rem;
}

.author-social-btn {
    width: 34px;
    height: 34px;
    border: 1px solid var(--color-border);
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--color-mid);
    font-size: 0.85rem;
    text-decoration: none;
    transition: background 0.2s, color 0.2s, border-color 0.2s;
}
.author-social-btn:hover {
    background: var(--color-accent);
    border-color: var(--color-accent);
    color: #fff;
}

.author-posts-heading {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--color-black);
    margin-bottom: 1.75rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.author-posts-count {
    font-size: 0.7rem;
    font-weight: 700;
    background: var(--color-accent);
    color: #fff;
    border-radius: 50px;
    padding: 0.1rem 0.5rem;
    letter-spacing: 0.03em;
}

.author-no-posts {
    color: var(--color-mid);
    font-size: 0.95rem;
    margin-top: 2rem;
}
</style>
@endsection
