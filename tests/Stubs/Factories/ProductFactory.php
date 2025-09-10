<?php

namespace JobMetric\Url\Tests\Stubs\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JobMetric\Url\Tests\Stubs\Models\Product;

/**
 * @extends Factory<Product>
 *
 * Factory for the Product stub model used in tests.
 * Note:
 * - Do NOT persist "slug" or "slug_collection" as real DB columns.
 * - Instead, set URL via dispatchSlug() in an after-creating hook.
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

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
            'category_id' => null,
            'title' => $this->faker->sentence,
        ];
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
     * Set category id (nullable to support tests that pass null).
     *
     * @param int|null $id
     * @return static
     */
    public function setCategoryId(?int $id): static
    {
        return $this->state(fn(array $attributes) => [
            'category_id' => $id,
        ]);
    }

    /**
     * Set URL (slug and collection) via the HasUrl trait AFTER the model is created.
     *
     * This avoids trying to insert non-existent DB columns like "slug" into the "products" table.
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
