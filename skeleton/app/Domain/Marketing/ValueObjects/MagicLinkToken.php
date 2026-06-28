<?php

declare(strict_types=1);

namespace App\Domain\Marketing\ValueObjects;

final class MagicLinkToken
{
    private function __construct(private readonly string $valor) {}

    public static function generar(): self
    {
        return new self(bin2hex(random_bytes(32)));
    }

    public static function desde(string $valor): self
    {
        return new self($valor);
    }

    public static function esFormatoValido(string $valor): bool
    {
        return preg_match('/^[0-9a-f]{64}$/', $valor) === 1;
    }

    public function valor(): string
    {
        return $this->valor;
    }
}
