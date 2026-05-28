<?php

declare(strict_types=1);

namespace App\Application\Crud\Context;

/**
 * Contexto para validadores de formulario externos (CrudValidatorInterface).
 * Los errores se acumulan y luego el motor los convierte en ValidationException.
 */
final class CrudValidationContext extends CrudContext
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $normalized
     * @param array<string, mixed>|null $record
     */
    public function __construct(
        string $resourceKey,
        string $table,
        string $primaryKey,
        ?int $userId,
        string $ip,
        private readonly array $input,
        private readonly array $normalized,
        private readonly ?array $record,
        private readonly bool $isEdit
    ) {
        parent::__construct($resourceKey, $table, $primaryKey, $userId, $ip);
    }

    /** @return array<string, mixed> */
    public function input(): array
    {
        return $this->input;
    }

    /** @return array<string, mixed> */
    public function normalized(): array
    {
        return $this->normalized;
    }

    /** @return array<string, mixed>|null */
    public function record(): ?array
    {
        return $this->record;
    }

    public function isEdit(): bool
    {
        return $this->isEdit;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
