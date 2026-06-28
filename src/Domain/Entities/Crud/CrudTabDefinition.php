<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Entities\Crud;

/**
 * Definición inmutable de una pestaña de detalle. Tipos: `fields`, `relation`
 * (hasMany read-only), `component` (vista whitelisteada), `history` (bitácora).
 */
final class CrudTabDefinition
{
    private const TYPES = ['fields', 'relation', 'component', 'history'];

    /** @param list<string> $columns */
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $type,
        private readonly array $columns,
        private readonly string $relation,
        private readonly string $view
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        $type = (string) ($config['type'] ?? 'fields');
        if (!in_array($type, self::TYPES, true)) {
            $type = 'fields';
        }

        $columns = [];
        foreach (($config['columns'] ?? []) as $col) {
            if (is_string($col) && $col !== '') {
                $columns[] = $col;
            }
        }

        return new self(
            key: (string) ($config['key'] ?? ''),
            label: (string) ($config['label'] ?? ($config['key'] ?? '')),
            type: $type,
            columns: $columns,
            relation: (string) ($config['relation'] ?? ''),
            view: (string) ($config['view'] ?? '')
        );
    }

    public function key(): string { return $this->key; }
    public function label(): string { return $this->label; }
    public function type(): string { return $this->type; }
    /** @return list<string> */
    public function columns(): array { return $this->columns; }
    public function relation(): string { return $this->relation; }
    public function view(): string { return $this->view; }

    public function isFields(): bool { return $this->type === 'fields'; }
    public function isRelation(): bool { return $this->type === 'relation'; }
    public function isComponent(): bool { return $this->type === 'component'; }
    public function isHistory(): bool { return $this->type === 'history'; }
}
