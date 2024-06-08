<?php

namespace JobMetric\Url\Events;

use JobMetric\Url\Models\Url;

class UrlableResourceEvent
{
    /**
     * The urlable model instance.
     *
     * @var mixed
     */
    public mixed $urlable;

    /**
     * The resource to be filled by the listener.
     *
     * @var mixed|null
     */
    public mixed $resource;

    /**
     * Create a new event instance.
     *
     * @param mixed $urlable
     */
    public function __construct(mixed $urlable)
    {
        $this->urlable = $urlable;
        $this->resource = null;
    }
}
