<?php

namespace JobMetric\Url\Contracts;

interface UrlContract
{
    /**
     * Get the full URL including the domain.
     *
     * @return string
     */
    public function getFullUrl(): string;
}
