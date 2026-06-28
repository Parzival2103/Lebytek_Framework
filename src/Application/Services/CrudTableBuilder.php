<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\CrudResourceDefinition;
use App\Kernel\Helpers\LebytekUiConfig;
use App\Kernel\Helpers\Paginator;

final class CrudTableBuilder
{
    public function build(
        CrudResourceDefinition $definition,
        array $rows,
        Paginator $paginator,
        int $total,
        array $permissions,
        array $query = [],
        string $groupBy = '',
        array $summaryRow = [],
        bool $aggregationSkipped = false,
        ?string $aggregationSkipMessage = null,
        bool $tableCompact = false
    ): array {
        $baseColumns = [];
        foreach ($definition->listColumns() as $column) {
            $baseColumns[(string) ($column['name'] ?? '')] = $column;
        }

        $groupBy = trim($groupBy);
        $summaries = $definition->listSummaries();
        $grouped = $groupBy !== '';

        $columns = [];
        if ($grouped) {
            $groupMeta = $baseColumns[$groupBy] ?? [];
            $columns[] = [
                'name' => $groupBy,
                'label' => (string) ($groupMeta['label'] ?? $groupBy),
                'sortable' => true,
                'format' => (string) ($groupMeta['format'] ?? ''),
                'badge' => is_array($groupMeta['badge'] ?? null) ? $groupMeta['badge'] : [],
            ];

            foreach (is_array($summaries) ? $summaries : [] as $summary) {
                $type = (string) ($summary['type'] ?? '');
                $column = (string) ($summary['column'] ?? '');
                if ($type === 'sum') {
                    $alias = 'crud_sum_' . $column;
                } elseif ($type === 'count') {
                    $alias = 'crud_cnt_' . $column;
                } else {
                    continue;
                }

                $label = (string) ($summary['label'] ?? ($type === 'count' ? 'Conteo' : 'Total ' . $column));
                $columns[] = [
                    'name' => $alias,
                    'label' => $label,
                    'sortable' => true,
                    'format' => (string) ($summary['format'] ?? ''),
                    'badge' => [],
                ];
            }
        } else {
            foreach ($definition->listColumns() as $column) {
                $built = [
                    'name' => (string) ($column['name'] ?? ''),
                    'label' => (string) ($column['label'] ?? ($column['name'] ?? '')),
                    'sortable' => (bool) ($column['sortable'] ?? false),
                    'format' => (string) ($column['format'] ?? ''),
                    'badge' => is_array($column['badge'] ?? null) ? $column['badge'] : [],
                ];
                if (array_key_exists('priority', $column) && is_numeric($column['priority'])) {
                    $built['priority'] = (int) $column['priority'];
                }
                $columns[] = $built;
            }
        }

        $formattedRows = [];
        foreach ($rows as $row) {
            $formattedRows[] = $this->formatRow($row, $columns);
        }

        $formattedSummary = [];
        if ($summaryRow !== []) {
            if ($grouped) {
                $formattedSummary = $this->formatRow($summaryRow, $columns);
            } else {
                $cells = [];
                foreach (is_array($summaries) ? $summaries : [] as $summary) {
                    $type = (string) ($summary['type'] ?? '');
                    $col  = (string) ($summary['column'] ?? '');
                    if ($col === '') {
                        continue;
                    }
                    if ($type === 'sum') {
                        $alias = 'crud_sum_' . $col;
                    } elseif ($type === 'count') {
                        $alias = 'crud_cnt_' . $col;
                    } else {
                        continue;
                    }
                    if (!array_key_exists($alias, $summaryRow)) {
                        continue;
                    }
                    $cells[$col] = $this->formatScalar($summaryRow[$alias], (string) ($summary['format'] ?? ''));
                }
                if ($cells !== []) {
                    $formattedSummary = ['_formatted' => $cells];
                }
            }
        }

        return [
            'resource' => $definition->key(),
            'title' => $definition->title(),
            'columns' => $columns,
            'rows' => $formattedRows,
            'filters' => $definition->listFilters(),
            'actions' => $definition->listActions(),
            'permissions' => $permissions,
            'total' => $total,
            'paginator' => $paginator,
            'query' => $query,
            'primaryKey' => $definition->primaryKey(),
            'grouped' => $grouped,
            'groupBy' => $groupBy,
            'summaryRow' => $formattedSummary,
            'aggregationSkipped' => $aggregationSkipped,
            'aggregationSkipMessage' => $aggregationSkipMessage,
            'tableCompact' => $tableCompact,
        ];
    }

    private function formatRow(array $row, array $columns): array
    {
        $row['_formatted'] = [];
        $row['_badge'] = [];

        foreach ($columns as $column) {
            $name = $column['name'];
            $format = $column['format'];
            $value = $row[$name] ?? null;

            if ($format === 'date' && !empty($value)) {
                $timestamp = strtotime((string) $value);
                $row['_formatted'][$name] = $timestamp ? date('d/m/Y', $timestamp) : $value;
                continue;
            }

            if ($format === 'datetime' && !empty($value)) {
                $timestamp = strtotime((string) $value);
                $row['_formatted'][$name] = $timestamp ? date('d/m/Y H:i', $timestamp) : $value;
                continue;
            }

            if ($format === 'money' && $value !== null && $value !== '') {
                $row['_formatted'][$name] = '$' . number_format((float) $value, 2, '.', ',');
                continue;
            }

            $badgeConfig = is_array($column['badge'] ?? null) ? $column['badge'] : [];
            $colKey = strtolower((string) $name);
            if ($badgeConfig !== [] || in_array($colKey, ['status', 'estado'], true)) {
                if (in_array($colKey, ['status', 'estado'], true)) {
                    $badgeConfig = array_merge(LebytekUiConfig::defaultStatusBadges(), $badgeConfig);
                }
                $badgeValue = strtolower(trim((string) $value));
                $cssClass = (string) ($badgeConfig[$badgeValue] ?? $badgeConfig[(string) $value] ?? 'secondary');
                $row['_badge'][$name] = $cssClass;
            }

            $row['_formatted'][$name] = $value;
        }

        return $row;
    }

    private function formatScalar(mixed $value, string $format): mixed
    {
        if ($format === 'date' && !empty($value)) {
            $timestamp = strtotime((string) $value);
            return $timestamp ? date('d/m/Y', $timestamp) : $value;
        }

        if ($format === 'datetime' && !empty($value)) {
            $timestamp = strtotime((string) $value);
            return $timestamp ? date('d/m/Y H:i', $timestamp) : $value;
        }

        if ($format === 'money' && $value !== null && $value !== '') {
            return '$' . number_format((float) $value, 2, '.', ',');
        }

        if ($format === 'number' && $value !== null && $value !== '') {
            return number_format((float) $value, 0, '.', ',');
        }

        return $value;
    }
}
