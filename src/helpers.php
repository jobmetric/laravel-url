<?php

use Illuminate\Database\Eloquent\Model;
use JobMetric\Url\Facades\Url;

if (!function_exists('getUrl')) {
    /**
     * Get the url
     *
     * @param Model $urlable
     * @param string|null $collection
     *
     * @return array
     * @throws Throwable
     */
    function getUrl(Model $urlable, string $collection = null): array
    {
        return Url::get($urlable, $collection);
    }
}

if (!function_exists('getByUrl')) {
    /**
     * Get the url by url
     *
     * @param string $url
     *
     * @return array
     * @throws Throwable
     */
    function getByUrl(string $url): array
    {
        return Url::getByUrl($url);
    }
}

if (!function_exists('dispatchUrl')) {
    /**
     * Dispatch the url
     *
     * @param Model $urlable
     * @param string $url
     * @param string|null $collection
     *
     * @return array
     * @throws Throwable
     */
    function dispatchUrl(Model $urlable, string $url, string $collection = null): array
    {
        return Url::dispatch($urlable, $url, $collection);
    }
}

if (!function_exists('forgetUrl')) {
    /**
     * Forget the url
     *
     * @param Model $urlable
     * @param string|null $collection
     *
     * @return array
     * @throws Throwable
     */
    function forgetUrl(Model $urlable, string $collection = null): array
    {
        return Url::forget($urlable, $collection);
    }
}

if (!function_exists('forgetByUrl')) {
    /**
     * Forget the url by url
     *
     * @param string $url
     *
     * @return array
     * @throws Throwable
     */
    function forgetByUrl(string $url): array
    {
        return Url::forgetByUrl($url);
    }
}

if (!function_exists('forgetByModel')) {
    /**
     * Forget the url by model
     *
     * @param Model $urlable
     *
     * @return array
     * @throws Throwable
     */
    function forgetByModel(Model $urlable): array
    {
        return Url::forgetByModel($urlable);
    }
}
