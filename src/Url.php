<?php

namespace JobMetric\Url;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use JobMetric\Url\Exceptions\UrlTooLongException;
use JobMetric\Url\Http\Resources\UrlResource;
use JobMetric\Url\Models\Url as UrlModel;
use Throwable;

class Url
{
    /**
     * The application instance.
     *
     * @var Application
     */
    protected Application $app;

    /**
     * Create a new country instance.
     *
     * @param Application $app
     *
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * get the url
     *
     * @param Model $urlable
     * @param string|null $collection
     *
     * @return array
     * @throws Throwable
     */
    public function get(Model $urlable, string $collection = null): array
    {
        /**
         * @var UrlModel $url_model
         */
        $url_model = UrlModel::query()->where([
            'urlable_type' => $urlable->getMorphClass(),
            'urlable_id' => $urlable->getKey(),
            'collection' => $collection
        ])->first();

        if ($url_model) {
            return [
                'ok' => true,
                'message' => trans('url::base.messages.found'),
                'data' => UrlResource::make($url_model),
                'status' => 200
            ];
        } else {
            return [
                'ok' => false,
                'message' => trans('url::base.validation.not_found'),
                'status' => 404
            ];
        }
    }

    /**
     * get the url
     *
     * @param string $url
     *
     * @return array
     * @throws Throwable
     */
    public function getByUrl(string $url): array
    {
        /**
         * @var UrlModel $url_model
         */
        $url_model = UrlModel::query()->where('url', $url)->first();

        if ($url_model) {
            return [
                'ok' => true,
                'message' => trans('url::base.messages.found'),
                'data' => UrlResource::make($url_model),
                'status' => 200
            ];
        } else {
            return [
                'ok' => false,
                'message' => trans('url::base.validation.not_found'),
                'status' => 404
            ];
        }
    }

    /**
     * dispatch url
     *
     * @param Model $urlable
     * @param string $url
     * @param string|null $collection
     *
     * @return array
     * @throws Throwable
     */
    public function dispatch(Model $urlable, string $url, string $collection = null): array
    {
        if (strlen($url) >= config('url.url_long')) {
            throw new UrlTooLongException;
        }

        /**
         * @var UrlModel $url_model
         */
        $url_model = UrlModel::query()->where([
            'urlable_type' => $urlable->getMorphClass(),
            'urlable_id' => $urlable->getKey(),
            'collection' => $collection
        ])->first();

        $mode = $url_model ? 'updated' : 'created';
        $status = $url_model ? 200 : 201;

        if ($url_model) {
            $url_model->url = $url;

            $url_model->save();
        } else {
            $url_model = new UrlModel;

            $url_model->urlable()->associate($urlable);
            $url_model->url = $url;
            $url_model->collection = $collection;

            $url_model->save();
        }

        return [
            'ok' => true,
            'message' => trans('url::base.messages.' . $mode),
            'data' => UrlResource::make($url_model),
            'status' => $status
        ];
    }

    /**
     * forget the url
     *
     * @param Model $urlable
     * @param string|null $collection
     *
     * @return array
     * @throws Throwable
     */
    public function forget(Model $urlable, string $collection = null): array
    {
        /**
         * @var UrlModel $url_model
         */
        $url_model = UrlModel::query()->where([
            'urlable_type' => $urlable->getMorphClass(),
            'urlable_id' => $urlable->getKey(),
            'collection' => $collection
        ])->first();

        if ($url_model) {
            $url_model->delete();

            return [
                'ok' => true,
                'message' => trans('url::base.messages.deleted'),
                'status' => 200
            ];
        } else {
            return [
                'ok' => false,
                'message' => trans('url::base.validation.not_found'),
                'status' => 404
            ];
        }
    }

    /**
     * forget the url
     *
     * @param string $url
     *
     * @return array
     * @throws Throwable
     */
    public function forgetByUrl(string $url): array
    {
        /**
         * @var UrlModel $url_model
         */
        $url_model = UrlModel::query()->where('url', $url)->first();

        if ($url_model) {
            $url_model->delete();

            return [
                'ok' => true,
                'message' => trans('url::base.messages.deleted'),
                'status' => 200
            ];
        } else {
            return [
                'ok' => false,
                'message' => trans('url::base.validation.not_found'),
                'status' => 404
            ];
        }
    }

    /**
     * forget the url
     *
     * @param Model $urlable
     *
     * @return array
     * @throws Throwable
     */
    public function forgetByModel(Model $urlable): array
    {
        /**
         * @var UrlModel $url_model
         */
        $url_model = UrlModel::query()->where([
            'urlable_type' => $urlable->getMorphClass(),
            'urlable_id' => $urlable->getKey()
        ])->first();

        if ($url_model) {
            $url_model->delete();

            return [
                'ok' => true,
                'message' => trans('url::base.messages.deleted'),
                'status' => 200
            ];
        } else {
            return [
                'ok' => false,
                'message' => trans('url::base.validation.not_found'),
                'status' => 404
            ];
        }
    }
}
