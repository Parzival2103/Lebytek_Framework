<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Crud\Context;

/**
 * Contexto de escritura (create/update/delete).
 * `input` y `record` son de solo lectura; `data` es mutable y el motor lo
 * relee tras beforeCreate/beforeUpdate para persistir las mutaciones del handler.
 */
final class CrudWriteContext extends CrudContext
{
    /** @var array<string, mixed> */
    private array $data;

    /**
     * @param array<string, mixed> $input  Entrada cruda del usuario (solo lectura).
     * @param array<string, mixed>|null $record  Fila existente (update/delete) o null (create).
     * @param array<string, mixed> $data  Payload a persistir (mutable).
     */
    public function __construct(
        string $resourceKey,
        string $table,
        string $primaryKey,
        ?int $userId,
        string $ip,
        private readonly array $input,
        private readonly ?array $record,
        private ?int $recordId,
        array $data,
        private readonly bool $isCreate
    ) {
        parent::__construct($resourceKey, $table, $primaryKey, $userId, $ip);
        $this->data = $data;
    }

    /** @return array<string, mixed> */
    public function input(): array
    {
        return $this->input;
    }

    /** @return array<string, mixed>|null */
    public function record(): ?array
    {
        return $this->record;
    }

    public function recordId(): ?int
    {
        return $this->recordId;
    }

    public function setRecordId(?int $recordId): void
    {
        $this->recordId = $recordId;
    }

    public function isCreate(): bool
    {
        return $this->isCreate;
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->data;
    }

    /** @param array<string, mixed> $data */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /** @param array<string, mixed> $patch */
    public function mergeData(array $patch): void
    {
        $this->data = array_merge($this->data, $patch);
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }
}
