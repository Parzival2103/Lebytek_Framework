<?php

declare(strict_types=1);

namespace App\Domain\Entities\Crud;

/**
 * Definición inmutable de una relación: `belongsTo` (alimenta selects) o
 * `hasMany` (filas hijas read-only para tabs). `manyToMany` está fuera de alcance.
 */
final class CrudRelationDefinition
{
    /**
     * @param array<string, mixed> $filter
     * @param list<array<string, mixed>> $columns
     */
    public function __construct(
        private readonly string $name,
        private readonly string $type,
        private readonly string $table,
        private readonly string $foreignKey,
        private readonly string $valueColumn,
        private readonly string $labelColumn,
        private readonly array $filter,
        private readonly string $orderBy,
        private readonly string $direction,
        private readonly int $limit,
        private readonly array $columns
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromArray(string $name, array $config): self
    {
        $type = (string) ($config['type'] ?? '');
        $type = in_array($type, ['belongsTo', 'hasMany'], true) ? $type : 'belongsTo';

        $direction = strtoupper((string) ($config['direction'] ?? 'DESC'));
        $direction = $direction === 'ASC' ? 'ASC' : 'DESC';

        $limit = isset($config['limit']) ? (int) $config['limit'] : 50;
        if ($limit < 1) {
            $limit = 50;
        }
        if ($limit > 500) {
            $limit = 500;
        }

        $columns = [];
        foreach (($config['columns'] ?? []) as $col) {
            if (is_array($col) && ($col['name'] ?? '') !== '') {
                $columns[] = $col;
            }
        }

        return new self(
            name: $name,
            type: $type,
            table: (string) ($config['table'] ?? ''),
            foreignKey: (string) ($config['foreign_key'] ?? ''),
            valueColumn: (string) ($config['value'] ?? 'id'),
            labelColumn: (string) ($config['label'] ?? 'nombre'),
            filter: is_array($config['filter'] ?? null) ? $config['filter'] : [],
            orderBy: (string) ($config['order_by'] ?? 'id'),
            direction: $direction,
            limit: $limit,
            columns: $columns
        );
    }

    public function name(): string { return $this->name; }
    public function type(): string { return $this->type; }
    public function isBelongsTo(): bool { return $this->type === 'belongsTo'; }
    public function isHasMany(): bool { return $this->type === 'hasMany'; }
    public function table(): string { return $this->table; }
    public function foreignKey(): string { return $this->foreignKey; }
    public function valueColumn(): string { return $this->valueColumn; }
    public function labelColumn(): string { return $this->labelColumn; }
    /** @return array<string, mixed> */
    public function filter(): array { return $this->filter; }
    public function orderBy(): string { return $this->orderBy; }
    public function direction(): string { return $this->direction; }
    public function limit(): int { return $this->limit; }
    /** @return list<array<string, mixed>> */
    public function columns(): array { return $this->columns; }
}
