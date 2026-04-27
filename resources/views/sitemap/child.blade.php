<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach($entries as $entry)
    <url>
        <loc>{{ $entry->url }}</loc>
        @if($entry->alternate_urls)
            @foreach($entry->alternate_urls as $lang => $altUrl)
                <xhtml:link rel="alternate" hreflang="{{ $lang }}" href="{{ $altUrl }}" />
            @endforeach
            <xhtml:link rel="alternate" hreflang="x-default" href="{{ $entry->alternate_urls['vi'] ?? $entry->url }}" />
        @endif
        <changefreq>{{ $entry->changefreq?->value ?? 'weekly' }}</changefreq>
        <priority>{{ number_format((float) ($entry->priority ?? 0.8), 1) }}</priority>
        <lastmod>{{ $entry->updated_at->toAtomString() }}</lastmod>
    </url>
@endforeach
</urlset>
