<?php

namespace JobMetric\Url;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JobMetric\Url\Contracts\UrlContract;
use JobMetric\Url\Events\UrlChanged;
use JobMetric\Url\Exceptions\ModelUrlContractNotFoundException;
use JobMetric\Url\Exceptions\SlugConflictException;
use JobMetric\Url\Exceptions\SlugNotFoundException;
use JobMetric\Url\Exceptions\UrlConflictException;
use JobMetric\Url\Http\Resources\SlugResource;
use JobMetric\Url\Models\Slug;
use JobMetric\Url\Models\Url;
use Throwable;

/**
 * Trait HasUrl
 *
 * Provides a single slug for an Eloquent model using a polymorphic one-to-one relation,
 * and a versioned full URL history stored in the `urls` table. The trait captures incoming
 * "slug" and optional "slug_collection" (or falls back to the model's "type") during saving,
 * normalizes them, and persists to the Slug table after the model is saved.
 *
 * URL handling:
 * - On every save, builds current full URL via UrlContract::getFullUrl().
 * - If it's the first URL, a new Url row is created with version = 1 (active).
 * - If full URL changed, previous active Url row is soft-deleted and a new row is created with version = prev+1.
 * - Active URLs must be unique globally; soft-deleted duplicates with the same full_url are removed
 *   permanently right before inserting the new active URL, inside a DB transaction.
 * - If an active URL conflict exists for another model, a UrlConflictException is thrown.
 *
 * Cascade:
 * - If parent path components change (e.g., category slug), the model may implement:
 *     getUrlDescendants(): iterable<Model>
 *   to return children needing URL refresh. Each descendant MUST implement UrlContract.
 *   The trait will resync their versioned URLs atomically without exposing public methods.
 *
 * Deletion:
 * - On soft delete, the Slug row and all Url rows are soft-deleted.
 * - On restore, conflicts are checked; then the Slug row is restored and the active URL is resynced.
 * - On force delete, the Slug row and all Url rows are permanently deleted.
 *
 * Database invariants (recommended):
 * - Slug table: unique index on (slugable_type, slugable_id, deleted_at) and, optionally, on (slugable_type, collection, slug, deleted_at).
 * - Url table: unique (urlable_type, urlable_id, version). Active URL global uniqueness is enforced at application level.
 *
 * @property-read string|null $slug
 * @property-read SlugResource|null $slug_resource
 * @property-read string|null $slug_collection
 */
trait HasUrl
{
    /**
     * Temporarily holds normalized slug/collection so they can be persisted after the model is saved.
     *
     * @var array{slug: string|null, collection: string|null}
     */
    private array $innerSlug = ['slug' => null, 'collection' => null];

    /**
     * Caches the model's full URL prior to saving for change detection.
     *
     * @var string|null
     */
    private ?string $preSaveFullUrl = null;

    /**
     * Indicates whether the slug value explicitly changed in this save cycle.
     *
     * @var bool
     */
    private bool $slugChanged = false;

    /**
     * Allows temporarily disabling descendant cascade (use withoutUrlCascade()).
     *
     * @var bool
     */
    protected bool $disableUrlCascade = false;

    /**
     * Internal flag to detect restore flow and skip saved-hook syncing.
     *
     * @var bool
     */
    protected bool $isUrlRestoring = false;

    /**
     * Make "slug" and "slug_collection" mass-assignable for convenient input binding.
     *
     * @return void
     */
    public function initializeHasUrl(): void
    {
        $this->mergeFillable(['slug', 'slug_collection']);
    }

