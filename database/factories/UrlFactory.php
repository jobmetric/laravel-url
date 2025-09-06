<?php

namespace JobMetric\Url\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
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
        return [
            'urlable_type' => null,
            'urlable_id' => null,
            'full_url' => null,
            'collection' => null,
            'version' => 1,
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
     * Set the full URL. The value will be trimmed.
     *
     * @param string $full_url
     *
     * @return static
     */
    public function setUrl(string $full_url): static
    {
        return $this->state(fn(array $attributes) => [
            'full_url' => $full_url,
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

    /**
     * Set the version number.
     *
     * @param int $version
     *
     * @return static
     */
    public function setVersion(int $version): static
    {
        return $this->state(fn(array $attributes) => [
            'version' => $version,
        ]);
    }
}
