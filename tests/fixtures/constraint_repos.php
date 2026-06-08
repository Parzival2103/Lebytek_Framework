<?php

declare(strict_types=1);

use App\Domain\Interfaces\CrudConstraintRepositoryInterface;

if (!class_exists('FakeConstraintRepository')) {
    /** Repositorio en memoria para probar CrudDbConstraintValidator sin DB. */
    class FakeConstraintRepository implements CrudConstraintRepositoryInterface
    {
        /** @var array<string, bool> clave "table.column.value[.except]" => bool */
        public array $unique = [];
        /** @var array<string, bool> clave "table.column.value" => bool */
        public array $reference = [];

        public function existsForUnique(string $table, string $column, mixed $value, string $primaryKey, ?int $exceptId): bool
        {
            $key = $table . '.' . $column . '.' . (string) $value . '.' . ($exceptId ?? 'null');

            return $this->unique[$key] ?? false;
        }

        public function existsForReference(string $table, string $column, mixed $value): bool
        {
            return $this->reference[$table . '.' . $column . '.' . (string) $value] ?? false;
        }
    }
}
