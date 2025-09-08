<?php

namespace JobMetric\Url\Contracts;

/**
 * Contract for models that expose a canonical, versioned URL.
 *
 * The returned string is persisted in the `urls` table and used for
 * uniqueness checks, redirects, and routing resolution.
 *
 * Implementation guidelines:
 * - MUST be deterministic and side effect free (pure function of the model state).
 * - MUST be ≤ 2000 characters (schema constraint).
 * - SHOULD omit query strings and fragments (canonical path only).
 * - Choose ONE consistent format across your app (path-only or absolute) and stick to it.
 *   Recommended: path-only without leading slash, e.g. "category/product".
 * - SHOULD be normalized according to your URL policy (e.g. no trailing slash, stable casing).
 * - MUST reflect changes in related parents (e.g. category slug changes should update descendants).
 */
interface UrlContract
{
    /**
     * Return the model's canonical URL string used for persistence and routing.
     *
     * @return string Canonical URL (e.g. "category/product" or "https://example.com/category/product").
     */
    public function getFullUrl(): string;
}
