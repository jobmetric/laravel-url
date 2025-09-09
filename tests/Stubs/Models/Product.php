<?php

namespace JobMetric\Url\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use JobMetric\Url\Contracts\UrlContract;
use JobMetric\Url\HasUrl;
use JobMetric\Url\Tests\Stubs\Factories\ProductFactory;

/**
 * @property int|null    $id
 * @property int|null    $category_id
 * @property string|null $title
 *
 * @method static create(array $attributes = [])
 */
class Product extends Model implements UrlContract
{
    use HasFactory, SoftDeletes, HasUrl;

    protected $fillable = [
        'category_id',
        'title',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'title'       => 'string',
    ];

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Build canonical full URL:
     * - If the product has a category, include all ancestor category slugs, then product slug.
     *   e.g., category "a/b" and product "p1" => "a/b/p1"
     * - Otherwise: just the product slug.
     */
    public function getFullUrl(): string
    {
        $segments = [];

        // Append category ancestor segments if category exists
        $cat = $this->relationLoaded('category') ? $this->getRelation('category') : $this->category;
        if ($cat instanceof Category) {
            // Reuse the same algorithm: walk up to root collecting slugs
            $node = $cat;
            $catSegments = [];
            while ($node instanceof Category) {
                $slug = (string) $node->slug;
                if ($slug !== '') {
                    array_unshift($catSegments, $slug);
                }

                $node = $node->relationLoaded('parent')
                    ? $node->getRelation('parent')
                    : $node->parent;
            }

            $segments = array_merge($segments, $catSegments);
        }

        // Append product slug
        $productSlug = (string) $this->slug;
        if ($productSlug !== '') {
            $segments[] = $productSlug;
        }

        return implode('/', $segments);
    }
}
