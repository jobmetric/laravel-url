<?php

namespace JobMetric\Url\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;
use JobMetric\Url\Models\Url;

/**
 * Validation rule to ensure a URL (slug) is unique per model type,
 * excluding an optional current record (for update flows).
 */
class UrlExistRule implements Rule
{
    /**
     * The fully-qualified class name of the related model (urlable type).
     *
     * @var string
     */
    private string $class_name;

    /**
     * The ID of the current model instance to exclude (useful in updates).
     *
     * @var int|null
     */
    private ?int $object_id;

    /**
     * @param string $class_name Related model class (urlable_type)
     * @param int|null $object_id Optional model ID to exclude
     */
    public function __construct(string $class_name, ?int $object_id = null)
    {
        $this->class_name = $class_name;
        $this->object_id = $object_id;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        // Let other rules (required|string) handle empties.
        if ($value === null || $value === '') {
            return true;
        }

        $slug = is_string($value) ? trim($value) : (string)$value;

        /** @var Builder $query */
        $query = Url::query()
            ->where('urlable_type', $this->class_name)
            ->where('url', $slug)
            ->when($this->object_id, function (Builder $q) {
                $q->where('urlable_id', '!=', $this->object_id);
            });

        return !$query->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return trans('url::base.rule.exist');
    }
}
