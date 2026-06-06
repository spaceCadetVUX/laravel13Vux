@props(['items' => []])
@php
    $hasItems = count($items) > 0;
    if ($hasItems) {
        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn ($item, $i) => array_filter([
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $item['name'],
                'item'     => $item['url'] ?? null,
            ]))->all(),
        ];
    }
@endphp
@if($hasItems)
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endif
