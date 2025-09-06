<?php

namespace JobMetric\Url;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use JobMetric\Url\Contracts\UrlContract;
use JobMetric\Url\Exceptions\ModelUrlContractNotFoundException;
use JobMetric\Url\Exceptions\SlugNotFoundException;
use JobMetric\Url\Http\Resources\SlugResource;
use JobMetric\Url\Models\Slug;
use Throwable;

/**
 * Trait HasUrl
 *
 * Provides a single slug for an Eloquent model using a polymorphic one-to-one relation.
 * The trait captures incoming "slug" and optional "slug_collection" (or falls back to the model's "type")
 * during saving, normalizes them, and persists to the Slug table after the model is saved.
 *
 * Database invariants (recommended):
 * - Add a unique index on (slugable_type, slugable_id) to enforce one Slug row per record.
 * - Add a unique index on (slugable_type, collection, slug) to enforce uniqueness of slugs within a type+collection scope.
 *
 * @property-read string|null $slug Exposes the resolved slug of the current model (from the default collection).
 * @property-read SlugResource|null $slug_resource Exposes the Slug resource wrapper for the current model.
 * @property-read string|null $slug_collection Exposes the resolved collection for the current model (if any).
 */
trait HasUrl
{
    /**
     * Holds a pending URL payload extracted from attributes during the saving event.
     * Role: Temporarily buffers normalized slug/collection so they can be persisted after the model is saved.
     *
     * @var array{slug: string|null, collection: string|null}
     */
    private array $innerSlug = ['slug' => null, 'collection' => null];

    /**
     * Initializes fillable attributes for URL handling.
     * Role: Make "slug" and "slug_collection" mass-assignable for convenient input binding.
     *
     * @return void
     */
    public function initializeHasUrl(): void
    {
        $this->mergeFillable(['slug', 'slug_collection']);
    }

