<?php

namespace JobMetric\Url\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JobMetric\Url\Models\Url;

/**
 * @extends Factory<Url>
 */
class UrlFactory extends Factory
{
    protected $model = Url::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'urlable_type' => null,
            'urlable_id' => null,
            'url' => $this->faker->url,
            'collection' => $this->faker->word
        ];
    }

    /**
     * set urlable
     *
     * @param string $urlable_type
     * @param int $urlable_id
     *
     * @return static
     */
    public function setUrlable(string $urlable_type, int $urlable_id): static
    {
        return $this->state(fn(array $attributes) => [
            'urlable_type' => $urlable_type,
            'urlable_id' => $urlable_id,
        ]);
    }

    /**
     * set url
     *
     * @param string $url
     *
     * @return static
     */
    public function setUrl(string $url): static
    {
        return $this->state(fn(array $attributes) => [
            'url' => $url
        ]);
    }

    /**
     * set collection
     *
     * @param string|null $collection
     *
     * @return static
     */
    public function setCollection(string|null $collection): static
    {
        return $this->state(fn(array $attributes) => [
            'collection' => $collection
        ]);
    }
}
