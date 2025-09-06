<?php

namespace JobMetric\Url\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use JobMetric\Url\Models\Slug;

/**
 * SlugFactory
 *
 * Provides factory states for creating Slug records attached to any polymorphic model.
 *
 * @extends Factory<Slug>
 */
class SlugFactory extends Factory
{
    /**
     * The associated model for this factory.
     *
     * @var class-string<Slug>
     */
    protected $model = Slug::class;

    /**
     * Define the model's default state.
     *
     * Note:
     * - 'slugable_type' and 'slugable_id' are NOT NULL in the migration.
     *   You should set them via setSlugable() / forSlugable() before create().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Generate a slug and keep it within MySQL-safe length.
        $slug = $this->faker->unique()->slug();

        return [
            'slugable_type' => null,
            'slugable_id' => null,
            'slug' => Str::limit($slug, 100, ''), // keep within length cap
            'collection' => null,
        ];
    }

    /**
     * Set slugable using explicit type and id.
     *
     * @param string $slugable_type
     * @param int $slugable_id
     *
     * @return static
     */
    public function setSlugable(string $slugable_type, int $slugable_id): static
    {
        return $this->state(fn(array $attributes) => [
            'slugable_type' => $slugable_type,
            'slugable_id' => $slugable_id,
        ]);
    }

    /**
     * Set slugable from a polymorphic Eloquent model instance.
     *
     * @param Model $model
     *
     * @return static
     */
    public function forSlugable(Model $model): static
    {
        return $this->state(fn(array $attributes) => [
            'slugable_type' => $model->getMorphClass(),
            'slugable_id' => (int)$model->getKey(),
        ]);
    }

    /**
     * Set a custom URL (slug). The value will be trimmed and length-limited.
     *
     * @param string $slug
     *
     * @return static
     */
    public function setSlug(string $slug): static
    {
        $max = (int)config('url.slug_long', 191);
        $normalized = trim($slug);
        $normalized = Str::limit($normalized, $max, '');

        return $this->state(fn(array $attributes) => [
            'slug' => $normalized,
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
