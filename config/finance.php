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
    | rows keep the base currency they were converted into.
    |
    */

    'base_currency' => env('FINANCE_BASE_CURRENCY', 'IDR'),

];
