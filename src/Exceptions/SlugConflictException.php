<?php

namespace JobMetric\Url\Exceptions;

use Exception;
use Throwable;

class SlugConflictException extends Exception
{
    public function __construct(int $code = 409, ?Throwable $previous = null)
    {
        parent::__construct(trans('url::base.exceptions.slug_conflict'), $code, $previous);
    }
}
