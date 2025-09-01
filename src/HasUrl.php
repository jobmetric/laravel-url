<?php

namespace JobMetric\Url;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use JobMetric\Url\Exceptions\UrlNotFoundException;
use JobMetric\Url\Http\Resources\UrlResource;
use JobMetric\Url\Models\Url;
use Throwable;

/**
 * Trait HasUrl
 *
 * Provides a single URL (slug) for an Eloquent model using a polymorphic one-to-one relation.
 * The trait captures incoming "slug" and optional "url_collection" (or falls back to the model's "type")
 * during saving, normalizes them, and persists to the Url table after the model is saved.
 *
 * Database invariants (recommended):
 * - Add a unique index on (urlable_type, urlable_id) to enforce one Url row per record.
 * - Add a unique index on (urlable_type, collection, url) to enforce uniqueness of slugs within a type+collection scope.
 *
 * @property-read string|null $url Exposes the resolved slug of the current model (from the default collection).
 * @property-read UrlResource|null $url_resource Exposes the Url resource wrapper for the current model.
 * @property-read string|null $url_collection Exposes the resolved collection for the current model (if any).
 */
trait HasUrl
{
    /**
     * Holds a pending URL payload extracted from attributes during the saving event.
     * Role: Temporarily buffers normalized slug/collection so they can be persisted after the model is saved.
     *
     * @var array{slug: string|null, collection: string|null}
     */
    private array $innerUrl = ['slug' => null, 'collection' => null];

    /**
     * Initializes fillable attributes for URL handling.
     * Role: Make "slug" and "url_collection" mass-assignable for convenient input binding.
     *
     * @return void
     */
    public function initializeHasUrl(): void
    {
        $this->mergeFillable(['slug', 'url_collection']);
    }

