<?php

namespace JobMetric\Url\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;
use JobMetric\Url\Models\Url;

class UrlCollectionExistRule implements Rule
{
    private string $class_name;
    private string|null $collection;
    private int|null $object_id;

    public function __construct(string $class_name, string $collection = null, int|null $object_id = null)
    {
        $this->class_name = $class_name;
        $this->collection = $collection;
        $this->object_id = $object_id;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     *
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        $query = Url::query();

        $query->where('urlable_type', $this->class_name)
            ->where('collection', $this->collection)
            ->where('url', $value)
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
        return trans('url::base.rule.exist', ['field' => trans('url::base.components.url_slug.title')]);
    }
}
