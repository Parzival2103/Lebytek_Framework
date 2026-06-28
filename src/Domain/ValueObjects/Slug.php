<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\ValueObjects;

use Lebytek\Framework\Domain\Exceptions\ValidationException;

/*
|--------------------------------------------------------------------------
| Slug — Value Object para identificadores URL-safe
|--------------------------------------------------------------------------
*/

final class Slug
{
    private string $value;

    public function __construct(string $value)
    {
        $value = strtolower(trim($value));

        if (!preg_match('/^[a-z0-9_\-\.]+$/', $value)) {
            throw new ValidationException("El slug '{$value}' contiene caracteres no permitidos.");
        }

        if (strlen($value) < 2 || strlen($value) > 60) {
            throw new ValidationException("El slug debe tener entre 2 y 60 caracteres.");
        }

        $this->value = $value;
    }

    public static function fromString(string $text): self
    {
        $slug = strtolower($text);
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT', $slug);
        $slug = preg_replace('/[^a-z0-9\s\-]/', '', $slug);
        $slug = preg_replace('/[\s\-]+/', '_', $slug);
        $slug = trim($slug, '_');

        return new self($slug);
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
