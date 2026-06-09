<?php

namespace App\Support;

/**
 * Single source of truth for locale-aware public URLs.
 *
 * URL strategy (Subdirectory — Google-recommended for multilingual SEO):
 *   vi: /vi/thuong-hieu/philips   ← /vi/ prefix for all locales
 *   en: /en/brands/philips        ← /en/ prefix for all locales
 *
 * Usage:
 *   LocaleUrl::for('brand', 'philips', 'vi')    → vi canonical
 *   LocaleUrl::for('brand', 'philips', 'en')    → en canonical
 *   LocaleUrl::hreflang('brand', 'philips')     → full hreflang map
 *   LocaleUrl::listUrl('brand', 'vi')           → /vi/thuong-hieu (no trailing slash)
 */
class LocaleUrl
{
    /**
     * Build the absolute canonical URL for a model.
     */
    public static function for(string $morphAlias, string $slug, ?string $locale = null): string
    {
        $locale  ??= app()->getLocale();
        $baseUrl   = rtrim((string) (config('seo.app_url') ?: config('app.url')), '/');
        $prefix    = config("localeurl.prefixes.{$locale}.{$morphAlias}", "/{$morphAlias}s/");

        return $baseUrl . $prefix . $slug;
    }

    /**
     * Build the hreflang map for a model — for use in <link rel="alternate"> and API.
     *
     * Returns:
     *   ['vi' => '...', 'en' => '...', 'x-default' => '...(vi)']
     */
    public static function hreflang(string $morphAlias, string $slug): array
    {
        $locales       = config('localeurl.supported_locales', ['vi', 'en']);
        $defaultLocale = config('localeurl.default_locale', 'vi');

        $map = [];
        foreach ($locales as $locale) {
            $map[$locale] = self::for($morphAlias, $slug, $locale);
        }
        $map['x-default'] = $map[$defaultLocale];

        return $map;
    }

    /**
     * Return just the base list URL (no slug) for a morph alias + locale.
     * Used for breadcrumb list items.
     */
    public static function listUrl(string $morphAlias, ?string $locale = null): string
    {
        $locale  ??= app()->getLocale();
        $baseUrl   = rtrim((string) (config('seo.app_url') ?: config('app.url')), '/');
        $prefix    = config("localeurl.prefixes.{$locale}.{$morphAlias}", "/{$morphAlias}s/");

        return $baseUrl . rtrim($prefix, '/');
    }

    /**
     * Return the human-readable list label for a morph alias + locale.
     * Used for breadcrumb list item names.
     */
    public static function listLabel(string $morphAlias, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        return (string) config("localeurl.list_labels.{$locale}.{$morphAlias}", $morphAlias);
    }

    /**
     * Build the canonical URL for a blog post using the nested category structure.
     *
     * URL structure:
     *   VI: /vi/chu-de/{category_slug}/{post_slug}
     *   EN: /en/blog/{category_slug}/{post_slug}
     *
     * Falls back to the flat `blog_post` prefix when the post has no category.
     */
    public static function forBlogPost(\App\Models\BlogPost $post, string $locale): string
    {
        $post->loadMissing(['translations', 'blogCategory.translations']);

        $translation = $post->translations->where('locale', $locale)->first();
        if (! $translation) {
            return '';
        }

        $blogCategory = $post->blogCategory;
        $catTrans     = $blogCategory?->translations->where('locale', $locale)->first();
        $catSlug      = $catTrans?->slug ?? $blogCategory?->slug;

        $baseUrl = rtrim((string) (config('seo.app_url') ?: config('app.url')), '/');

        if ($catSlug) {
            // /vi/bai-viet/{cat}/{post} or /en/blog/{cat}/{post}
            $nestedBase = match ($locale) {
                'vi'    => '/vi/bai-viet/',
                'en'    => '/en/blog/',
                default => '/vi/bai-viet/',
            };
            return $baseUrl . $nestedBase . $catSlug . '/' . $translation->slug;
        }

        // Fallback: flat URL — post has no category (should not happen for published posts)
        return static::for('blog_post', $translation->slug, $locale);
    }
}
