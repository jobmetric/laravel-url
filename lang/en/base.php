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
        "exist" => "Url :field already exists.",
    ],

    "validation" => [
        "errors" => "Validation errors occurred.",
        "not_found" => "The url not found.",
    ],

    "messages" => [
        "found" => "The url was found successfully.",
        "created" => "The url was created successfully.",
        "updated" => "The url was updated successfully.",
        "deleted" => "The url was deleted successfully.",
    ],

    "exceptions" => [
        "not_found" => "Url not found",
        "url_too_long" => "The url is too long.",
    ],

    "fields" => [
        "slug" => [
            "title" => "Slug",
            "placeholder" => "Enter the slug.",
            "description" => "The 'slug' is a version of the name that is appropriate for a web address. It is usually all lowercase and contains only letters, numbers, and hyphens.",
        ],
    ],

];
