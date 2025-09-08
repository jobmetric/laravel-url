<?php
declare(strict_types=1);

namespace JobMetric\Url\Http\Requests;

use JobMetric\Url\Rules\SlugExistRule;

/**
 * Trait UrlTypeObjectRequest
 *
 * Adds URL (slug) validation rules and human-readable attribute labels
 * to FormRequest classes when the target type supports URLs.
 *
 * Responsibilities:
 * - Conditionally append a "slug" validation rule using SlugExistRule for
 *   per-model-instance uniqueness (with optional exclusion on update).
 * - Keep rule set aligned with DB constraints (e.g., max length 100).
 * - Provide a translated attribute label for "slug" to improve error messages.
 */
trait UrlTypeObjectRequest
{
    /**
     * Append the "slug" validation rule to the provided rules array when enabled.
     * It preserves any existing slug rules and appends:
     *   - sometimes | nullable | string | max:100
     *   - SlugExistRule (unique among active rows, scoped by type/collection, excluding object_id)
     *
     * @param array<string, mixed> $rules Rules array passed by reference to be augmented.
     * @param bool $hasUrl Whether the current type supports URL/slug.
     * @param string $class_name Fully-qualified class name used as slugable_type.
     * @param string|null $collection Optional collection scope (null = default).
     * @param int|null $object_id Optional model ID to exclude (useful on update).
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

        // Preserve existing rules (if any) and append ours.
        $base = $rules['slug'] ?? [];

        // Normalize to array
        if (!is_array($base)) {
            $base = [$base];
        }

        $append = [
            'sometimes',
            'nullable',
            'string',
            'max:100',
            new SlugExistRule($class_name, $collection, $object_id),
        ];

        $rules['slug'] = array_values(array_merge($base, $append));
    }

    /**
     * Append a translated attribute label for "slug" to the given attributes array when enabled.
     *
     * @param array<string, string> $params Attributes array passed by reference to be augmented.
     * @param bool $hasUrl Whether the current type supports URL/slug.
     *
     * @return void
     */
    public function renderUrlAttribute(array &$params, bool $hasUrl): void
    {
        if (!$hasUrl) {
            return;
        }

        $params['slug'] = trans('url::base.components.url_slug.title');
    }
}
