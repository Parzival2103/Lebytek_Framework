<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Entities\Crud;

/**
 * Máquina de estados inmutable parseada del bloque `states` de la metadata.
 * Pura: sin DB, sin efectos. Decide validez de transiciones y expone la
 * presentación (label/badge) de cada estado. Cero `if ($module === 'x')`.
 */
final class CrudStateMachine
{
    /**
     * @param array<string, array{label: string, badge: string}> $values
     * @param array<string, list<string>> $transitions
     */
    public function __construct(
        private readonly string $column,
        private readonly array $values,
        private readonly array $transitions
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        $column = (string) ($config['column'] ?? '');

        $values = [];
        $rawValues = is_array($config['values'] ?? null) ? $config['values'] : [];
        foreach ($rawValues as $state => $meta) {
            $meta = is_array($meta) ? $meta : [];
            $key = (string) $state;
            $values[$key] = [
                'label' => (string) ($meta['label'] ?? $key),
                'badge' => (string) ($meta['badge'] ?? 'secondary'),
            ];
        }

        $transitions = [];
        $rawTransitions = is_array($config['transitions'] ?? null) ? $config['transitions'] : [];
        foreach ($rawTransitions as $from => $targets) {
            $list = [];
            if (is_array($targets)) {
                foreach ($targets as $target) {
                    if (is_string($target) && $target !== '') {
                        $list[] = $target;
                    }
                }
            }
            $transitions[(string) $from] = $list;
        }

        return new self($column, $values, $transitions);
    }

    public function column(): string
    {
        return $this->column;
    }

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, $this->transitions[$from] ?? [], true);
    }

    /** @return list<string> */
    public function allowedFrom(string $state): array
    {
        return $this->transitions[$state] ?? [];
    }

    public function isKnownState(string $state): bool
    {
        return array_key_exists($state, $this->values);
    }

    public function label(string $state): ?string
    {
        return $this->values[$state]['label'] ?? null;
    }

    public function badge(string $state): ?string
    {
        return $this->values[$state]['badge'] ?? null;
    }

    /** @return array<string, array{label: string, badge: string}> */
    public function values(): array
    {
        return $this->values;
    }
}
