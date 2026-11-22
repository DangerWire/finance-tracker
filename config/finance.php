<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base Currency
    |--------------------------------------------------------------------------
    |
    | The currency that all totals are converted into and reported in. The
    | original amount on a transaction is always preserved, so changing this
    | value only affects transactions created from that point onward; existing
    | rows keep the base currency they were converted into and are excluded
    | from totals until they are reconverted with finance:backfill-base-amounts.
    |
    */

    'base_currency' => env('FINANCE_BASE_CURRENCY', 'CNY'),

    /*
    |--------------------------------------------------------------------------
    | Supported Main Currencies
    |--------------------------------------------------------------------------
    |
    | Supported user-selectable primary currencies with their display names
    | and symbols.
    |
    */

    'supported_currencies' => [
        'IDR' => ['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp'],
        'USD' => ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$'],
        'EUR' => ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
        'CNY' => ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥'],
        'JPY' => ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥'],
        'GBP' => ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£'],
        'SGD' => ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$'],
        'AUD' => ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$'],
        'MYR' => ['code' => 'MYR', 'name' => 'Malaysian Ringgit', 'symbol' => 'RM'],
    ],

];
