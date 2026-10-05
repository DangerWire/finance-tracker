<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenRouter API Key
    |--------------------------------------------------------------------------
    |
    | Your OpenRouter key, used through Prism. The key stays in the
    | environment and is never committed, so insights are unavailable until
    | it is set rather than failing at runtime.
    |
    */

    'api_key' => env('OPENROUTER_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Model
    |--------------------------------------------------------------------------
    |
    | A small, inexpensive model is sufficient because the model only
    | describes figures the database has already calculated. Point this at a
    | different OpenRouter model to trade cost against phrasing quality.
    |
    */

    'model' => env('OPENROUTER_MODEL', 'openai/gpt-4o-mini'),

    /*
    |--------------------------------------------------------------------------
    | Minimum Transactions
    |--------------------------------------------------------------------------
    |
    | Insights below this many recorded expenses in the current month are not
    | worth generating; the statistics would be too thin to say anything
    | meaningful.
    |
    */

    'minimum_transactions' => env('INSIGHTS_MINIMUM_TRANSACTIONS', 8),

];
