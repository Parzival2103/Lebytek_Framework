<?php
declare(strict_types=1);

namespace App\Application\Reporte;

use App\Domain\Exceptions\ValidationException;
use App\Domain\Reporte\ReporteFuente;

/**
 * Valida la selección del usuario contra la fuente vigente y la serializa a las
 * columnas de rep_reportes (JSON). La config del programador es la fuente de verdad:
 * columnas, tratamientos, filtros, periodo y plantilla deben estar permitidos.
 */
final class GuardarReporteUseCase
{
    public function __construct(
        private readonly ReporteConfigLoader $loader,
    ) {}

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed> columnas de rep_reportes listas para el repositorio
     */
    public function toRow(array $input, int $userId): array
    {
        $fuenteKey = (string) ($input['fuente_key'] ?? '');
        $fuente = $this->loader->load($fuenteKey);

        $errors = [];

        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            $errors[] = 'El nombre del reporte es obligatorio.';
        }

        $templateKey = (string) ($input['template_key'] ?? '');
        if (!in_array($templateKey, $fuente->templatesFor('coleccion'), true)) {
            $errors[] = "La plantilla '{$templateKey}' no está disponible para esta fuente.";
        }

        $columnas = [];
        foreach (is_array($input['columnas'] ?? null) ? $input['columnas'] : [] as $c) {
            $name = is_array($c) ? (string) ($c['name'] ?? '') : (string) $c;
            if (!$fuente->hasColumn($name)) {
                $errors[] = "La columna '{$name}' no está expuesta por la fuente.";
                continue;
            }
            $columnas[] = ['name' => $name, 'label' => $fuente->columnLabel($name), 'type' => $fuente->columnType($name)];
        }
        if ($columnas === []) {
            $errors[] = 'Selecciona al menos una columna.';
        }

        $tratamientos = $this->sanitizeTreatments(is_array($input['tratamientos'] ?? null) ? $input['tratamientos'] : [], $fuente, $errors);

        $filtros = [];
        foreach (is_array($input['filtros'] ?? null) ? $input['filtros'] : [] as $field => $value) {
            $field = (string) $field;
            if ($fuente->hasFilter($field) && $value !== null && $value !== '') {
                $filtros[$field] = (string) $value;
            }
        }

        $preset = (string) (($input['periodo']['preset'] ?? 'todo'));
        if ($fuente->hasPeriod() && !in_array($preset, $fuente->periodPresets(), true)) {
            $errors[] = "El periodo '{$preset}' no está disponible para esta fuente.";
        }

        if ($errors !== []) {
            throw new ValidationException('No se pudo guardar el reporte.', $errors);
        }

        $orientacion = (string) (($input['opciones']['orientacion'] ?? 'portrait'));
        $opciones = [
            'titulo'      => trim((string) (($input['opciones']['titulo'] ?? $nombre))),
            'orientacion' => in_array($orientacion, ['portrait', 'landscape'], true) ? $orientacion : 'portrait',
        ];

        return [
            'nombre'       => $nombre,
            'fuente_key'   => $fuenteKey,
            'modo'         => 'coleccion',
            'columnas'     => json_encode($columnas, JSON_UNESCAPED_UNICODE),
            'tratamientos' => json_encode($tratamientos, JSON_UNESCAPED_UNICODE),
            'filtros'      => json_encode($filtros, JSON_UNESCAPED_UNICODE),
            'periodo'      => json_encode(['preset' => $preset], JSON_UNESCAPED_UNICODE),
            'opciones'     => json_encode($opciones, JSON_UNESCAPED_UNICODE),
            'template_key' => $templateKey,
            'compartido'   => !empty($input['compartido']) ? 1 : 0,
            'created_by'   => $userId,
        ];
    }

    /**
     * @param array<string,mixed> $tratamientos
     * @param list<string> $errors
     * @return array<string,mixed>
     */
    private function sanitizeTreatments(array $tratamientos, ReporteFuente $fuente, array &$errors): array
    {
        $groupBy = [];
        foreach (is_array($tratamientos['group_by'] ?? null) ? $tratamientos['group_by'] : [] as $g) {
            $g = (string) $g;
            if (in_array($g, $fuente->groupBy(), true)) {
                $groupBy[] = $g;
            } else {
                $errors[] = "No se puede agrupar por '{$g}'.";
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
            } else {
                $errors[] = "Tratamiento '{$op}' no permitido en '{$col}'.";
            }
        }

        $order = null;
        if (is_array($tratamientos['order'] ?? null)) {
            $by = (string) ($tratamientos['order']['by'] ?? '');
            $dir = strtolower((string) ($tratamientos['order']['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
            if ($by !== '') {
                $order = ['by' => $by, 'dir' => $dir];
            }
        }

        return ['group_by' => $groupBy, 'aggregations' => $aggs, 'order' => $order];
    }
}
