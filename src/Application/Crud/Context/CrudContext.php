<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Crud\Context;

/**
 * Base inmutable de los contextos del CRUD Engine.
 * Transporta la identidad común del recurso y del actor.
 */
class CrudContext
{
    public function __construct(
        protected readonly string $resourceKey,
        protected readonly string $table,
        protected readonly string $primaryKey,
        protected readonly ?int $userId,
        protected readonly string $ip
    ) {}

    public function resourceKey(): string
    {
        return $this->resourceKey;
    }

    public function table(): string
    {
        return $this->table;
    }

    public function primaryKey(): string
    {
        return $this->primaryKey;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function ip(): string
    {
        return $this->ip;
    }
}
