<?php

namespace JobMetric\Url\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \JobMetric\Url\Url
 */
class Url extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'Url';
    }
}
