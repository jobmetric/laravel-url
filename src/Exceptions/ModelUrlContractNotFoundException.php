<?php

namespace JobMetric\Url\Exceptions;

use Exception;
use Throwable;

class ModelUrlContractNotFoundException extends Exception
{
    public function __construct(string $model, int $code = 400, ?Throwable $previous = null)
    {
        parent::__construct(trans('url::base.exceptions.model_url_contract_not_found', [
            'model' => $model
        ]), $code, $previous);
    }
}
