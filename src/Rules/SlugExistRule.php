<?php
declare(strict_types=1);

namespace JobMetric\Url\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use JobMetric\Url\Models\Slug;

/**
 * Class SlugExistRule
 *
 * Ensures a slug is unique per model type and (optional) collection,
 * excluding an optional current record (for update flows).
 * Only active (non-deleted) slugs are considered.
 */
final class SlugExistRule implements ValidationRule
{
    /**
     * Fully-qualified class name of the related model (slugable type).
     */
    private string $className;

    /**
     * Optional collection name; empty string is treated as null.
     */
    private ?string $collection;

    /**
     * Current model ID to exclude from uniqueness check (useful on updates).
     */
    private ?int $objectId;

    /**
     * @param string $className Related model class (slugable_type).
     * @param string|null $collection Optional collection scope (null for default).
     * @param int|null $objectId Optional model ID to exclude.
     */
    public function __construct(string $className, ?string $collection = null, ?int $objectId = null)
    {
        $this->className = $className;
        $this->collection = ($collection === '') ? null : $collection;
        $this->objectId = $objectId;
    }

    /**
     * Validate the attribute.
     *
     * - Normalizes the input slug exactly like HasUrl::normalizeSlugPair:
     *   slugify + trim + limit to 100 chars.
     * - Checks uniqueness among active (non-deleted) records only.
     *
     * @param string $attribute
     * @param mixed $value
     * @param Closure $fail
     *
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Let other rules (required|string) handle empties.
        if ($value === null || $value === '') {
            return;
        }

        // Normalize slug exactly as in HasUrl
        $raw = is_string($value) ? trim($value) : (string)$value;
        $slug = Str::limit(Str::slug($raw), 100, '');

        $query = Slug::query()
            ->where('slugable_type', $this->className)
            ->where('slug', $slug)
            ->whereNull('deleted_at'); // consider only active slugs

        if ($this->collection === null) {
            $query->whereNull('collection');
        } else {
            $query->where('collection', $this->collection);
        }

        if (!is_null($this->objectId)) {
            $query->where('slugable_id', '!=', $this->objectId);
        }

        if ($query->exists()) {
            $fail(trans('url::base.rule.exist'));
        }
    }
}
