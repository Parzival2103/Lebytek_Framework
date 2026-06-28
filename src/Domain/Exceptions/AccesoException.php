<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Exceptions;

use Lebytek\Framework\Kernel\Exceptions\AppException;

class AccesoException extends AppException
{
    public function __construct(string $message = 'Acceso denegado.', int $code = 403)
    {
        parent::__construct($message, $code);
    }
}
