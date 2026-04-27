<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($indexes as $index)
    <sitemap>
        <loc>{{ $index->url }}</loc>
@if($index->last_generated_at)
        <lastmod>{{ $index->last_generated_at->toAtomString() }}</lastmod>
@endif
    </sitemap>
@endforeach
</sitemapindex>
