<?php

namespace JobMetric\Url\Exceptions;

use Exception;
use Throwable;

class UrlConflictException extends Exception
{
    public function __construct(int $code = 409, ?Throwable $previous = null)
    {
        parent::__construct(trans('url::base.exceptions.url_conflict'), $code, $previous);
    }
}
