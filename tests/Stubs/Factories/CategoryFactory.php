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
            'title' => $this->faker->sentence,
            'status' => $this->faker->randomElement(['draft', 'published', 'archived']),
            'slug' => $this->faker->slug,
            'slug_collection' => null,
        ];
    }

    /**
     * set title
     *
     * @param string $title
     *
     * @return static
     */
    public function setTitle(string $title): static
    {
        return $this->state(fn(array $attributes) => [
            'title' => $title,
        ]);
    }

    /**
     * set status
     *
     * @param string $status
     *
     * @return static
     */
    public function setStatus(string $status): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => $status,
        ]);
    }

    /**
     * set url (slug and slug_collection)
     *
     * @param string $slug
     * @param string|null $slugCollection
     *
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
