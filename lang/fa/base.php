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

    "validation" => [
        "errors" => "خطای اعتبارسنجی رخ داده است.",
        "not_found" => "آدرس اینترنتی یافت نشد.",
    ],

    "messages" => [
        "found" => "آدرس اینترنتی با موفقیت یافت شد.",
        "created" => "آدرس اینترنتی با موفقیت ایجاد شد.",
        "updated" => "آدرس اینترنتی با موفقیت به روز شد.",
        "deleted" => "آدرس اینترنتی با موفقیت حذف شد.",
    ],

    "exceptions" => [
        "not_found" => "آدرس اینترنتی یافت نشد",
        "url_too_long" => "آدرس اینترنتی بیش از حد طولانی است.",
        "model_url_contract_not_found" => "مدل :model باید قرارداد JobMetric\Url\Contracts\UrlContract را پیاده سازی کند.",
    ],

];