    /**
     * Boots the URL lifecycle hooks.
     * Role: Capture incoming slug/collection before save, persist Slug after save, and manage deletion behavior.
     *
     * @return void
     * @throws Throwable
     */
    public static function bootHasUrl(): void
    {
        if (!in_array(UrlContract::class, class_implements(self::class))) {
            throw new ModelUrlContractNotFoundException(self::class);
        }

        static::saving(function (Model $model) {
            $incomingSlug = $model->attributes['slug'] ?? null;
            $incomingCollection = $model->attributes['slug_collection'] ?? $model->attributes['type'] ?? null;

            if ($incomingSlug !== null || $incomingCollection !== null) {
                [$slug, $collection] = $model->normalizeSlugPair($incomingSlug, $incomingCollection);
                $collection = $model->resolveSlugCollection($collection);

                $model->innerSlug = [
                    'slug' => $slug,
                    'collection' => $collection,
                ];

                unset(
                    $model->attributes['slug'],
                    $model->attributes['slug_collection']
                );
            }
        });

        static::saved(function (Model $model) {
            if (!empty($model->innerSlug['slug']) || $model->innerSlug['collection'] !== null) {
                $model->persistSlugPair($model->innerSlug['slug'], $model->innerSlug['collection']);
                $model->innerSlug = ['slug' => null, 'collection' => null];
            }
        });

        static::deleted(function (Model $model) {
            if (!in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                $model->slugRecord()->delete();
            }
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::forceDeleted(function (Model $model) {
                $model->slugRecord()->delete();
            });
        }
    }

    /**
     * Returns the polymorphic one-to-one URL relation.
     * Role: Provide direct access to the underlying Slug row associated with this model.
     *
     * @return MorphOne
     */
    public function slugRecord(): MorphOne
    {
        return $this->morphOne(Slug::class, 'slugable');
    }

    /**
     * Resolves the default URL resource envelope for this model.
     * Role: Retrieve the Slug row (for the resolved default collection) and wrap it in SlugResource.
     *
     * @return array{ok: bool, data?: SlugResource}
     */
    public function slug(): array
    {
        $record = $this->getSlugRecord(null);

        return $record
            ? $this->okEnvelope(new SlugResource($record))
            : $this->failEnvelope();
    }

    /**
     * Resolves the URL by a specific collection.
     * Role: Fetch the Slug row filtered by collection; optionally return only the slug string.
     *
     * @param string|null $collection Target collection to resolve; null uses the default resolution.
     * @param bool $mode When true, returns only the slug string; otherwise returns an envelope.
     *
     * @return array|string|null
     */
    public function slugByCollection(?string $collection = null, bool $mode = false): array|string|null
    {
        $record = $this->getSlugRecord($collection);

        if ($mode) {
            return $record?->slug;
        }

        return $record
            ? $this->okEnvelope(new SlugResource($record))
            : $this->failEnvelope();
    }

    /**
     * Accessor for the slug string of the current model.
     * Role: Expose the Slug slug via attribute access, pulling from the default collection.
     *
     * @return string|null
     */
    public function getSlugAttribute(): ?string
    {
        $env = $this->slug();

        return ($env['ok'] ?? false) ? $env['data']->slug : null;
    }

    /**
     * Convenience getter for the slug.
     * Role: Provide a helper method to fetch the slug value identical to the accessor.
     *
     * @return string|null
     */
    public function getSlug(): ?string
    {
        return $this->slug;
    }

    /**
     * Accessor for the Slug resource of the current model.
     * Role: Expose the SlugResource via attribute access for the default collection.
     *
     * @return SlugResource|null
     */
    public function getSlugResourceAttribute(): ?SlugResource
    {
        $env = $this->slug();

        return ($env['ok'] ?? false) ? $env['data'] : null;
    }

    /**
     * Accessor for the resolved collection of the current model.
     * Role: Expose the currently resolved collection (if any) via attribute access.
     *
     * @return string|null
     */
    public function getSlugCollectionAttribute(): ?string
    {
        $env = $this->slug();
        $res = ($env['ok'] ?? false) ? $env['data'] : null;

        return $res?->collection;
    }

    /**
     * Finds a model by slug for this model type across all collections.
     * Role: Resolve the Slug row by (type, slug) and return the related model instance.
     *
     * @param string $slug The slug string to match.
     *
     * @return Model|null
     */
    public static function findBySlug(string $slug): ?Model
    {
        $type = (new static())->getMorphClass();

        $row = Slug::query()
            ->where('slugable_type', $type)
            ->where('slug', $slug)
            ->first();

        return $row?->slugable;
    }

    /**
     * Finds a model by slug or throws SlugNotFoundException.
     * Role: Enforce a fail-fast lookup when a slug must resolve to a model instance.
     *
     * @param string $slug The slug string to match.
     *
     * @return Model|null
     *
     * @throws Throwable
     */
    public static function findBySlugOrFail(string $slug): ?Model
    {
        $model = static::findBySlug($slug);

        if ($model) {
            return $model;
        }

        throw new SlugNotFoundException;
    }

    /**
     * Finds a model by slug within a specific collection for this type.
     * Role: Resolve the Slug row by (type, collection?, slug) and return the related model.
     *
     * @param string $slug The slug string to match.
     * @param string|null $collection The collection scope; null will match rows with NULL collection.
     *
     * @return Model|null
     */
    public static function findBySlugAndCollection(string $slug, ?string $collection = null): ?Model
    {
        $type = (new static())->getMorphClass();

        $row = Slug::query()
            ->where('slugable_type', $type)
            ->when(
                $collection === null,
                fn (Builder $q) => $q->whereNull('collection'),
                fn (Builder $q) => $q->where('collection', $collection)
            )
            ->where('slug', $slug)
            ->first();

        return $row?->slugable;
    }

    /**
     * Finds a model by slug and collection or throws SlugNotFoundException.
     * Role: Enforce a fail-fast lookup scoped by collection when resolution is mandatory.
     *
     * @param string $slug The slug string to match.
     * @param string|null $collection The collection scope; null will match rows with NULL collection.
     *
     * @return Model|null
     *
     * @throws Throwable
     */
    public static function findBySlugAndCollectionOrFail(string $slug, ?string $collection = null): ?Model
    {
        $model = static::findBySlugAndCollection($slug, $collection);

        if ($model) {
            return $model;
        }

        throw new SlugNotFoundException;
    }

    /**
     * Dispatches a new/updated URL for this model.
     * Role: Normalize, resolve collection, upsert into Slug storage, and return a resource envelope.
     *
     * @param string|null $slug The candidate slug to persist.
     * @param string|null $collection Optional collection scope to persist with the slug.
     *
     * @return array{ok: bool, data?: SlugResource}
     */
    public function dispatchSlug(?string $slug, ?string $collection = null): array
    {
        [$slug, $collection] = $this->normalizeSlugPair($slug, $collection);
        $collection = $this->resolveSlugCollection($collection);

        $this->persistSlugPair($slug, $collection);

        $record = $this->getSlugRecord($collection);

        return $record
            ? $this->okEnvelope(new SlugResource($record))
            : $this->failEnvelope();
    }

    /**
     * Forgets the URL of this model (optionally verifying collection).
     * Role: Delete the single Slug row if it exists and matches the provided collection (when given).
     *
     * @param string|null $collection Optional collection to check against before deletion.
     *
     * @return array{ok: bool}
     */
    public function forgetSlug(?string $collection = null): array
    {
        $record = $this->slugRecord()->first();

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
    protected function normalizeSlugPair(?string $slug, ?string $collection): array
    {
        $normalizedSlug = $slug === null
            ? null
            : Str::limit(Str::slug(trim((string) $slug)), 100, '');

        return [$normalizedSlug, $collection === null ? null : trim((string) $collection)];
    }

    /**
     * Resolves the collection to use when not explicitly provided.
     * Role: Prefer explicit collection; otherwise use getSlugCollectionDefault() or the model's "type".
     *
     * @param string|null $collection Candidate collection to resolve.
     *
     * @return string|null
     */
    protected function resolveSlugCollection(?string $collection): ?string
    {
        if ($collection !== null && $collection !== '') {
            return $collection;
        }

        if (method_exists($this, 'getSlugCollectionDefault')) {
            return $this->getSlugCollectionDefault();
        }

        if (isset($this->attributes['type'])) {
            return (string) $this->attributes['type'];
        }

        return null;
    }

    /**
     * Persists the (slug, collection) pair into Slug storage for this model.
     * Role: Upsert the Slug row using the (slugable_type, slugable_id) tuple to keep exactly one record.
     *
     * @param string|null $slug Normalized slug to persist (nullable).
     * @param string|null $collection Resolved collection to persist (nullable).
     *
     * @return void
     */
    protected function persistSlugPair(?string $slug, ?string $collection): void
    {
        /** @var Model $this */
        if ($slug === null && $collection === null) {
            return;
        }

        Slug::query()->updateOrCreate(
            [
                'slugable_type' => $this->getMorphClass(),
                'slugable_id' => $this->getKey(),
            ],
            [
                'slug' => $slug,
                'collection' => $collection,
            ]
        );
    }

    /**
     * Fetches the Slug record for this model, optionally verifying collection equality.
     * Role: Retrieve the single Slug row and filter by collection when provided.
     *
     * @param string|null $collection Target collection to match; null returns the single row as-is.
     *
     * @return Slug|null
     */
    protected function getSlugRecord(?string $collection): ?Slug
    {
        /** @var Model $this */
        $record = $this->slugRecord()->first();

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
