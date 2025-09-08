<?php

namespace JobMetric\Url\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use JobMetric\Url\Events\UrlableResourceEvent;

/**
 * Class Slug
 *
 * Represents a URL (slug) entry associated with any Eloquent model
 * via a polymorphic relation.
 *
 * This model is designed to store and resolve slugs for models such as posts,
 * products, users, etc., providing a central place to manage friendly URLs.
 *
 * @package JobMetric\Url
 *
 * @property int $id
 * @property string $slugable_type
 * @property int $slugable_id
 * @property string $slug
 * @property string|null $collection
 * @property Carbon|null $deleted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read Model|MorphTo $slugable
 * @property-read mixed $slugable_resource
 *
 * @method static Builder|Slug whereSlugableType(string $slugable_type)
 * @method static Builder|Slug whereSlugableId(int $slugable_id)
 * @method static Builder|Slug whereSlug(string $slug)
 * @method static Builder|Slug whereCollection(string|null $collection)
 * @method static Builder|Slug ofSlugable(string $slugable_type, int $slugable_id)
 * @method static Builder|Slug active()
 */
class Slug extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'slugable_type',
        'slugable_id',
        'slug',
        'collection',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'slugable_type' => 'string',
        'slugable_id'   => 'integer',
        'slug'          => 'string',
        'collection'    => 'string',
        'deleted_at'    => 'datetime',
    ];

    /**
     * Override the table name using config.
     */
    public function getTable(): string
    {
        return config('url.tables.slug', parent::getTable());
    }

    /**
     * Get the parent slugable model (morph-to relation).
     */
    public function slugable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope: only active (non-deleted) rows.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Scope: filter by collection (NULL-safe).
     */
    public function scopeWhereCollection(Builder $query, ?string $collection): Builder
    {
        return $collection === null
            ? $query->whereNull('collection')
            : $query->where('collection', $collection);
    }

    /**
     * Scope a query to only include URLs of a given slugable.
     */
    public function scopeOfSlugable(Builder $query, string $slugable_type, int $slugable_id): Builder
    {
        return $query->where([
            'slugable_type' => $slugable_type,
            'slugable_id'   => $slugable_id,
        ]);
    }

    /**
     * Accessor to get the resource representation of the slugable model.
     * Fires the UrlableResourceEvent to allow external listeners to transform the slugable into a resource.
     *
     * @return mixed
     */
    public function getSlugableResourceAttribute(): mixed
    {
        // Avoid firing event when no related model is available.
        $model = $this->slugable;
        if (!$model) {
            return null;
        }

        $event = new UrlableResourceEvent($model);
        event($event);

        return $event->resource;
    }
}
