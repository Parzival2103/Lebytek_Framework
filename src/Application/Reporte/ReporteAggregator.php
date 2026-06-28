<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Reporte;

/**
 * Aplica tratamientos (agrupar / sum / count / avg / min / max / ordenar) a filas
 * crudas y produce columnas, filas y totales listos para PdfDataTable/PdfTotalsBlock.
 * Puro: sin BD, sin estado. La agregación ocurre en PHP sobre lo que ya devolvió el
 * recurso CRUD (mismo principio que list.summaries del CRUD Engine).
 */
final class ReporteAggregator
{
    private const NUMERIC_TYPES = ['money', 'number', 'int', 'integer', 'decimal', 'float'];
    private const OP_LABELS = [
        'sum'   => 'Suma de',
        'avg'   => 'Promedio de',
        'min'   => 'Mínimo de',
        'max'   => 'Máximo de',
        'count' => 'Cantidad',
    ];

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{name:string,label:string,type:string}> $columns
     * @param array<string,mixed> $tratamientos
     * @return array{columns:list<array{name:string,label:string,format:string}>,rows:list<array<string,mixed>>,totals:list<array{label:string,value:mixed,format:string}>}
     */
    public function apply(array $rows, array $columns, array $tratamientos): array
    {
        $byName = [];
        foreach ($columns as $c) {
            $byName[(string) $c['name']] = $c;
        }

        $groupBy = array_values(array_map('strval', is_array($tratamientos['group_by'] ?? null) ? $tratamientos['group_by'] : []));
        $aggs = is_array($tratamientos['aggregations'] ?? null) ? $tratamientos['aggregations'] : [];
        $order = is_array($tratamientos['order'] ?? null) ? $tratamientos['order'] : null;

        if ($groupBy === [] && $aggs === []) {
            return $this->plainList($rows, $columns, $order);
        }

        return $this->grouped($rows, $byName, $groupBy, $aggs, $order);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{name:string,label:string,type:string}> $columns
     * @param array<string,mixed>|null $order
     */
    private function plainList(array $rows, array $columns, ?array $order): array
    {
        $outCols = [];
        foreach ($columns as $c) {
            $outCols[] = [
                'name'   => (string) $c['name'],
                'label'  => (string) $c['label'],
                'format' => $this->formatFor((string) ($c['type'] ?? 'text')),
            ];
        }

        $names = array_map(static fn(array $c): string => (string) $c['name'], $columns);
        $outRows = [];
        foreach ($rows as $row) {
            $picked = [];
            foreach ($names as $n) {
                $picked[$n] = $row[$n] ?? '';
            }
            $outRows[] = $picked;
        }

        $outRows = $this->sortRows($outRows, $order);

        return ['columns' => $outCols, 'rows' => $outRows, 'totals' => []];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,array{name:string,label:string,type:string}> $byName
     * @param list<string> $groupBy
     * @param list<mixed> $aggs
     * @param array<string,mixed>|null $order
     */
    private function grouped(array $rows, array $byName, array $groupBy, array $aggs, ?array $order): array
    {
        $outCols = [];
        foreach ($groupBy as $g) {
            $outCols[] = [
                'name'   => $g,
                'label'  => (string) ($byName[$g]['label'] ?? $g),
                'format' => $this->formatFor((string) ($byName[$g]['type'] ?? 'text')),
            ];
        }

        $normAggs = [];
        foreach ($aggs as $a) {
            if (!is_array($a)) {
                continue;
            }
            $op = (string) ($a['op'] ?? '');
            if (!isset(self::OP_LABELS[$op])) {
                continue;
            }
            $col = (string) ($a['column'] ?? '');
            $name = $op === 'count' || $col === '' ? ($op === 'count' ? 'count' : $op) : $op . '_' . $col;
            $label = $op === 'count'
                ? self::OP_LABELS['count']
                : self::OP_LABELS[$op] . ' ' . (string) ($byName[$col]['label'] ?? $col);
            $format = $op === 'count'
                ? 'number'
                : $this->formatFor((string) ($byName[$col]['type'] ?? 'number'));

            $normAggs[] = ['op' => $op, 'column' => $col, 'name' => $name, 'label' => $label, 'format' => $format];
            $outCols[] = ['name' => $name, 'label' => $label, 'format' => $format];
        }

        $buckets = [];
        foreach ($rows as $row) {
            $keyParts = [];
            foreach ($groupBy as $g) {
                $keyParts[] = (string) ($row[$g] ?? '');
            }
            $buckets[implode("\x1f", $keyParts)][] = $row;
        }

        $outRows = [];
        foreach ($buckets as $bucketRows) {
            $first = $bucketRows[0];
            $outRow = [];
            foreach ($groupBy as $g) {
                $outRow[$g] = $first[$g] ?? '';
            }
            foreach ($normAggs as $agg) {
                $outRow[$agg['name']] = $this->compute($agg['op'], $agg['column'], $bucketRows);
            }
            $outRows[] = $outRow;
        }

        $outRows = $this->sortRows($outRows, $order);

        $totals = [];
        foreach ($normAggs as $agg) {
            $totals[] = [
                'label'  => $agg['label'],
                'value'  => $this->compute($agg['op'], $agg['column'], $rows),
                'format' => $agg['format'],
            ];
        }

        return ['columns' => $outCols, 'rows' => $outRows, 'totals' => $totals];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return float|int
     */
    private function compute(string $op, string $column, array $rows)
    {
        if ($op === 'count') {
            return count($rows);
        }

        $values = [];
        foreach ($rows as $row) {
            if (array_key_exists($column, $row) && $row[$column] !== null && $row[$column] !== '') {
                $values[] = (float) $row[$column];
            }
        }
        if ($values === []) {
            return 0.0;
        }

        return match ($op) {
            'sum' => array_sum($values),
            'avg' => array_sum($values) / count($values),
            'min' => min($values),
            'max' => max($values),
            default => 0.0,
        };
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed>|null $order
     * @return list<array<string,mixed>>
     */
    private function sortRows(array $rows, ?array $order): array
    {
        $by = (string) ($order['by'] ?? '');
        if ($by === '') {
            return $rows;
        }
        $dir = strtolower((string) ($order['dir'] ?? 'asc')) === 'desc' ? -1 : 1;

        usort($rows, static function (array $a, array $b) use ($by, $dir): int {
            $va = $a[$by] ?? null;
            $vb = $b[$by] ?? null;
            if (is_numeric($va) && is_numeric($vb)) {
                return ((float) $va <=> (float) $vb) * $dir;
            }
            return strcmp((string) $va, (string) $vb) * $dir;
        });

        return $rows;
    }

    private function formatFor(string $type): string
    {
        if (in_array($type, self::NUMERIC_TYPES, true)) {
            return $type === 'money' ? 'money' : 'number';
        }
        return match ($type) {
            'date'     => 'date',
            'datetime' => 'datetime',
            default    => 'raw',
        };
    }
}
