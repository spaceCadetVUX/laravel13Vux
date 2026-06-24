<?php

if (! function_exists('current_locale')) {
    function current_locale(): string
    {
        return app()->getLocale();
    }
}

if (! function_exists('switch_locale_url')) {
    /**
     * Generate the equivalent URL for the given locale on the current page.
     *
     * Priority:
     *   1. $alternateUrls shared by the controller (dynamic pages — blog, product, category)
     *   2. Swap locale prefix in the current route name (static pages)
     *   3. Fallback to locale home
     */
    function switch_locale_url(string $locale): string
    {
        $alternateUrls = view()->shared('alternateUrls');
        if (is_array($alternateUrls) && isset($alternateUrls[$locale])) {
            return $alternateUrls[$locale];
        }

        $route = request()->route();
        if (! $route || ! $route->getName()) {
            return route($locale . '.index');
        }

        $currentName = $route->getName();
        $baseName    = preg_replace('/^(vi|en)\./', '', $currentName);

        try {
            // Pass all current route params + override locale so slug/id params are preserved
            $params = array_merge($route->parameters(), ['locale' => $locale]);
            return route($locale . '.' . $baseName, $params);
        } catch (\Throwable) {
            return route($locale . '.index');
        }
    }
}

if (! function_exists('route_locale')) {
    function route_locale(string $name, string $locale, array $params = []): string
    {
        return route($name, array_merge(['locale' => $locale], $params));
    }
}

if (! function_exists('supported_locales')) {
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

if (! function_exists('format_price')) {
    function format_price(float|int|null $amount, ?string $currency = null): ?string
    {
        if ($amount === null || $amount <= 0) return null;

        $currency = strtoupper($currency ?? 'VND');

        return match($currency) {
            'USD'  => '$' . number_format($amount, 0, '.', ','),
            'EUR'  => '€' . number_format($amount, 0, '.', ','),
            'JPY', 'KRW', 'CNY' => '¥' . number_format($amount, 0, '.', ','),
            'SGD'  => 'S$' . number_format($amount, 0, '.', ','),
            'THB'  => '฿' . number_format($amount, 0, '.', ','),
            default => number_format($amount, 0, ',', '.') . 'đ', // VND
        };
    }
}
