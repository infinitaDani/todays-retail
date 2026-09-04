<?php

namespace App\Modules\Inventory\Exceptions;

use RuntimeException;

class ContificoApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorType,
        public readonly ?int $httpStatus = null,
        public readonly bool $shouldAbortExecution = false,
    ) {
        parent::__construct($message);
    }
}
