<?php

namespace JobMetric\Url\Http\Requests;

use JobMetric\Url\Rules\UrlCollectionExistRule;

trait UrlTypeObjectRequest
{
    public function renderUrlFiled(
        array    &$rules,
        string   $class_name,
        string   $collection,
        int|null $object_id = null,
    ): void
    {
        $rules['slug'] = [
            'string',
            'nullable',
            'sometimes',
            new UrlCollectionExistRule($class_name, $collection, $object_id),
        ];
    }
}
