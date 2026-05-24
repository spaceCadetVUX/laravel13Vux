<?php

namespace App\Support;

/**
 * Single source of truth for locale-aware public URLs.
 *
 * URL strategy (Option C — Vietnamese-first):
 *   vi (default): /thuong-hieu/philips      ← no locale prefix
 *   en:           /en/brands/philips        ← /en/ prefix
 *
 * Usage:
 *   LocaleUrl::for('brand', 'philips')          → vi canonical
 *   LocaleUrl::for('brand', 'philips', 'en')    → en canonical
 *   LocaleUrl::hreflang('brand', 'philips')     → full hreflang map
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
}
