<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Reporte;

use Lebytek\Framework\Domain\Exceptions\ValidationException;

/**
 * Valida la configuración de una fuente reportable contra las columnas reales del
 * recurso CRUD. Espejo de CalendarConfigValidator: acumula errores y lanza uno solo.
 */
final class ReporteConfigValidator
{
    private const PROTECTED = [
        'id', 'created_at', 'created_by', 'updated_at', 'updated_by',
        'deleted', 'deleted_at', 'deleted_by',
    ];
    private const NUMERIC_TYPES = ['money', 'number', 'int', 'integer', 'decimal', 'float'];
    private const NUMERIC_TREATMENTS = ['sum', 'avg', 'min', 'max'];
    private const VALID_PRESETS = ['hoy', 'semana', 'mes', 'anio', 'ayer', 'mes_pasado', 'anio_pasado', 'todo'];

    /**
     * @param array<string,mixed> $config
     * @param list<string> $availableColumns columnas conocidas del recurso CRUD
     * @param list<string> $availableRelations relaciones declaradas del recurso CRUD
     */
    public function validate(array $config, array $availableColumns, array $availableRelations = []): void
    {
        $errors = [];
        $known = array_fill_keys($availableColumns, true);

        $fuente = is_array($config['fuente'] ?? null) ? $config['fuente'] : [];
        if (($fuente['key'] ?? '') === '' || ($fuente['resource'] ?? '') === '') {
            $errors[] = 'fuente.key y fuente.resource son obligatorios.';
        }

        $expose = is_array($config['expose'] ?? null) ? $config['expose'] : [];

        $columnTypes = [];
        $cols = is_array($expose['columns'] ?? null) ? $expose['columns'] : [];
        if ($cols === []) {
            $errors[] = 'expose.columns debe listar al menos una columna.';
        }
        foreach ($cols as $i => $c) {
            if (!is_array($c) || ($c['name'] ?? '') === '') {
                $errors[] = "expose.columns[{$i}].name es obligatorio.";
                continue;
            }
            $name = (string) $c['name'];
            $type = (string) ($c['type'] ?? 'text');
            $columnTypes[$name] = $type;

            if (in_array($name, self::PROTECTED, true)) {
                $errors[] = "expose.columns[{$i}] usa la columna protegida '{$name}'.";
            } elseif (!isset($known[$name])) {
                $errors[] = "expose.columns[{$i}] ('{$name}') no existe en el recurso.";
            }

            foreach (is_array($c['treatments'] ?? null) ? $c['treatments'] : [] as $t) {
                $t = (string) $t;
                if ($t === 'count') {
                    continue;
                }
                if (!in_array($t, self::NUMERIC_TREATMENTS, true)) {
                    $errors[] = "Tratamiento inválido '{$t}' en columna '{$name}'.";
                } elseif (!in_array($type, self::NUMERIC_TYPES, true)) {
                    $errors[] = "Tratamiento '{$t}' requiere columna numérica; '{$name}' es '{$type}'.";
                }
            }
        }

        foreach (['group_by', 'order_by'] as $listKey) {
            foreach (is_array($expose[$listKey] ?? null) ? $expose[$listKey] : [] as $col) {
                if (!isset($known[(string) $col])) {
                    $errors[] = "expose.{$listKey} contiene '{$col}', que no existe en el recurso.";
                }
            }
        }

        foreach (is_array($expose['filters'] ?? null) ? $expose['filters'] : [] as $i => $f) {
            $field = is_array($f) ? (string) ($f['field'] ?? '') : '';
            if ($field === '') {
                $errors[] = "expose.filters[{$i}].field es obligatorio.";
            } elseif (!isset($known[$field])) {
                $errors[] = "expose.filters[{$i}].field ('{$field}') no existe en el recurso.";
            }
        }

        $period = is_array($expose['period'] ?? null) ? $expose['period'] : [];
        if ($period !== []) {
            $pf = (string) ($period['field'] ?? '');
            if ($pf === '' || !isset($known[$pf])) {
                $errors[] = "expose.period.field ('{$pf}') no existe en el recurso.";
            }
            foreach (is_array($period['presets'] ?? null) ? $period['presets'] : [] as $p) {
                if (!in_array((string) $p, self::VALID_PRESETS, true)) {
                    $errors[] = "expose.period.presets contiene preset no soportado ('{$p}').";
                }
            }
        }

        if (!isset($expose['max_rows']) || (int) $expose['max_rows'] <= 0) {
            $errors[] = 'expose.max_rows es obligatorio y debe ser mayor que 0.';
        }

        $knownRelations = array_fill_keys($availableRelations, true);
        foreach (is_array($expose['relations'] ?? null) ? $expose['relations'] : [] as $i => $rel) {
            $rel = (string) $rel;
            if ($rel === '' || !isset($knownRelations[$rel])) {
                $errors[] = "expose.relations[{$i}] ('{$rel}') no es una relación declarada del recurso.";
            }
        }

        if ($errors !== []) {
            throw new ValidationException('Configuración de reporte inválida.', $errors);
        }
    }
}
