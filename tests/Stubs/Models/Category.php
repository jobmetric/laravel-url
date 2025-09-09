<?php

namespace JobMetric\Url\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use JobMetric\Url\Contracts\UrlContract;
use JobMetric\Url\HasUrl;
use JobMetric\Url\Tests\Stubs\Factories\CategoryFactory;

/**
 * @property int         $id
 * @property int|null    $parent_id
 * @property string|null $title
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 *
 * @method static create(array $attributes = [])
 */
class Category extends Model implements UrlContract
{
    use HasFactory, SoftDeletes, HasUrl;

    protected $fillable = [
        'parent_id',
        'title',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'title'     => 'string',
    ];

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * Provide URL descendants for cascading refresh:
     * - All nested child categories (recursively)
     * - All products under this category subtree
     *
     * @return iterable<Model>
     */
    protected function getUrlDescendants(): iterable
    {
        return $this->gatherUrlDescendants();
    }

    /**
     * Build canonical full URL for Category using ancestor slugs.
     * Example: root "a", child "b" => "a/b".
     */
    public function getFullUrl(): string
    {
        $segments = $this->buildAncestorSlugSegments();
        return implode('/', $segments);
    }

    /**
     * Collect category slugs from root to current.
     *
     * @return array<int, string>
     */
    private function buildAncestorSlugSegments(): array
    {
        $segments = [];
        $node = $this;

        // Walk up the parent chain, unshifting slugs to get root->...->current order
        while ($node instanceof self) {
            $slug = (string) $node->slug;
            if ($slug !== '') {
                array_unshift($segments, $slug);
            }

            $node = $node->relationLoaded('parent')
                ? $node->getRelation('parent')
                : $node->parent;
        }

        return $segments;
    }

    /**
     * Recursively gather all descendant categories and their products.
     *
     * @return array<int, Model>
     */
    private function gatherUrlDescendants(): array
    {
        $acc = [];

        // Include all direct products
        foreach ($this->products as $product) {
            $acc[] = $product;
        }

        // Include all child categories and recurse
        foreach ($this->children as $child) {
            $acc[] = $child;

            // Merge child's descendants
            foreach ($child->gatherUrlDescendants() as $desc) {
                $acc[] = $desc;
            }
        }

        return $acc;
    }
}
