<?php

if (! function_exists('route_locale')) {
    /**
     * Generate a URL for a named route with a specific locale prefix.
     *
     * Usage in Blade:
     *   {{ route_locale('product.show', 'en', ['slug' => $enSlug]) }}
     */
    function route_locale(string $name, string $locale, array $params = []): string
    {
        return route($name, array_merge(['locale' => $locale], $params));
    }
}

if (! function_exists('supported_locales')) {
    /**
     * Return the array of supported locales from config.
     */
    function supported_locales(): array
    {
        return config('app.supported_locales', ['vi', 'en']);
    }
}

if (! function_exists('is_supported_locale')) {
    function is_supported_locale(string $locale): bool
    {
        return in_array($locale, config('app.supported_locales', ['vi', 'en']), true);
    }
}
