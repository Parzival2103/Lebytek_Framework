<?php

declare(strict_types=1);

use App\Domain\Interfaces\CrudRelationRepositoryInterface;

if (!class_exists('FakeRelationRepository')) {
    /** Repositorio de relaciones en memoria para tests sin DB. */
    class FakeRelationRepository implements CrudRelationRepositoryInterface
    {
        /** @var array<string, array<string, string>> */
        public array $options = [];
        /** @var array<string, list<array<string, mixed>>> */
        public array $children = [];
        /** @var list<array<string, mixed>> registro de llamadas a childrenBy */
        public array $childCalls = [];

        public function distinctOptions(string $table, string $valueColumn, string $labelColumn, array $filter, string $orderBy): array
        {
            return $this->options[$table] ?? [];
        }

        public function childrenBy(string $table, string $foreignKey, int $parentId, array $columns, string $orderBy, string $direction, int $limit): array
        {
            $this->childCalls[] = ['table' => $table, 'fk' => $foreignKey, 'parent' => $parentId, 'dir' => $direction, 'limit' => $limit];

            return $this->children[$table] ?? [];
        }
    }
}
