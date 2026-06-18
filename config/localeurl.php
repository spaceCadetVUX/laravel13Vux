<?php

/**
 * Locale-aware URL prefix map for all public model types.
 *
 * Both vi and en use explicit locale prefix (subdirectory strategy).
 * Recommended by Google for multilingual SEO — clear locale signal in URL,
 * clean hreflang, no duplicate-content ambiguity.
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
            'brand'         => '/vi/thuong-hieu/',
            'manufacturer'  => '/vi/nha-san-xuat/',
            'product'       => '/vi/san-pham/',
            'category'      => '/vi/danh-muc/',
            'blog_post'     => '/vi/bai-viet/',
            'blog_category' => '/vi/blog/',
        ],
        'en' => [
            'brand'         => '/en/brands/',
            'manufacturer'  => '/en/manufacturers/',
            'product'       => '/en/products/',
            'category'      => '/en/categories/',
            'blog_post'     => '/en/blog/',
            'blog_category' => '/en/blog/',
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
