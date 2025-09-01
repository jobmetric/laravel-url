<?php

namespace JobMetric\Url\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use JobMetric\Url\Models\Url;

/**
 * UrlFactory
 *
 * Provides factory states for creating Url records attached to any polymorphic model.
 *
 * @extends Factory<Url>
 */
class UrlFactory extends Factory
{
    /**
     * The associated model for this factory.
     *
     * @var class-string<Url>
     */
    protected $model = Url::class;

    /**
     * Define the model's default state.
     *
     * Note:
     * - 'urlable_type' and 'urlable_id' are NOT NULL in the migration.
     *   You should set them via setUrlable() / forUrlable() before create().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Generate a slug and keep it within MySQL-safe length.
        $max = (int)config('url.url_long', 191);
        $slug = $this->faker->unique()->slug();

        return [
            'urlable_type' => null,
            'urlable_id' => null,
            'url' => Str::limit($slug, $max, ''), // keep within length cap
            'collection' => null,
        ];
    }

    /**
     * Set urlable using explicit type and id.
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
     * Set urlable from a polymorphic Eloquent model instance.
     *
     * @param Model $model
     *
     * @return static
     */
    public function forUrlable(Model $model): static
    {
        return $this->state(fn(array $attributes) => [
            'urlable_type' => $model->getMorphClass(),
            'urlable_id' => (int)$model->getKey(),
        ]);
    }

    /**
     * Set a custom URL (slug). The value will be trimmed and length-limited.
     *
     * @param string $url
     *
     * @return static
     */
    public function setUrl(string $url): static
    {
        $max = (int)config('url.url_long', 191);
        $normalized = trim($url);
        $normalized = Str::limit($normalized, $max, '');

        return $this->state(fn(array $attributes) => [
            'url' => $normalized,
        ]);
    }

    /**
     * Set a collection name. The value will be trimmed.
     *
     * @param string|null $collection
     *
     * @return static
     */
    public function setCollection(?string $collection): static
    {
        return $this->state(fn(array $attributes) => [
            'collection' => $collection !== null ? trim($collection) : null,
        ]);
    }
}
