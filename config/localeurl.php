<?php

/**
 * Locale-aware URL prefix map for all public model types.
 *
 * vi = default locale — no /vi/ prefix in URL (Vietnamese-first site).
 * en = /en/ prefix.
 *
 * Used by:
 *   - App\Support\LocaleUrl (canonical generation + hreflang)
 *   - App\Services\Seo\JsonldService (JSON-LD url / @id / breadcrumb)
 *   - Filament resource canonical auto-fill
 */
return [

    'supported_locales' => ['vi', 'en'],

    'default_locale' => 'vi',

    'prefixes' => [
        'vi' => [
            'brand'         => '/thuong-hieu/',
            'manufacturer'  => '/nha-san-xuat/',
            'product'       => '/san-pham/',
            'category'      => '/danh-muc/',
            'blog_post'     => '/bai-viet/',
            'blog_category' => '/chu-de/',
        ],
        'en' => [
            'brand'         => '/en/brands/',
            'manufacturer'  => '/en/manufacturers/',
            'product'       => '/en/products/',
            'category'      => '/en/categories/',
            'blog_post'     => '/en/blog/',
            'blog_category' => '/en/blog/category/',
        ],
    ],

    'list_labels' => [
        'vi' => [
            'brand'         => 'Thương hiệu',
            'manufacturer'  => 'Nhà sản xuất',
            'product'       => 'Sản phẩm',
            'category'      => 'Danh mục',
            'blog_post'     => 'Bài viết',
            'blog_category' => 'Chủ đề',
        ],
        'en' => [
            'brand'         => 'Brands',
            'manufacturer'  => 'Manufacturers',
            'product'       => 'Products',
            'category'      => 'Categories',
            'blog_post'     => 'Blog',
            'blog_category' => 'Blog Categories',
        ],
    ],

];
