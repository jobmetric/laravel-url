<?php

namespace JobMetric\Url\Tests\Stubs\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JobMetric\Url\Tests\Stubs\Models\Category;

/**
 * @extends Factory<Category>
 *
 * Factory for the Category stub model used in tests.
 * Note:
 * - Do NOT persist "slug" or "slug_collection" as real DB columns.
 * - Instead, set URL via dispatchSlug() in an after-creating hook.
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * Only real DB columns should be returned here.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'title' => $this->faker->sentence,
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
     * Set URL (slug and collection) via the HasUrl trait AFTER the model is created.
     *
     * This avoids trying to insert non-existent DB columns like "slug" into the "categories" table.
     *
     * @param string $slug
     * @param string|null $slugCollection
     * @return static
     */
    public function setUrl(string $slug, ?string $slugCollection = null): static
    {
        return $this->state(fn(array $attributes) => [
            'slug' => $slug,
            'slug_collection' => $slugCollection,
        ]);
    }
}
