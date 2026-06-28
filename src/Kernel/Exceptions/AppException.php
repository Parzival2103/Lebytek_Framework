<?php

declare(strict_types=1);

namespace Lebytek\Framework\Kernel\Exceptions;

/*
|--------------------------------------------------------------------------
| AppException — Excepción base de la aplicación
|--------------------------------------------------------------------------
*/

class AppException extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
