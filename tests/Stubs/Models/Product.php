<?php

namespace JobMetric\Url\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use JobMetric\Url\HasUrl;
use JobMetric\Url\Tests\Stubs\Factories\ProductFactory;

/**
 * @property int $id
 * @property string $title
 * @property string $status
 *
 * @method static create(string[] $array)
 */
class Product extends Model
{
    use HasFactory, HasUrl;

    public $timestamps = false;
    protected $fillable = [
        'title',
        'status'
    ];
    protected $casts = [
        'title' => 'string',
        'status' => 'string',
    ];

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
