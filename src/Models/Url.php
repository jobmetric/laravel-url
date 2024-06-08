<?php

namespace JobMetric\Url\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int id
 * @property string urlable_type
 * @property int urlable_id
 * @property string url
 * @property string collection
 * @property mixed created_at
 * @property mixed updated_at
 */
class Url extends Model
{
    use HasFactory;

    protected $fillable = [
        'urlable_type',
        'urlable_id',
        'url',
        'collection'
    ];

    protected $casts = [
        'urlable_type' => 'string',
        'urlable_id' => 'integer',
        'url' => 'string',
        'collection' => 'string'
    ];

    public function getTable()
    {
        return config('url.tables.url', parent::getTable());
    }

    /**
     * urlable relationship
     *
     * @return MorphTo
     */
    public function urlable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include urls of a given urlable.
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
     * Scope a query to only include urls of a given url.
     *
     * @param Builder $query
     * @param string $url
     *
     * @return Builder
     */
    public function scopeOfUrl(Builder $query, string $url): Builder
    {
        return $query->where('url', $url);
    }

    /**
     * Scope a query to only include urls of a given collection.
     *
     * @param Builder $query
     * @param string $collection
     *
     * @return Builder
     */
    public function scopeOfCollection(Builder $query, string $collection): Builder
    {
        return $query->where('collection', $collection);
    }
}
