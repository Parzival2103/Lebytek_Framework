<?php

declare(strict_types=1);

namespace App\Kernel\Exceptions;

class HttpException extends AppException
{
    public function __construct(string $message = '', int $httpStatus = 500, ?\Throwable $previous = null)
    {
        parent::__construct($message, $httpStatus, $previous);
    }

    public function getHttpStatus(): int
    {
        return $this->getCode();
    }
}
