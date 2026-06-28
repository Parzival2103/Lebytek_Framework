<?php

declare(strict_types=1);

namespace App\Application\Crud\Context;

/**
 * Contexto de una transición de estado.
 * Un guard puede lanzar para bloquear la transición.
 */
final class CrudTransitionContext extends CrudContext
{
    /**
     * @param array<string, mixed>|null $record
     * @param array<string, mixed> $input
     */
    public function __construct(
        string $resourceKey,
        string $table,
        string $primaryKey,
        ?int $userId,
        string $ip,
        private readonly ?array $record,
        private readonly string $statusColumn,
        private readonly string $from,
        private readonly string $to,
        private readonly array $input
    ) {
        parent::__construct($resourceKey, $table, $primaryKey, $userId, $ip);
    }

    /** @return array<string, mixed>|null */
    public function record(): ?array
    {
        return $this->record;
    }

    public function statusColumn(): string
    {
        return $this->statusColumn;
    }

    public function from(): string
    {
        return $this->from;
    }

    public function to(): string
    {
        return $this->to;
    }

    /** @return array<string, mixed> */
    public function input(): array
    {
        return $this->input;
    }
}
