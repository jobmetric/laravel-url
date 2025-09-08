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
 * Class Url
 *
 * Represents a versioned full URL entry associated with any Eloquent model
 * via a polymorphic relation.
 *
 * @package JobMetric\Url
 *
 * @property int $id
 * @property string $urlable_type
 * @property int $urlable_id
 * @property string $full_url
 * @property string|null $collection
 * @property int $version
 * @property Carbon|null $deleted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read Model|MorphTo $urlable
 * @property-read mixed $urlable_resource
 *
 * @method static Builder|Url whereUrlableType(string $urlable_type)
 * @method static Builder|Url whereUrlableId(int $urlable_id)
 * @method static Builder|Url whereFullUrl(string $full_url)
 * @method static Builder|Url whereCollection(string|null $collection)
 * @method static Builder|Url whereVersion(int $version)
 * @method static Builder|Url ofUrlable(string $urlable_type, int $urlable_id)
 * @method static Builder|Url active()
 */
class Url extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'urlable_type',
        'urlable_id',
        'full_url',
        'collection',
        'version',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'urlable_type' => 'string',
        'urlable_id' => 'integer',
        'full_url' => 'string',
        'collection' => 'string',
        'version' => 'integer',
        'deleted_at' => 'datetime',
    ];

    /**
     * Override the table name using config.
     */
    public function getTable(): string
    {
        return config('url.tables.url', parent::getTable());
    }

    /**
     * Get the parent urlable model (morph-to relation).
     */
    public function urlable(): MorphTo
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
     * Scope a query to only include URLs of a given urlable.
     */
    public function scopeOfUrlable(Builder $query, string $urlable_type, int $urlable_id): Builder
    {
        return $query->where([
            'urlable_type' => $urlable_type,
            'urlable_id' => $urlable_id,
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
        $model = $this->urlable;
        if (!$model) {
            return null;
        }

        $event = new UrlableResourceEvent($model);
        event($event);

        return $event->resource;
    }
}
