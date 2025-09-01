<?php

namespace JobMetric\Url\Http\Requests;

use JobMetric\Url\Rules\UrlExistRule;

/**
 * Trait UrlTypeObjectRequest
 *
 * Adds URL (slug) validation rules and human-readable attribute labels
 * to FormRequest classes when the target type supports URLs.
 *
 * Role:
 * - Conditionally append a "slug" validation rule using UrlExistRule for
 *   per-model-instance uniqueness (with optional exclusion on update).
 * - Provide a translated attribute label for "slug" to improve error messages.
 */
trait UrlTypeObjectRequest
{
    /**
     * Append the "slug" validation rule to the provided rules array when enabled.
     *
     * @param array<string, mixed> $rules The rules array passed by reference to be augmented.
     * @param bool $hasUrl Whether the current type supports URL/slug.
     * @param string $class_name The fully-qualified class name used as urlable_type.
     * @param string|null $collection Optional media collection name, if applicable.
     * @param int|null $object_id Optional model ID to exclude for update scenarios.
     *
     * @return void
     */
    public function renderUrlField(
        array   &$rules,
        bool    $hasUrl,
        string  $class_name,
        ?string $collection = null,
        ?int    $object_id = null
    ): void
    {
        if (!$hasUrl) {
            return;
        }

        $rules['slug'] = [
            'sometimes',
            'nullable',
            'string',
            new UrlExistRule($class_name, $collection, $object_id),
        ];
    }

    /**
     * Append a translated attribute label for "slug" to the given attributes array when enabled.
     *
     * @param array<string, string> $params The attributes array passed by reference to be augmented.
     * @param bool $hasUrl Whether the current type supports URL/slug.
     *
     * @return void
     */
    public function renderUrlAttribute(
        array &$params,
        bool  $hasUrl
    ): void
    {
        if (!$hasUrl) {
            return;
        }

        $params['slug'] = trans('url::base.components.url_slug.title');
    }
}
