<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ATU Shipping Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for ATU Shipping package.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Origin Country
    |--------------------------------------------------------------------------
    |
    | Default origin country code (ISO 3166-1 alpha-2) if not specified.
    |
    */
    'default_origin_country' => env('ATU_SHIPPING_DEFAULT_ORIGIN_COUNTRY', 'ZA'),

    /*
    |--------------------------------------------------------------------------
    | Base Currency
    |--------------------------------------------------------------------------
    |
    | Base currency code for shipping calculations. Falls back to A2 Commerce
    | currency if not set.
    |
    */
    'base_currency' => env('ATU_SHIPPING_BASE_CURRENCY', config('a2_commerce.currency', 'USD')),

    /*
    |--------------------------------------------------------------------------
    | Enable Logging
    |--------------------------------------------------------------------------
    |
    | Whether to log shipping selections. Logging happens at checkout or
    | when manually triggered.
    |
    */
    'enable_logging' => env('ATU_SHIPPING_ENABLE_LOGGING', true),
];
