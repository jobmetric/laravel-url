<?php

namespace JobMetric\Url\Typeify;

/**
 * Trait HasUrlType
 *
 * @package JobMetric\Url
 */
trait HasUrlType
{
    /**
     * Enable Url.
     *
     * @return static
     */
    public function url(): static
    {
        $this->setTypeParam('hasUrl', true);

        return $this;
    }

    /**
     * Has Url.
     *
     * @return bool
     */
    public function hasUrl(): bool
    {
        return $this->getTypeParam('hasUrl', false);
    }
}
