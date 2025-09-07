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
 * @property int $id The primary identifier of the URL row.
 * @property string $slugable_type The class name of the related model.
 * @property int $slugable_id The ID of the related model instance.
 * @property string $slug The unique URL (slug) value for the model instance.
 * @property string|null $collection An optional collection name to group URLs.
 * @property Carbon $deleted_at The timestamp when this URL was soft-deleted.
 * @property Carbon $created_at The timestamp when this URL was created.
 * @property Carbon $updated_at The timestamp when this URL was last updated.
 *
 * @property-read Model|MorphTo $slugable The related Eloquent model.
 * @property-read mixed $slugable_resource The resource object resolved for the slugable via event.
 *
 * @method static Builder|Slug whereSlugableType(string $slugable_type)
 * @method static Builder|Slug whereSlugableId(int $slugable_id)
 * @method static Builder|Slug whereSlug(string $slug)
 * @method static Builder|Slug whereCollection(string|null $collection)
 *
 * @method static Builder|Slug ofSlugable(string $slugable_type, int $slugable_id)
 */
class Slug extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'slugable_type',
        'slugable_id',
        'slug',
        'collection',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'slugable_type' => 'string',
        'slugable_id' => 'integer',
        'slug' => 'string',
        'collection' => 'string',
    ];

    /**
     * Override the table name using config.
     *
     * @return string
     */
    public function getTable(): string
    {
        return config('url.tables.slug', parent::getTable());
    }

    /**
     * Get the parent slugable model (morph-to relation).
     *
     * @return MorphTo
     */
    public function slugable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include URLs of a given slugable.
     *
     * @param Builder $query
     * @param string $slugable_type
     * @param int $slugable_id
     *
     * @return Builder
     */
    public function scopeOfSlugable(Builder $query, string $slugable_type, int $slugable_id): Builder
    {
        return $query->where([
            'slugable_type' => $slugable_type,
            'slugable_id' => $slugable_id
        ]);
    }

    /**
     * Accessor to get the resource representation of the slugable model.
     * Fires the SlugableResourceEvent to allow external listeners
     * to transform the slugable into a resource.
     *
     * @return mixed
     */
    public function getSlugableResourceAttribute(): mixed
    {
        $event = new UrlableResourceEvent($this->slugable);
        event($event);

        return $event->resource;
    }
}
