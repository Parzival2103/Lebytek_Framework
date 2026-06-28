<?php

declare(strict_types=1);

namespace Lebytek\Framework\Kernel\Container;

final class Container
{
    private array $bindings  = [];
    private array $instances = [];

    public function bind(string $id, \Closure $factory): void
    {
        $this->bindings[$id] = $factory;
    }

    public function singleton(string $id, \Closure $factory): void
    {
        $this->bindings[$id] = function () use ($id, $factory) {
            if (!isset($this->instances[$id])) {
                $this->instances[$id] = $factory($this);
            }
            return $this->instances[$id];
        };
    }

    public function get(string $id): mixed
    {
        if (isset($this->bindings[$id])) {
            return ($this->bindings[$id])($this);
        }

        if (class_exists($id)) {
            return new $id();
        }

        throw new \RuntimeException("No se encontró binding para: {$id}");
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]);
    }
}
