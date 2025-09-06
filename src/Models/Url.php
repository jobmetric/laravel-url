<?php

namespace JobMetric\Url\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use JobMetric\Url\Events\UrlableResourceEvent;

/**
 * Class Url
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
 * @property string $urlable_type The class name of the related model.
 * @property int $urlable_id The ID of the related model instance.
 * @property string $full_url The full URL string (up to 2000 characters).
 * @property string|null $collection An optional collection name to group URLs.
 * @property integer $version The version number of the URL for the given urlable.
 * @property Carbon|null $deleted_at The timestamp when this URL was soft-deleted.
 * @property Carbon $created_at The timestamp when this URL was created.
 * @property Carbon $updated_at The timestamp when this URL was last updated.
 *
 * @property-read Model|MorphTo $urlable The related Eloquent model.
 * @property-read mixed $urlable_resource The resource object resolved for the urlable via event.
 *
 * @method static Builder|Url whereUrlableType(string $urlable_type)
 * @method static Builder|Url whereUrlableId(int $urlable_id)
 * @method static Builder|Url whereFullUrl(string $full_url)
 * @method static Builder|Url whereCollection(string|null $collection)
 * @method static Builder|Url whereVersion(int $version)
 *
 * @method static Builder|Url ofUrlable(string $urlable_type, int $urlable_id)
 */
class Url extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'urlable_type',
        'urlable_id',
        'full_url',
        'collection',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'urlable_type' => 'string',
        'urlable_id' => 'integer',
        'full_url' => 'string',
        'collection' => 'string',
    ];

    /**
     * Override the table name using config.
     *
     * @return string
     */
    public function getTable(): string
    {
        return config('url.tables.url', parent::getTable());
    }

    /**
     * Get the parent urlable model (morph-to relation).
     *
     * @return MorphTo
     */
    public function urlable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include URLs of a given urlable.
     *
     * @param Builder $query
     * @param string $urlable_type
     * @param int $urlable_id
     *
     * @return Builder
     */
    public function scopeOfUrlable(Builder $query, string $urlable_type, int $urlable_id): Builder
    {
        return $query->where([
            'urlable_type' => $urlable_type,
            'urlable_id' => $urlable_id
        ]);
    }

    /**
     * Accessor to get the resource representation of the urlable model.
     * Fires the UrlableResourceEvent to allow external listeners
     * to transform the urlable into a resource.
     *
     * @return mixed
     */
    public function getUrlableResourceAttribute(): mixed
    {
        $event = new UrlableResourceEvent($this->urlable);
        event($event);

        return $event->resource;
    }
}
