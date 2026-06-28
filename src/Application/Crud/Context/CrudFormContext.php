<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Crud\Context;

/**
 * Contexto para el hook beforeRenderForm: permite a un handler sembrar
 * opciones de campos y valores por defecto antes de renderizar el formulario.
 */
final class CrudFormContext extends CrudContext
{
    /** @var array<string, mixed> */
    private array $fieldOptions = [];

    /** @var array<string, mixed> */
    private array $fieldValues = [];

    /** @param array<string, mixed>|null $record */
    public function __construct(
        string $resourceKey,
        string $table,
        string $primaryKey,
        ?int $userId,
        string $ip,
        private readonly bool $isEdit,
        private readonly ?array $record
    ) {
        parent::__construct($resourceKey, $table, $primaryKey, $userId, $ip);
    }

    public function isEdit(): bool
    {
        return $this->isEdit;
    }

    /** @return array<string, mixed>|null */
    public function record(): ?array
    {
        return $this->record;
    }

    /** @param array<int, mixed> $options */
    public function setFieldOptions(string $field, array $options): void
    {
        $this->fieldOptions[$field] = $options;
    }

    public function setFieldValue(string $field, mixed $value): void
    {
        $this->fieldValues[$field] = $value;
    }

    /** @return array<string, mixed> */
    public function fieldOptions(): array
    {
        return $this->fieldOptions;
    }

    /** @return array<string, mixed> */
    public function fieldValues(): array
    {
        return $this->fieldValues;
    }
}