    /**
     * Boots the URL lifecycle hooks.
     * Role: Capture incoming slug/collection before save, persist Url after save, and manage deletion behavior.
     *
     * @return void
     */
    public static function bootHasUrl(): void
    {
        static::saving(function (Model $model) {
            $incomingSlug = $model->attributes['slug'] ?? $model->attributes['url'] ?? null;
            $incomingCollection = $model->attributes['url_collection'] ?? $model->attributes['type'] ?? null;

            if ($incomingSlug !== null || $incomingCollection !== null) {
                [$slug, $collection] = $model->normalizeUrlPair($incomingSlug, $incomingCollection);
                $collection = $model->resolveUrlCollection($collection);

                $model->innerUrl = [
                    'slug' => $slug,
                    'collection' => $collection,
                ];

                unset(
                    $model->attributes['slug'],
                    $model->attributes['url'],
                    $model->attributes['url_collection']
                );
            }
        });

        static::saved(function (Model $model) {
            if (!empty($model->innerUrl['slug']) || $model->innerUrl['collection'] !== null) {
                $model->persistUrlPair($model->innerUrl['slug'], $model->innerUrl['collection']);
                $model->innerUrl = ['slug' => null, 'collection' => null];
            }
        });

        static::deleted(function (Model $model) {
            if (!in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                $model->urlRecord()->delete();
            }
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::forceDeleted(function (Model $model) {
                $model->urlRecord()->delete();
            });
        }
    }

    /**
     * Returns the polymorphic one-to-one URL relation.
     * Role: Provide direct access to the underlying Url row associated with this model.
     *
     * @return MorphOne
     */
    public function urlRecord(): MorphOne
    {
        return $this->morphOne(Url::class, 'urlable');
    }

    /**
     * Resolves the default URL resource envelope for this model.
     * Role: Retrieve the Url row (for the resolved default collection) and wrap it in UrlResource.
     *
     * @return array{ok: bool, data?: UrlResource}
     */
    public function url(): array
    {
        $record = $this->getUrlRecord(null);

        return $record
            ? $this->okEnvelope(new UrlResource($record))
            : $this->failEnvelope();
    }

    /**
     * Resolves the URL by a specific collection.
     * Role: Fetch the Url row filtered by collection; optionally return only the slug string.
     *
     * @param string|null $collection Target collection to resolve; null uses the default resolution.
     * @param bool $mode When true, returns only the slug string; otherwise returns an envelope.
     *
     * @return array|string|null
     */
    public function urlByCollection(?string $collection = null, bool $mode = false): array|string|null
    {
        $record = $this->getUrlRecord($collection);

        if ($mode) {
            return $record?->url;
        }

        return $record
            ? $this->okEnvelope(new UrlResource($record))
            : $this->failEnvelope();
    }

    /**
     * Accessor for the slug string of the current model.
     * Role: Expose the Url slug via attribute access, pulling from the default collection.
     *
     * @return string|null
     */
    public function getUrlAttribute(): ?string
    {
        $env = $this->url();

        return ($env['ok'] ?? false) ? $env['data']->url : null;
    }

    /**
     * Convenience getter for the slug.
     * Role: Provide a helper method to fetch the slug value identical to the accessor.
     *
     * @return string|null
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Accessor for the Url resource of the current model.
     * Role: Expose the UrlResource via attribute access for the default collection.
     *
     * @return UrlResource|null
     */
    public function getUrlResourceAttribute(): ?UrlResource
    {
        $env = $this->url();

        return ($env['ok'] ?? false) ? $env['data'] : null;
    }

    /**
     * Accessor for the resolved collection of the current model.
     * Role: Expose the currently resolved collection (if any) via attribute access.
     *
     * @return string|null
     */
    public function getUrlCollectionAttribute(): ?string
    {
        $env = $this->url();
        $res = ($env['ok'] ?? false) ? $env['data'] : null;

        return $res?->collection;
    }

    /**
     * Finds a model by slug for this model type across all collections.
     * Role: Resolve the Url row by (type, url) and return the related model instance.
     *
     * @param string $url The slug string to match.
     *
     * @return Model|null
     */
    public static function findByUrl(string $url): ?Model
    {
        $type = (new static())->getMorphClass();

        $row = Url::query()
            ->where('urlable_type', $type)
            ->where('url', $url)
            ->first();

        return $row?->urlable;
    }

    /**
     * Finds a model by slug or throws UrlNotFoundException.
     * Role: Enforce a fail-fast lookup when a slug must resolve to a model instance.
     *
     * @param string $url The slug string to match.
     *
     * @return Model|null
     *
     * @throws Throwable
     */
    public static function findByUrlOrFail(string $url): ?Model
    {
        $model = static::findByUrl($url);

        if ($model) {
            return $model;
        }

        throw new UrlNotFoundException;
    }

    /**
     * Finds a model by slug within a specific collection for this type.
     * Role: Resolve the Url row by (type, collection?, url) and return the related model.
     *
     * @param string $url The slug string to match.
     * @param string|null $collection The collection scope; null will match rows with NULL collection.
     *
     * @return Model|null
     */
    public static function findByUrlAndCollection(string $url, ?string $collection = null): ?Model
    {
        $type = (new static())->getMorphClass();

        $row = Url::query()
            ->where('urlable_type', $type)
            ->when(
                $collection === null,
                fn (Builder $q) => $q->whereNull('collection'),
                fn (Builder $q) => $q->where('collection', $collection)
            )
            ->where('url', $url)
            ->first();

        return $row?->urlable;
    }

    /**
     * Finds a model by slug and collection or throws UrlNotFoundException.
     * Role: Enforce a fail-fast lookup scoped by collection when resolution is mandatory.
     *
     * @param string $url The slug string to match.
     * @param string|null $collection The collection scope; null will match rows with NULL collection.
     *
     * @return Model|null
     *
     * @throws Throwable
     */
    public static function findByUrlAndCollectionOrFail(string $url, ?string $collection = null): ?Model
    {
        $model = static::findByUrlAndCollection($url, $collection);

        if ($model) {
            return $model;
        }

        throw new UrlNotFoundException;
    }

    /**
     * Dispatches a new/updated URL for this model.
     * Role: Normalize, resolve collection, upsert into Url storage, and return a resource envelope.
     *
     * @param string|null $url The candidate slug to persist.
     * @param string|null $collection Optional collection scope to persist with the slug.
     *
     * @return array{ok: bool, data?: UrlResource}
     */
    public function dispatchUrl(?string $url, ?string $collection = null): array
    {
        [$slug, $collection] = $this->normalizeUrlPair($url, $collection);
        $collection = $this->resolveUrlCollection($collection);

        $this->persistUrlPair($slug, $collection);

        $record = $this->getUrlRecord($collection);

        return $record
            ? $this->okEnvelope(new UrlResource($record))
            : $this->failEnvelope();
    }

    /**
     * Forgets the URL of this model (optionally verifying collection).
     * Role: Delete the single Url row if it exists and matches the provided collection (when given).
     *
     * @param string|null $collection Optional collection to check against before deletion.
     *
     * @return array{ok: bool}
     */
    public function forgetUrl(?string $collection = null): array
    {
        $record = $this->urlRecord()->first();

        if ($record && ($collection === null || $record->collection === $collection)) {
            $record->delete();
        }

        return $this->okEnvelope();
    }

    /**
     * Normalizes a slug/collection pair prior to persistence.
     * Role: Sanitize, slugify, and length-limit slug; trim collection.
     *
     * @param string|null $slug Candidate slug (may be raw input).
     * @param string|null $collection Candidate collection (raw input).
     *
     * @return array{0: string|null, 1: string|null}
     */
    protected function normalizeUrlPair(?string $slug, ?string $collection): array
    {
        $max = (int) config('url.url_long', 191);

        $normalizedSlug = $slug === null
            ? null
            : Str::limit(Str::slug(trim((string) $slug)), $max, '');

        return [$normalizedSlug, $collection === null ? null : trim((string) $collection)];
    }

    /**
     * Resolves the collection to use when not explicitly provided.
     * Role: Prefer explicit collection; otherwise use getUrlCollectionDefault() or the model's "type".
     *
     * @param string|null $collection Candidate collection to resolve.
     *
     * @return string|null
     */
    protected function resolveUrlCollection(?string $collection): ?string
    {
        if ($collection !== null && $collection !== '') {
            return $collection;
        }

        if (method_exists($this, 'getUrlCollectionDefault')) {
            return $this->getUrlCollectionDefault();
        }

        if (isset($this->attributes['type'])) {
            return (string) $this->attributes['type'];
        }

        return null;
    }

    /**
     * Persists the (slug, collection) pair into Url storage for this model.
     * Role: Upsert the Url row using the (urlable_type, urlable_id) tuple to keep exactly one record.
     *
     * @param string|null $slug Normalized slug to persist (nullable).
     * @param string|null $collection Resolved collection to persist (nullable).
     *
     * @return void
     */
    protected function persistUrlPair(?string $slug, ?string $collection): void
    {
        /** @var Model $this */
        if ($slug === null && $collection === null) {
            return;
        }

        Url::query()->updateOrCreate(
            [
                'urlable_type' => $this->getMorphClass(),
                'urlable_id' => $this->getKey(),
            ],
            [
                'url' => $slug,
                'collection' => $collection,
            ]
        );
    }

    /**
     * Fetches the Url record for this model, optionally verifying collection equality.
     * Role: Retrieve the single Url row and filter by collection when provided.
     *
     * @param string|null $collection Target collection to match; null returns the single row as-is.
     *
     * @return Url|null
     */
    protected function getUrlRecord(?string $collection): ?Url
    {
        /** @var Model $this */
        $record = $this->urlRecord()->first();

        if ($collection === null) {
            return $record;
        }

        if ($record && $record->collection === $collection) {
            return $record;
        }

        return null;
    }

    /**
     * Builds a success envelope payload (optionally with data).
     * Role: Provide a unified response format for success paths.
     *
     * @param mixed|null $data Optional payload to include in the success envelope.
     *
     * @return array{ok: bool, data?: mixed}
     */
    protected function okEnvelope(mixed $data = null): array
    {
        return $data === null ? ['ok' => true] : ['ok' => true, 'data' => $data];
    }

    /**
     * Builds a failure envelope payload.
     * Role: Provide a unified response format for failure/empty paths.
     *
     * @return array{ok: bool}
     */
    protected function failEnvelope(): array
    {
        return ['ok' => false];
    }
}
