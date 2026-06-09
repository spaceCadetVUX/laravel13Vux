{!! '<' . '?xml version="1.0" encoding="UTF-8"?' . '>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach($pages as $page)
    <url>
        <loc>{{ $page['vi'] }}</loc>
        <xhtml:link rel="alternate" hreflang="vi" href="{{ $page['vi'] }}" />
        <xhtml:link rel="alternate" hreflang="en" href="{{ $page['en'] }}" />
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $page['vi'] }}" />
        <changefreq>{{ $page['changefreq'] }}</changefreq>
        <priority>{{ $page['priority'] }}</priority>
    </url>
@endforeach
</urlset>
