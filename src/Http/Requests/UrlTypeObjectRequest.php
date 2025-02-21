<?php

namespace JobMetric\Url\Http\Requests;

use JobMetric\Url\Rules\UrlCollectionExistRule;

trait UrlTypeObjectRequest
{
    public function renderUrlFiled(
        array       &$rules,
        bool        $hasUrl,
        string      $class_name,
        string|null $collection = null,
        int|null    $object_id = null,
    ): void
    {
        if ($hasUrl) {
            $rules['slug'] = [
                'string',
                'nullable',
                'sometimes',
                new UrlCollectionExistRule($class_name, $collection, $object_id),
            ];
        }
    }

    public function renderUrlAttribute(
        array &$params,
        bool  $hasUrl,
    ): void
    {
        if ($hasUrl) {
            $params['slug'] = trans('url::base.components.url_slug.title');
        }
    }
}
