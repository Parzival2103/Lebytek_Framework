<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Reporte;

use Lebytek\Framework\Domain\Reporte\ReporteDataSourceInterface;
use Lebytek\Framework\Domain\Reporte\ReporteFuente;
use Lebytek\Framework\Domain\Reporte\ReporteGuardado;

/**
 * Construye el payload de datos de un reporte de colección: interseca la selección
 * guardada con el expose vigente (config = fuente de verdad), resuelve el periodo,
 * lee filas con scope, recorta a max_rows y agrega. No conoce PDF ni marca.
 */
final class BuildReporteDataUseCase
{
    public function __construct(
        private readonly ReporteConfigLoader $loader,
        private readonly ReporteDataSourceInterface $dataSource,
        private readonly PeriodoResolver $periodos,
        private readonly ReporteAggregator $aggregator,
    ) {}

    /**
     * @return array{title:string,period:string,orientation:string,columns:list<array{name:string,label:string,format:string}>,rows:list<array<string,mixed>>,totals:list<array{label:string,value:mixed,format:string}>,capped:bool}
     */
    public function build(ReporteGuardado $reporte, ?int $userId, callable $can): array
    {
        $fuente = $this->loader->load($reporte->fuenteKey());
        $definition = $this->loader->crudDefinition($fuente->resource());

        $columns = [];
        foreach ($reporte->columnas() as $c) {
            $name = (string) ($c['name'] ?? '');
            if ($fuente->hasColumn($name)) {
                $columns[] = ['name' => $name, 'label' => $fuente->columnLabel($name), 'type' => $fuente->columnType($name)];
            }
        }

        $tratamientos = $this->intersectTreatments($reporte->tratamientos(), $fuente);

        $filtros = [];
        foreach ($reporte->filtros() as $field => $value) {
            $field = (string) $field;
            if ($fuente->hasFilter($field) && $value !== null && $value !== '') {
                $filtros[$field] = $value;
            }
        }

        $preset = (string) ($reporte->periodo()['preset'] ?? 'todo');
        $range = $this->periodos->resolve($preset);

        $rows = $this->dataSource->rows(
            $definition,
            $fuente->periodField(),
            $range['from'],
            $range['to'],
            $userId,
            $can,
            $filtros
        );

        $capped = false;
        if (count($rows) > $fuente->maxRows()) {
            $rows = array_slice($rows, 0, $fuente->maxRows());
            $capped = true;
        }

        $agg = $this->aggregator->apply($rows, $columns, $tratamientos);

        return [
            'title'       => (string) ($reporte->opciones()['titulo'] ?? $reporte->nombre()),
            'period'      => $range['label'],
            'orientation' => (string) ($reporte->opciones()['orientacion'] ?? 'portrait'),
            'columns'     => $agg['columns'],
            'rows'        => $agg['rows'],
            'totals'      => $agg['totals'],
            'capped'      => $capped,
        ];
    }

    /**
     * @param array<string,mixed> $tratamientos
     * @return array<string,mixed>
     */
    private function intersectTreatments(array $tratamientos, ReporteFuente $fuente): array
    {
        $groupBy = [];
        foreach (is_array($tratamientos['group_by'] ?? null) ? $tratamientos['group_by'] : [] as $g) {
            if (in_array((string) $g, $fuente->groupBy(), true)) {
                $groupBy[] = (string) $g;
            }
        }

        $aggs = [];
        foreach (is_array($tratamientos['aggregations'] ?? null) ? $tratamientos['aggregations'] : [] as $a) {
            if (!is_array($a)) {
                continue;
            }
            $op = (string) ($a['op'] ?? '');
            $col = (string) ($a['column'] ?? '');
            if ($op === 'count' && $col === '') {
                $aggs[] = ['op' => 'count', 'column' => ''];
            } elseif ($fuente->allowsTreatment($col, $op)) {
                $aggs[] = ['op' => $op, 'column' => $col];
            }
        }

        $order = null;
        if (is_array($tratamientos['order'] ?? null)) {
            $by = (string) ($tratamientos['order']['by'] ?? '');
            if ($by !== '' && (in_array($by, $groupBy, true) || in_array($by, $fuente->orderBy(), true) || str_contains($by, '_'))) {
                $order = ['by' => $by, 'dir' => (string) ($tratamientos['order']['dir'] ?? 'asc')];
            }
        }

        return ['group_by' => $groupBy, 'aggregations' => $aggs, 'order' => $order];
    }
}
