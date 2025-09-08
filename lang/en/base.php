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
        "exist" => "Url already exists.",
    ],

    "exceptions" => [
        "not_found" => "Url not found",
        "slug_not_found" => "The slug not found.",
        "model_url_contract_not_found" => "The :model model must implement the JobMetric\Url\Contracts\UrlContract contract.",
        "slug_conflict" => "The slug is already in use by another record.",
        "url_conflict" => "This active URL is already used by another record.",
    ],

];
