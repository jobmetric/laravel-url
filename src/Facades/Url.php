<?php

namespace JobMetric\Url\Facades;

use Illuminate\Support\Facades\Facade;
use Illuminate\Database\Eloquent\Model;

/**
 * @see \JobMetric\Url\Url
 *
 * @method static array get(Model $urlable, string $collection = null, bool $mode = false)
 * @method static array getByUrl(string $url)
 * @method static array dispatch(Model $urlable, string|null $url, string $collection = null)
 * @method static array forget(Model $urlable, string $collection = null)
 * @method static array forgetByUrl(string $url)
 * @method static array forgetByModel(Model $urlable)
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
        return \JobMetric\Url\Url::class;
    }
}
