<?php

namespace JobMetric\Url\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;
use JobMetric\Url\Models\Url;

class UrlCollectionExistRule implements Rule
{
    private string $class_name;
    private string $collection;
    private int|null $object_id;

    public function __construct(string $class_name, string $collection, int|null $object_id = null)
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
        $_url = (new Url)->getTable();

        $query = Url::query();

        $query->where($_url . '.urlable_type', $this->class_name)
            ->where($_url . '.collection', $this->collection)
            ->when($this->object_id, function (Builder $q) use ($_url) {
                $q->where($_url . '.urlable_id', '!=', $this->object_id);
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
        return trans('url::base.rule.exist', ['field' => $this->collection]);
    }
}