    /**
     * Capture incoming slug/collection before save, persist Slug after save,
     * handle versioned full URL, and manage delete/restore/force-delete flows.
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
            // Cache current full URL (if computable) to detect path changes
            if (method_exists($model, 'getFullUrl')) {
                try {
                    /** @var UrlContract $model */
                    $model->preSaveFullUrl = (string) $model->getFullUrl();
                } catch (Throwable) {
                    $model->preSaveFullUrl = null;
                }
            }

            // Capture incoming slug and optional collection
            $incomingSlug = $model->attributes['slug'] ?? null;
            $incomingCollection = $model->attributes['slug_collection'] ?? $model->attributes['type'] ?? null;

            if ($incomingSlug !== null || $incomingCollection !== null) {
                [$slug, $collection] = $model->normalizeSlugPair($incomingSlug, $incomingCollection);
                $collection = $model->resolveSlugCollection($collection);

                // Detect explicit slug or collection change compared to current stored record (if any)
                $currentRecord = $model->slugRecord()->withTrashed()->first();
                $model->slugChanged = $currentRecord
                    ? ((string) $currentRecord->slug !== (string) ($slug ?? $currentRecord->slug) ||
                        (string) $currentRecord->collection !== (string) $collection)
                    : ($slug !== null || $collection !== null);

                $model->innerSlug = [
                    'slug' => $slug,
                    'collection' => $collection,
                ];

                unset($model->attributes['slug'], $model->attributes['slug_collection']);
            } else {
                $model->slugChanged = false;
            }
        });

        static::saved(function (Model $model) {
            // If we are in the middle of a restore, skip saved-hook syncing.
            if (property_exists($model, 'isUrlRestoring') && $model->isUrlRestoring === true) {
                // Just clear temp flags; restored-hook will handle proper syncing.
                $model->innerSlug = ['slug' => null, 'collection' => null];
                $model->slugChanged = false;
                $model->preSaveFullUrl = null;

                return;
            }

            // If slug/collection going to change, ensure no active slug conflict BEFORE upsert
            if (!empty($model->innerSlug['slug']) || $model->innerSlug['collection'] !== null) {
                if ($model->slugChanged) {
                    $model->ensureNoActiveSlugConflict($model->innerSlug['collection'], $model->innerSlug['slug']);
                }

                $model->persistSlugPair($model->innerSlug['slug'], $model->innerSlug['collection']);
                $model->innerSlug = ['slug' => null, 'collection' => null];
            }

            // Execute URL sync & cascade AFTER COMMIT to avoid partial states
            $after = function () use ($model) {
                $model->syncVersionedUrl();

                if ($model->slugChanged && !$model->disableUrlCascade) {
                    $model->refreshDescendantUrls();
                }

                $model->slugChanged = false;
                $model->preSaveFullUrl = null;
            };

            if (method_exists(DB::class, 'afterCommit')) {
                DB::afterCommit($after);
            } else {
                $after();
            }
        });

        // Soft delete / Hard delete handler
        static::deleted(function (Model $model) {
            $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($model), true);
            $isForce = $usesSoftDeletes && (isset($model->forceDeleting) && $model->forceDeleting === true);

            if ($usesSoftDeletes && !$isForce) {
                // Soft delete related slug and URLs
                $model->deleteRelatedSlugAndUrls(false);
                return;
            }

            // Non-soft models or hard delete path: remove everything permanently
            $model->deleteRelatedSlugAndUrls(true);
        });

        // Force delete & restore flows for models using SoftDeletes
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::forceDeleted(function (Model $model) {
                $model->deleteRelatedSlugAndUrls(true);
            });

            // Before restoring: validate conflicts (slug ONLY).
            static::restoring(function (Model $model) {
                // mark restore flow
                if (property_exists($model, 'isUrlRestoring')) {
                    $model->isUrlRestoring = true;
                }

                $trashedSlug = Slug::query()
                    ->ofSlugable($model->getMorphClass(), $model->getKey())
                    ->withTrashed()
                    ->first();

                if ($trashedSlug) {
                    $model->ensureNoActiveSlugConflict($trashedSlug->collection, $trashedSlug->slug);
                }
            });

            // After restoring: restore slug row and resync versioned URL (after commit).
            // Conflict on full URL is checked inside syncVersionedUrl(), now that slug is restored.
            static::restored(function (Model $model) {
                $after = function () use ($model) {
                    $model->restoreRelatedSlugIfAvailable();
                    $model->syncVersionedUrl();

                    // clear restore flag
                    if (property_exists($model, 'isUrlRestoring')) {
                        $model->isUrlRestoring = false;
                    }
                };

                if (method_exists(DB::class, 'afterCommit')) {
                    DB::afterCommit($after);
                } else {
                    $after();
                }
            });
        }
    }

    /**
     * Polymorphic one-to-one Slug relation.
     *
     * @return MorphOne
     */
    public function slugRecord(): MorphOne
    {
        return $this->morphOne(Slug::class, 'slugable')->withDefault();
    }

    /**
     * Polymorphic one-to-one current Url relation (latest active by version).
     *
     * @return MorphOne
     */
    public function urlRecord(): MorphOne
    {
        return $this
            ->morphOne(Url::class, 'urlable')
            ->whereNull('deleted_at')
            ->latestOfMany('version');
    }

    /**
     * Retrieve the Slug row and wrap it in SlugResource.
     *
     * @return array{ok: bool, data?: SlugResource}
     */
    public function slug(): array
    {
        $record = $this->getSlugRecord(null);

        return $record
            ? $this->okEnvelope(SlugResource::make($record))
            : $this->failEnvelope();
    }

    /**
     * Fetch the Slug row filtered by collection; optionally return only the slug string.
     *
     * @param string|null $collection
     * @param bool $mode When true, returns only the slug string.
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
            ? $this->okEnvelope(SlugResource::make($record))
            : $this->failEnvelope();
    }

    /**
     * Slug accessor using the default collection.
     *
     * @return string|null
     */
    public function getSlugAttribute(): ?string
    {
        $env = $this->slug();

        return ($env['ok'] ?? false) ? $env['data']->slug : null;
    }

    /**
     * Convenience getter for slug value.
     *
     * @return string|null
     */
    public function getSlug(): ?string
    {
        return $this->slug;
    }

    /**
     * SlugResource accessor using the default collection.
     *
     * @return SlugResource|null
     */
    public function getSlugResourceAttribute(): ?SlugResource
    {
        $env = $this->slug();

        return ($env['ok'] ?? false) ? $env['data'] : null;
    }

    /**
     * Resolved collection accessor using the default collection.
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
     * Resolve by slug across all collections for this model type.
     *
     * @param string $slug
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
     * Resolve by slug or throw.
     *
     * @param string $slug
     * @return Model|null
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
     * Resolve by slug and collection for this model type.
     *
     * @param string $slug
     * @param string|null $collection
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
     * Resolve by slug and collection or throw.
     *
     * @param string $slug
     * @param string|null $collection
     * @return Model|null
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
     * Normalize, resolve collection, upsert into Slug storage, and return a resource envelope.
     * Also syncs the versioned URL with the current full URL.
     *
     * @param string|null $slug
     * @param string|null $collection
     * @return array{ok: bool, data?: SlugResource}
     * @throws Throwable
     */
    public function dispatchSlug(?string $slug, ?string $collection = null): array
    {
        [$slug, $collection] = $this->normalizeSlugPair($slug, $collection);
        $collection = $this->resolveSlugCollection($collection);

        // Check conflicts on-demand dispatch as well
        $this->ensureNoActiveSlugConflict($collection, $slug);

        $this->persistSlugPair($slug, $collection);

        $this->syncVersionedUrl();

        $record = $this->getSlugRecord($collection);

        return $record
            ? $this->okEnvelope(SlugResource::make($record))
            : $this->failEnvelope();
    }

    /**
     * Delete the single Slug row if exists and matches the provided collection (when given).
     *
     * @param string|null $collection
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
     * Sanitize, slugify, and length-limit slug; trim collection.
     *
     * @param string|null $slug
     * @param string|null $collection
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
     * Prefer explicit collection; otherwise use getSlugCollectionDefault() or the model's "type".
     *
     * @param string|null $collection
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
     * Upsert the Slug row using the (slugable_type, slugable_id) tuple to keep exactly one record.
     *
     * @param string|null $slug
     * @param string|null $collection
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
     * Fetch the Slug record, optionally verifying collection equality.
     *
     * @param string|null $collection
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
     * Check for an active (non-deleted) URL conflict with the given full URL.
     * Throws if a different model already owns the same active URL.
     *
     * @param string $fullUrl
     * @return void
     * @throws UrlConflictException
     */
    protected function ensureNoActiveFullUrlConflict(string $fullUrl): void
    {
        /** @var Model $this */
        $conflict = Url::query()
            ->where('full_url', $fullUrl)
            ->whereNull('deleted_at')
            ->where(function (Builder $q) {
                $q->where('urlable_type', '!=', $this->getMorphClass())
                    ->orWhere('urlable_id', '!=', $this->getKey());
            })
            ->exists();

        if ($conflict) {
            throw new UrlConflictException();
        }
    }

    /**
     * Check for an active (non-deleted) slug conflict in the same type and collection.
     * Throws if another model uses the same slug.
     *
     * @param string|null $collection
     * @param string|null $slug
     * @return void
     * @throws SlugConflictException
     */
    protected function ensureNoActiveSlugConflict(?string $collection, ?string $slug): void
    {
        if ($slug === null) {
            return;
        }

        $query = Slug::query()
            ->where('slugable_type', $this->getMorphClass())
            ->where('slug', $slug)
            ->whereNull('deleted_at');

        $query = $collection === null
            ? $query->whereNull('collection')
            : $query->where('collection', $collection);

        $conflict = $query
            ->where(function (Builder $q) {
                $q->where('slugable_id', '!=', $this->getKey());
            })
            ->exists();

        if ($conflict) {
            throw new SlugConflictException();
        }
    }

    /**
     * Permanently remove soft-deleted URLs that match the given full URL.
     * This is used right before inserting a new active URL for the same path.
     *
     * @param string $fullUrl
     * @return void
     */
    protected function purgeTrashedFullUrlDuplicates(string $fullUrl): void
    {
        Url::query()
            ->where('full_url', $fullUrl)
            ->onlyTrashed()
            ->forceDelete();
    }

    /**
     * Synchronize the versioned Url row with the current computed full URL (self).
     * - Creates version=1 if none exists.
     * - If changed, soft-deletes previous active and inserts next version.
     * Database operations are wrapped in a transaction to keep changes atomic.
     *
     * @return void
     * @throws Throwable
     */
    protected function syncVersionedUrl(): void
    {
        /** @var Model&UrlContract $this */

        $newFullUrl = (string) $this->getFullUrl();

        $slugRow = $this->slugRecord()->withTrashed()->first();
        $collection = $slugRow?->collection;

        $currentActive = $this->urlRecord()->first();

        if (!$currentActive) {
            DB::transaction(function () use ($newFullUrl, $collection) {
                $this->ensureNoActiveFullUrlConflict($newFullUrl);
                $this->purgeTrashedFullUrlDuplicates($newFullUrl);

                Url::query()->create([
                    'urlable_type' => $this->getMorphClass(),
                    'urlable_id'   => $this->getKey(),
                    'full_url'     => $newFullUrl,
                    'collection'   => $collection,
                    'version'      => 1,
                ]);

                // Fire created event
                event(new UrlChanged($this, null, $newFullUrl, 1));
            });

            return;
        }

        if ($currentActive->full_url === $newFullUrl) {
            if ($currentActive->collection !== $collection) {
                $currentActive->collection = $collection;
                $currentActive->save();
            }
            return;
        }

        DB::transaction(function () use ($currentActive, $newFullUrl, $collection) {
            $this->ensureNoActiveFullUrlConflict($newFullUrl);
            $this->purgeTrashedFullUrlDuplicates($newFullUrl);

            $nextVersion = (int) $currentActive->version + 1;
            $oldFullUrl  = (string) $currentActive->full_url;

            $currentActive->delete();

            Url::query()->create([
                'urlable_type' => $this->getMorphClass(),
                'urlable_id'   => $this->getKey(),
                'full_url'     => $newFullUrl,
                'collection'   => $collection,
                'version'      => $nextVersion,
            ]);

            // Fire changed event
            event(new UrlChanged($this, $oldFullUrl, $newFullUrl, $nextVersion));
        });
    }

    /**
     * Synchronize the versioned Url row for another model that implements UrlContract.
     * Used for cascading updates on descendants.
     *
     * @param Model&UrlContract $model
     * @return void
     * @throws Throwable
     */
    protected function syncVersionedUrlFor(Model&UrlContract $model): void
    {
        $newFullUrl = (string) $model->getFullUrl();

        $slugRow = Slug::query()
            ->ofSlugable($model->getMorphClass(), $model->getKey())
            ->withTrashed()
            ->first();

        $collection = $slugRow?->collection;

        $currentActive = Url::query()
            ->ofUrlable($model->getMorphClass(), $model->getKey())
            ->whereNull('deleted_at')
            ->orderByDesc('version')
            ->first();

        DB::transaction(function () use ($model, $newFullUrl, $collection, $currentActive) {
            $conflict = Url::query()
                ->where('full_url', $newFullUrl)
                ->whereNull('deleted_at')
                ->where(function (Builder $q) use ($model) {
                    $q->where('urlable_type', '!=', $model->getMorphClass())
                        ->orWhere('urlable_id', '!=', $model->getKey());
                })
                ->exists();

            if ($conflict) {
                throw new UrlConflictException();
            }

            Url::query()
                ->where('full_url', $newFullUrl)
                ->onlyTrashed()
                ->forceDelete();

            if (!$currentActive) {
                Url::query()->create([
                    'urlable_type' => $model->getMorphClass(),
                    'urlable_id'   => $model->getKey(),
                    'full_url'     => $newFullUrl,
                    'collection'   => $collection,
                    'version'      => 1,
                ]);

                event(new UrlChanged($model, null, $newFullUrl, 1));
                return;
            }

            if ($currentActive->full_url === $newFullUrl) {
                if ($currentActive->collection !== $collection) {
                    $currentActive->collection = $collection;
                    $currentActive->save();
                }
                return;
            }

            $nextVersion = (int) $currentActive->version + 1;
            $oldFullUrl  = (string) $currentActive->full_url;

            $currentActive->delete();

            Url::query()->create([
                'urlable_type' => $model->getMorphClass(),
                'urlable_id'   => $model->getKey(),
                'full_url'     => $newFullUrl,
                'collection'   => $collection,
                'version'      => $nextVersion,
            ]);

            event(new UrlChanged($model, $oldFullUrl, $newFullUrl, $nextVersion));
        });
    }

    /**
     * If the model defines getUrlDescendants(): iterable<Model>, refresh each descendant's URL.
     * Only descendants that implement UrlContract will be processed.
     *
     * @return void
     * @throws Throwable
     */
    protected function refreshDescendantUrls(): void
    {
        if (!method_exists($this, 'getUrlDescendants')) {
            return;
        }

        $descendants = $this->getUrlDescendants();

        if (!is_iterable($descendants)) {
            return;
        }

        foreach ($descendants as $child) {
            if (!$child instanceof Model) {
                continue;
            }

            if (!$child instanceof UrlContract) {
                continue;
            }

            $this->syncVersionedUrlFor($child);
        }
    }

    /**
     * Soft or force delete related slug and all URL rows for this model.
     *
     * @param bool $force When true, permanently delete; otherwise soft delete.
     * @return void
     */
    protected function deleteRelatedSlugAndUrls(bool $force): void
    {
        $type = $this->getMorphClass();
        $id   = $this->getKey();

        if ($force) {
            Slug::query()
                ->ofSlugable($type, $id)
                ->withTrashed()
                ->forceDelete();

            Url::query()
                ->ofUrlable($type, $id)
                ->withTrashed()
                ->forceDelete();

            return;
        }

        // Soft delete: single slug row and all active URLs
        $this->slugRecord()->delete();

        Url::query()
            ->ofUrlable($type, $id)
            ->delete();
    }

    /**
     * Restore the slug row of this model if it exists in trash.
     *
     * @return void
     */
    protected function restoreRelatedSlugIfAvailable(): void
    {
        Slug::query()
            ->ofSlugable($this->getMorphClass(), $this->getKey())
            ->onlyTrashed()
            ->restore();
    }

    /**
     * Public helper: returns the current active full URL string (if any), without recomputing.
     *
     * @return string|null
     */
    public function getActiveFullUrl(): ?string
    {
        $row = $this->urlRecord()->first();
        return $row?->full_url;
    }

    /**
     * Returns the full URL history (active + optionally trashed) for this model, ordered by version asc.
     *
     * @param bool $withTrashed
     * @return Collection<int, Url>
     */
    public function urlHistory(bool $withTrashed = true): Collection
    {
        $q = Url::query()
            ->ofUrlable($this->getMorphClass(), $this->getKey())
            ->orderBy('version');

        if ($withTrashed) {
            $q->withTrashed();
        }

        return $q->get();
    }

    /**
     * Temporarily disables descendant cascade inside the given callback.
     *
     * @param callable $fn
     * @return mixed
     */
    public function withoutUrlCascade(callable $fn): mixed
    {
        $prev = $this->disableUrlCascade;
        $this->disableUrlCascade = true;

        try {
            return $fn();
        } finally {
            $this->disableUrlCascade = $prev;
        }
    }

    /**
     * Rebuilds (resyncs) URLs for all records of the current model class in chunks.
     *
     * @param callable|null $queryHook Optional: function(Builder $q): void to filter/customize the query.
     * @param int $chunk
     * @return void
     * @throws Throwable
     */
    public static function rebuildAllUrls(callable $queryHook = null, int $chunk = 500): void
    {
        /** @var Model&UrlContract $proto */
        $proto = new static();

        $q = $proto->newQuery();

        if ($queryHook) {
            $queryHook($q);
        }

        $q->chunkById($chunk, function ($items) {
            /** @var iterable<Model&UrlContract> $items */
            foreach ($items as $item) {
                // Directly resync; does not trigger saved() hooks or cascades
                $item->syncVersionedUrl();
            }
        });
    }

    /**
     * Resolve the active model that currently owns a given full URL (across all types).
     * Returns the urlable model if the URL is active; otherwise null.
     *
     * @param string $fullUrl
     * @return Model|null
     */
    public static function resolveActiveByFullUrl(string $fullUrl): ?Model
    {
        $row = Url::query()
            ->where('full_url', $fullUrl)
            ->whereNull('deleted_at')
            ->first();

        return $row?->urlable;
    }

    /**
     * Resolves a redirect target (canonical) for a given old full URL.
     * If the URL is currently active, returns null (no redirect needed).
     * If only a trashed URL exists, returns the current active full URL of the same model, if any.
     *
     * @param string $fullUrl
     * @return string|null
     */
    public static function resolveRedirectTarget(string $fullUrl): ?string
    {
        // If active exists, no redirect needed
        $active = Url::query()
            ->where('full_url', $fullUrl)
            ->whereNull('deleted_at')
            ->first();

        if ($active) {
            return null;
        }

        // Find most recent trashed row for this path
        $trashed = Url::query()
            ->where('full_url', $fullUrl)
            ->onlyTrashed()
            ->orderByDesc('id')
            ->first();

        if (!$trashed) {
            return null;
        }

        // Get current active URL of the same model
        $current = Url::query()
            ->ofUrlable($trashed->urlable_type, $trashed->urlable_id)
            ->whereNull('deleted_at')
            ->orderByDesc('version')
            ->first();

        return $current?->full_url;
    }

    /**
     * Build a success envelope payload (optionally with data).
     *
     * @param mixed|null $data
     * @return array{ok: bool, data?: mixed}
     */
    protected function okEnvelope(mixed $data = null): array
    {
        return $data === null ? ['ok' => true] : ['ok' => true, 'data' => $data];
    }

    /**
     * Build a failure envelope payload.
     *
     * @return array{ok: bool}
     */
    protected function failEnvelope(): array
    {
        return ['ok' => false];
    }
}
