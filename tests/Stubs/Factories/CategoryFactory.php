<?php

namespace JobMetric\Url\Tests\Stubs\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JobMetric\Url\Tests\Stubs\Models\Category;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'title' => $this->faker->sentence,
            // HasUrl merges "slug" and "slug_collection" into fillable at runtime
            'slug' => $this->faker->slug,
            'slug_collection' => null,
        ];
    }

    /**
     * Set parent_id.
     *
     * @param int|null $parentId
     * @return static
     */
    public function setParentId(int $parentId = null): static
    {
        return $this->state(fn(array $attributes) => [
            'parent_id' => $parentId,
        ]);
    }

    /**
     * Set title.
     *
     * @param string $title
     * @return static
     */
    public function setTitle(string $title): static
    {
        return $this->state(fn(array $attributes) => [
            'title' => $title,
        ]);
    }

    /**
     * Set URL fields (slug and slug_collection).
     *
     * @param string $slug
     * @param string|null $slugCollection
     * @return static
     */
    public function setUrl(string $slug, string $slugCollection = null): static
    {
        return $this->state(fn(array $attributes) => [
            'slug' => $slug,
            'slug_collection' => $slugCollection,
        ]);
    }
}
