<?php

namespace JobMetric\Url\Http\Requests;

use JobMetric\Url\Rules\UrlCollectionExistRule;

trait UrlTypeObjectRequest
{
    public function renderUrlFiled(
        array    &$rules,
        string   $class_name,
        string   $collection,
        string   $field_name = 'slug',
        int|null $object_id = null,
    ): void
    {
        $rules[$field_name] = [
            'string',
            'nullable',
            'sometimes',
            new UrlCollectionExistRule($class_name, $collection, $object_id),
        ];
    }
}
