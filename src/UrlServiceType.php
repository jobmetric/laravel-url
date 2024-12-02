<?php

namespace JobMetric\Url;

/**
 * Trait UrlServiceType
 *
 * @package JobMetric\Url
 */
trait UrlServiceType
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
