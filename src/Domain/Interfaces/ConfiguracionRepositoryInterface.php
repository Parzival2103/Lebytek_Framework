<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Interfaces;

interface ConfiguracionRepositoryInterface
{
    public function get(string $clave, mixed $default = null): mixed;

    public function set(string $clave, mixed $valor): void;

    public function all(): array;

    public function setMultiple(array $datos): void;
}
