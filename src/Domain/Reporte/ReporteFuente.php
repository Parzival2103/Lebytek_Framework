<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Reporte;

/**
 * Fuente reportable declarada por el programador en config/reportes/{key}.json.
 * VO inmutable: expone qué columnas/tratamientos/filtros/periodo puede usar el
 * usuario final, sin tocar la base de datos.
 */
final class ReporteFuente
{
    /** Tratamientos numéricos (no aplican a texto). 'count' aplica a cualquier columna. */
    private const NUMERIC_TYPES = ['money', 'number', 'int', 'integer', 'decimal', 'float'];
    private const NUMERIC_TREATMENTS = ['sum', 'avg', 'min', 'max'];

    /** @param array<string,mixed> $expose @param array<string,mixed> $templates */
    private function __construct(
        private readonly string $key,
        private readonly string $title,
        private readonly string $resource,
        private readonly string $icon,
        private readonly array $columns,
        private readonly array $groupBy,
        private readonly array $orderBy,
        private readonly array $filters,
        private readonly array $period,
        private readonly int $maxRows,
        private readonly array $relations,
        private readonly array $templates,
    ) {}

    /** @param array<string,mixed> $config */
    public static function fromArray(string $key, array $config): self
    {
        $fuente = is_array($config['fuente'] ?? null) ? $config['fuente'] : [];
        $expose = is_array($config['expose'] ?? null) ? $config['expose'] : [];
        $templates = is_array($config['templates'] ?? null) ? $config['templates'] : [];

        $columns = [];
        foreach (is_array($expose['columns'] ?? null) ? $expose['columns'] : [] as $c) {
            if (!is_array($c) || ($c['name'] ?? '') === '') {
                continue;
            }
            $name = (string) $c['name'];
            $columns[$name] = [
                'name'       => $name,
                'label'      => (string) ($c['label'] ?? $name),
                'type'       => (string) ($c['type'] ?? 'text'),
                'treatments' => array_values(array_map('strval', is_array($c['treatments'] ?? null) ? $c['treatments'] : [])),
            ];
        }

        $filters = [];
        foreach (is_array($expose['filters'] ?? null) ? $expose['filters'] : [] as $f) {
            if (is_array($f) && ($f['field'] ?? '') !== '') {
                $filters[(string) $f['field']] = (string) ($f['label'] ?? $f['field']);
            }
        }

        $period = is_array($expose['period'] ?? null) ? $expose['period'] : [];
        $relations = array_values(array_map('strval', is_array($expose['relations'] ?? null) ? $expose['relations'] : []));

        return new self(
            (string) ($fuente['key'] ?? $key),
            (string) ($fuente['title'] ?? $key),
            (string) ($fuente['resource'] ?? ''),
            (string) ($fuente['icon'] ?? 'bi-file-earmark-bar-graph'),
            $columns,
            array_values(array_map('strval', is_array($expose['group_by'] ?? null) ? $expose['group_by'] : [])),
            array_values(array_map('strval', is_array($expose['order_by'] ?? null) ? $expose['order_by'] : [])),
            $filters,
            $period,
            (int) ($expose['max_rows'] ?? 5000),
            $relations,
            [
                'coleccion' => array_values(array_map('strval', is_array($templates['coleccion'] ?? null) ? $templates['coleccion'] : [])),
                'registro'  => array_values(array_map('strval', is_array($templates['registro'] ?? null) ? $templates['registro'] : [])),
            ],
        );
    }

    public function key(): string { return $this->key; }
    public function title(): string { return $this->title; }
    public function resource(): string { return $this->resource; }
    public function icon(): string { return $this->icon; }

    /** @return list<array{name:string,label:string,type:string,treatments:list<string>}> */
    public function columns(): array { return array_values($this->columns); }

    public function hasColumn(string $name): bool { return isset($this->columns[$name]); }
    public function columnLabel(string $name): string { return $this->columns[$name]['label'] ?? $name; }
    public function columnType(string $name): string { return $this->columns[$name]['type'] ?? 'text'; }

    public function allowsTreatment(string $name, string $treatment): bool
    {
        if (!isset($this->columns[$name])) {
            return false;
        }
        if ($treatment === 'count') {
            return in_array('count', $this->columns[$name]['treatments'], true);
        }
        if (!in_array($treatment, self::NUMERIC_TREATMENTS, true)) {
            return false;
        }
        $type = $this->columns[$name]['type'];
        return in_array($type, self::NUMERIC_TYPES, true)
            && in_array($treatment, $this->columns[$name]['treatments'], true);
    }

    /** @return list<string> */
    public function groupBy(): array { return $this->groupBy; }
    /** @return list<string> */
    public function orderBy(): array { return $this->orderBy; }
    public function hasFilter(string $field): bool { return isset($this->filters[$field]); }
    /** @return array<string,string> field => label */
    public function filters(): array { return $this->filters; }

    public function hasPeriod(): bool { return ($this->period['field'] ?? '') !== ''; }
    public function periodField(): string { return (string) ($this->period['field'] ?? ''); }
    public function periodLabel(): string { return (string) ($this->period['label'] ?? 'Fecha'); }
    /** @return list<string> */
    public function periodPresets(): array
    {
        return array_values(array_map('strval', is_array($this->period['presets'] ?? null) ? $this->period['presets'] : []));
    }

    public function maxRows(): int { return $this->maxRows; }

    /** @return list<string> nombres de relaciones CRUD a cargar en modo registro */
    public function relationNames(): array { return $this->relations; }

    /** @return list<string> */
    public function templatesFor(string $mode): array { return $this->templates[$mode] ?? []; }
}
