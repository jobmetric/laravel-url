<?php

namespace JobMetric\Url\Tests\Stubs\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JobMetric\Url\Tests\Stubs\Models\Product;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => null,
            'title' => $this->faker->sentence,
            // HasUrl merges "slug" and "slug_collection" into fillable at runtime
            'slug' => $this->faker->slug,
            'slug_collection' => null,
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
     * Set category_id.
     *
     * @param int $id
     * @return static
     */
    public function setCategoryId(int $id): static
    {
        return $this->state(fn(array $attributes) => [
            'category_id' => $id,
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
