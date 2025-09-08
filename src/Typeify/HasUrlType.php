<?php

namespace JobMetric\Url\Typeify;

/**
 * Trait HasUrlType
 *
 * Add URL capability to a type.
 *
 * @package JobMetric\Url
 */
trait HasUrlType
{
    /**
     * Enable URL capability on the current type.
     *
     * @return static
     */
    public function url(): static
    {
        $this->setTypeParam('hasUrl', true);

        return $this;
    }

    /**
     * Determine whether the current type has URL capability.
     *
     * @return bool
     */
    public function hasUrl(): bool
    {
        return (bool) $this->getTypeParam('hasUrl', false);
    }
}
