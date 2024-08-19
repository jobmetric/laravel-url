<?php

namespace JobMetric\Url\Exceptions;

use Exception;
use Throwable;

class UrlTooLongException extends Exception
{
    public function __construct(int $code = 400, ?Throwable $previous = null)
    {
        parent::__construct(trans('url::base.exceptions.url_too_long'), $code, $previous);
    }
}
