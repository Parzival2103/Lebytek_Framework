<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Kernel\Exceptions\AppException;

class AccesoException extends AppException
{
    public function __construct(string $message = 'Acceso denegado.', int $code = 403)
    {
        parent::__construct($message, $code);
    }
}
