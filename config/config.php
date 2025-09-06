<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Table Name
    |--------------------------------------------------------------------------
    |
    | Table name in database
    */

    "tables" => [
        'slug' => 'slugs',
        'url' => 'urls',
    ],

    /*
    |--------------------------------------------------------------------------
    | Url long
    |--------------------------------------------------------------------------
    |
    | Url long for url
    */

    "url_long" => env("URL_LONG", 768),

    /*
    |--------------------------------------------------------------------------
    | Register Fallback
    |--------------------------------------------------------------------------
    |
    | Determine if the package should register its own fallback route.
    | Set this to false if you want to manage fallbacks manually.
    */

    "register_fallback" => env("URL_REGISTER_FALLBACK", true),

    /*
    |--------------------------------------------------------------------------
    | Fallback Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware stack applied to the fallback route.
    | By default it uses the "web" middleware group.
    */

    "fallback_middleware" => [
        'web',
    ],

];
