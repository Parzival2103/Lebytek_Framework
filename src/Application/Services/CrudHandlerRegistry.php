<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Interfaces\CrudHookHandlerInterface;

final class CrudHandlerRegistry
{
    /**
     * @param array<string, class-string> $map clave simple => FQCN de clase que implementa CrudHookHandlerInterface
     */
    public function __construct(private readonly array $map) {}

    /**
     * @return class-string|null
     */
    public function classForKey(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        return $this->map[$key] ?? null;
    }

    public function hasKey(string $key): bool
    {
        return isset($this->map[$key]);
    }

    /**
     * Resuelve la clave a una instancia y valida que implemente la interfaz
     * esperada. Devuelve null si la clave no está registrada.
     *
     * @param class-string $expectedInterface
     */
    public function resolve(?string $key, string $expectedInterface = CrudHookHandlerInterface::class): ?object
    {
        $class = $this->classForKey($key);
        if ($class === null) {
            return null;
        }

        $instance = new $class();
        if (!$instance instanceof $expectedInterface) {
            throw new \RuntimeException("El handler '{$key}' ({$class}) no implementa {$expectedInterface}.");
        }

        return $instance;
    }
}
