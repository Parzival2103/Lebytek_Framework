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

    public function resolve(?string $key): ?CrudHookHandlerInterface
    {
        $class = $this->classForKey($key);
        if ($class === null) {
            return null;
        }

        $instance = new $class();
        if (!$instance instanceof CrudHookHandlerInterface) {
            throw new \RuntimeException("Handler {$class} no implementa CrudHookHandlerInterface.");
        }

        return $instance;
    }
}
