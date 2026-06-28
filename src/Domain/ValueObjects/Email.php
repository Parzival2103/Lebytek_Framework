<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use App\Domain\Exceptions\ValidationException;

/*
|--------------------------------------------------------------------------
| Email — Value Object inmutable para dirección de correo
|--------------------------------------------------------------------------
*/

final class Email
{
    private string $value;

    public function __construct(string $value)
    {
        $value = strtolower(trim($value));

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException("El correo '{$value}' no tiene un formato válido.");
        }

        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
