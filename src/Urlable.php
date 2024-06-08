<?php

namespace JobMetric\Url;

use Illuminate\Database\Eloquent\Model;
use JobMetric\Url\Facades\Url as UrlFacade;
use JobMetric\Url\Http\Resources\UrlResource;

/**
 * Trait Urlable
 *
 * @package JobMetric\Url
 *
 * @property string url
 * @property UrlResource url_resource
 * @property string url_collection
 */
trait Urlable
{
    /**
     * Get the URL for the model.
     *
     * @return array
     */
    public function url(): array
    {
        /** @var Model $this */
        return UrlFacade::get($this);
    }

    /**
     * Get the URL by collection for the model.
     *
     * @param string|null $collection
     *
     * @return array
     */
    public function urlByCollection(string $collection = null): array
    {
        /** @var Model $this */
        return UrlFacade::get($this, $collection);
    }

    /**
     * Get the URL for the model.
     *
     * @return string|null
     */
    public function getUrlAttribute(): ?string
    {
        $url = $this->url();

        if ($url['ok']) {
            return $url['data']->url;
        } else {
            return null;
        }
    }

    /**
     * Get the URL for the model.
     *
     * @return string|null
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Get the URL resource for the model.
     *
     * @return UrlResource|null
     */
    public function getUrlResourceAttribute(): ?UrlResource
    {
        $url = $this->url();

        if ($url['ok']) {
            return $url['data'];
        } else {
            return null;
        }
    }

    /**
     * Get the URL collection for the model.
     *
     * @return string|null
     */
    public function getUrlCollectionAttribute(): ?string
    {
        $url = $this->url();

        if ($url['ok']) {
            return $url['data']->collection;
        } else {
            return null;
        }
    }

    /**
     * find the model by URL
     *
     * @param string $url
     *
     * @return Model|null
     */
    public static function findByUrl(string $url): ?Model
    {
        $url = UrlFacade::getByUrl($url);

        if ($url['ok']) {
            return $url['data']->urlable;
        } else {
            return null;
        }
    }

    /**
     * find the model by URL or fail
     *
     * @param string $url
     *
     * @return Model|null
     */
    public static function findByUrlOrFail(string $url): ?Model
    {
        $model = static::findByUrl($url);

        if ($model) {
            return $model;
        } else {
            abort(404);
        }
    }

    /**
     * find the model by URL and collection
     *
     * @param string $url
     * @param string $collection
     *
     * @return Model|null
     */
    public static function findByUrlAndCollection(string $url, string $collection): ?Model
    {
        $url = UrlFacade::getByUrl($url);

        if ($url['ok'] && $url['data']->collection == $collection) {
            return $url['data']->urlable;
        } else {
            return null;
        }
    }

    /**
     * find the model by URL and collection or fail
     *
     * @param string $url
     * @param string $collection
     *
     * @return Model|null
     */
    public static function findByUrlAndCollectionOrFail(string $url, string $collection): ?Model
    {
        $model = static::findByUrlAndCollection($url, $collection);

        if ($model) {
            return $model;
        } else {
            abort(404);
        }
    }

    /**
     * dispatch the URL for the model
     *
     * @param string $url
     * @param string|null $collection
     *
     * @return array
     */
    public function dispatchUrl(string $url, string $collection = null): array
    {
        /** @var Model $this */
        return UrlFacade::dispatch($this, $url, $collection);
    }

    /**
     * forget the URL for the model
     *
     * @param string|null $collection
     *
     * @return array
     */
    public function forgetUrl(string $collection = null): array
    {
        /** @var Model $this */
        return UrlFacade::forget($this, $collection);
    }
}
