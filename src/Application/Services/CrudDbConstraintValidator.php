<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\CrudFieldDefinition;
use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Interfaces\CrudConstraintRepositoryInterface;

/**
 * Aplica constraints de DB declaradas en validation: `unique` y `exists`.
 * Acumula errores por campo (no lanza); el motor los une al resto.
 */
final class CrudDbConstraintValidator
{
    public function __construct(private readonly CrudConstraintRepositoryInterface $repository) {}

    /**
     * @param array<string, mixed> $normalizedByField
     * @return array<string, list<string>>
     */
    public function validate(CrudResourceDefinition $definition, array $normalizedByField, ?int $exceptId): array
    {
        $errors = [];

        foreach ($definition->formFields() as $field) {
            if (!$field instanceof CrudFieldDefinition) {
                continue;
            }
            $rules = $field->validation();
            if (!is_array($rules)) {
                continue;
            }
            $name = $field->name();
            if (!array_key_exists($name, $normalizedByField)) {
                continue;
            }
            $value = $normalizedByField[$name];
            if ($value === null || $value === '') {
                continue;
            }

            if (isset($rules['unique']) && $rules['unique'] !== false) {
                $ignoreSelf = is_array($rules['unique']) && !empty($rules['unique']['ignore_self']);
                $except = $ignoreSelf ? $exceptId : null;
                if ($this->repository->existsForUnique($definition->table(), $name, $value, $definition->primaryKey(), $except)) {
                    $errors[$name][] = $this->message($rules, 'unique', 'Ya existe un registro con este valor.');
                }
            }

            if (isset($rules['exists']) && is_array($rules['exists'])) {
                $table = (string) ($rules['exists']['table'] ?? '');
                $column = (string) ($rules['exists']['column'] ?? 'id');
                if ($table !== '' && !$this->repository->existsForReference($table, $column, $value)) {
                    $errors[$name][] = $this->message($rules, 'exists', 'El valor seleccionado no es válido.');
                }
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $rules */
    private function message(array $rules, string $key, string $default): string
    {
        $messages = $rules['messages'] ?? null;
        if (is_array($messages) && isset($messages[$key]) && is_string($messages[$key]) && $messages[$key] !== '') {
            return $messages[$key];
        }

        return $default;
    }
}
