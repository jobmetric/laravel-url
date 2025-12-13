<?php

namespace JobMetric\Url\Events;

use Illuminate\Database\Eloquent\Model;
use JobMetric\EventSystem\Contracts\DomainEvent;
use JobMetric\EventSystem\Support\DomainEventDefinition;

/**
 * Event fired when a model's active full URL is created or changes (versioned).
 *
 * - $old is null when the first URL (version=1) is created.
 * - $new is the newly-activated full URL.
 * - $version is the version number of the newly-activated URL.
 */
readonly class UrlChanged implements DomainEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public Model $model,
        public ?string $old,
        public string $new,
        public int $version
    ) {
    }

    /**
     * Returns the stable technical key for the domain event.
     *
     * @return string
     */
    public static function key(): string
    {
        return 'url.changed';
    }

    /**
     * Returns the full metadata definition for this domain event.
     *
     * @return DomainEventDefinition
     */
    public static function definition(): DomainEventDefinition
    {
        return new DomainEventDefinition(self::key(), 'url::base.entity_names.url', 'url::base.events.url_changed.title', 'url::base.events.url_changed.description', 'fas fa-exchange-alt', [
            'url',
            'change',
            'routing',
        ]);
    }
}
