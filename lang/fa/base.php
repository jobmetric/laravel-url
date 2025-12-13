<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base url Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during Url for
    | various messages that we need to display to the user.
    |
    */

    "rule" => [
        "exist" => "آدرس از قبل وجود دارد.",
    ],

    "exceptions" => [
        "not_found" => "آدرس اینترنتی یافت نشد",
        "slug_not_found" => "نامک یافت نشد.",
        "model_url_contract_not_found" => "مدل :model باید قرارداد JobMetric\Url\Contracts\UrlContract را پیاده سازی کند.",
        "slug_conflict" => "این نامک هم‌اکنون توسط رکورد دیگری استفاده می‌شود.",
        "url_conflict" => "این آدرس اینترنتی فعال توسط رکورد دیگری استفاده می‌شود.",
    ],

    "entity_names" => [
        "url" => "آدرس اینترنتی",
    ],

    'events' => [
        'url_changed' => [
            'title' => 'تغییر آدرس',
            'description' => 'هنگامی که یک آدرس تغییر می‌کند، این رویداد فعال می‌شود.',
        ],
    ],

];
