<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Exceptions;

use Lebytek\Framework\Kernel\Exceptions\AppException;

class ValidationException extends AppException
{
    private array $errors;

    public function __construct(string $message = '', array $errors = [], int $code = 422)
    {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
}
