<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <x-seo.head
        :seo-meta="$seoMeta ?? null"
        :current-url="url()->current()"
        :alternate-urls="$alternateUrls ?? []"
        :fallback-title="$fallbackTitle ?? config('app.name')"
        :fallback-description="$fallbackDescription ?? ''"
    />
    <x-seo.jsonld :schemas="$jsonldSchemas ?? []" />
</head>
<body>
    <x-ui.locale-switcher :alternate-urls="$alternateUrls ?? []" />
    @yield('content')
</body>
</html>
