<?php

declare(strict_types=1);

namespace App\Application\Crud\Context;

/**
 * Contexto de una acción de fila (handler/transition/link tipo handler).
 */
final class CrudActionContext extends CrudContext
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
        private readonly int $recordId,
        private readonly ?array $record,
        private readonly string $action,
        private readonly array $input
    ) {
        parent::__construct($resourceKey, $table, $primaryKey, $userId, $ip);
    }

    public function recordId(): int
    {
        return $this->recordId;
    }

    /** @return array<string, mixed>|null */
    public function record(): ?array
    {
        return $this->record;
    }

    public function action(): string
    {
        return $this->action;
    }

    /** @return array<string, mixed> */
    public function input(): array
    {
        return $this->input;
    }
}
