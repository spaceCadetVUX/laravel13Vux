<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Site Identity
    |--------------------------------------------------------------------------
    */
    'app_name' => env('APP_NAME', 'YourShop'),
    'app_url'  => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Default OG Image
    | Served as fallback when a model has no og_image set.
    |--------------------------------------------------------------------------
    */
    'default_og_image' => env('APP_URL', 'http://localhost') . '/og-default.jpg',

    /*
    |--------------------------------------------------------------------------
    | Twitter / X
    |--------------------------------------------------------------------------
    */
    'twitter_handle' => env('TWITTER_HANDLE', ''),

    /*
    |--------------------------------------------------------------------------
    | Logo URL (used in Organization JSON-LD schema)
    |--------------------------------------------------------------------------
    */
    'logo_url' => env('APP_URL', 'http://localhost') . '/logo.png',

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    | Used as fallback in JSON-LD priceCurrency when product.currency is empty.
    |--------------------------------------------------------------------------
    */
    'currency' => env('DEFAULT_CURRENCY', 'VND'),

];
