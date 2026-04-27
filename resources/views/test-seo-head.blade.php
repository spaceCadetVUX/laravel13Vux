<!DOCTYPE html>
<html lang="vi">
<head>
    @php
        $mockSeo = new \App\Models\Seo\SeoMeta([
            'meta_title'       => 'Sản phẩm test | KNX Store',
            'meta_description' => 'Mô tả sản phẩm test cho SEO',
            'og_title'         => 'Sản phẩm test OG',
            'og_description'   => 'OG description test',
        ]);

        $currentUrl = 'https://knxstore.vn/vi/products/san-pham-test';

        $alternateUrls = [
            'vi' => 'https://knxstore.vn/vi/products/san-pham-test',
            'en' => 'https://knxstore.vn/en/products/test-product',
        ];
    @endphp

    <x-seo-head
        :seo-meta="$mockSeo"
        :current-url="$currentUrl"
        :alternate-urls="$alternateUrls"
        fallback-title="Fallback Title"
        fallback-description="Fallback description"
    />
</head>
<body>
    <h1>SeoHead component test</h1>
    <p>Check page source for title, canonical, hreflang vi/en, x-default, og tags.</p>
    <ul>
        <li>canonical must equal currentUrl (vi URL)</li>
        <li>hreflang vi + hreflang en both present</li>
        <li>x-default points to vi URL</li>
    </ul>
</body>
</html>
