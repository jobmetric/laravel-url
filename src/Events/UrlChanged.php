<?php

namespace JobMetric\Url\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Event fired when a model's active full URL is created or changes (versioned).
 *
 * - $old is null when the first URL (version=1) is created.
 * - $new is the newly-activated full URL.
 * - $version is the version number of the newly-activated URL.
 */
class UrlChanged
{
    /**
     * @var Model The urlable model whose URL has changed.
     */
    public Model $model;

    /**
     * @var string|null Previous active URL (if any).
     */
    public ?string $old;

    /**
     * @var string Newly activated URL.
     */
    public string $new;

    /**
     * @var int Version of the newly activated URL.
     */
    public int $version;

    public function __construct(Model $model, ?string $old, string $new, int $version)
    {
        $this->model   = $model;
        $this->old     = $old;
        $this->new     = $new;
        $this->version = $version;
    }
}
