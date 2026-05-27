<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fallback Locale
    |--------------------------------------------------------------------------
    |
    | When no locale is set or an unavailable locale is requested, the app
    | will fall back to this locale.
    |
    */
    'fallback' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Locale Metadata
    |--------------------------------------------------------------------------
    |
    | Human-readable metadata for each locale. If a locale exists in translation
    | files but is missing here, the locale code is used as both name and native
    | name, and rtl defaults to false.
    |
    */
    'metadata' => [
        'en' => [
            'name' => 'English',
            'native' => 'English',
            'rtl' => false,
        ],
        'tr' => [
            'name' => 'Turkish',
            'native' => 'Türkçe',
            'rtl' => false,
        ],
    ],

];
