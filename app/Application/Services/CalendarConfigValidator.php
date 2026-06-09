<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Exceptions\ValidationException;

final class CalendarConfigValidator
{
    private const VALID_VIEWS = ['month', 'week', 'day', 'table'];
    private const VALID_COLOR_BY = ['estado', 'field', 'fixed'];

    /**
     * @param array<string,mixed> $config
     * @param list<string> $availableColumns columnas conocidas del recurso CRUD
     */
    public function validate(array $config, array $availableColumns): void
    {
        $errors = [];

        $cal = $config['calendar'] ?? null;
        if (!is_array($cal) || ($cal['key'] ?? '') === '' || ($cal['resource'] ?? '') === '') {
            $errors[] = 'calendar.key y calendar.resource son obligatorios.';
        }

        $map = is_array($config['mapping'] ?? null) ? $config['mapping'] : [];
        $start = (string)($map['start'] ?? '');
        if ($start === '') {
            $errors[] = 'mapping.start es obligatorio.';
        } elseif (!in_array($start, $availableColumns, true)) {
            $errors[] = "mapping.start ('{$start}') no existe en el recurso.";
        }

        $end = $map['end'] ?? null;
        if ($end !== null && $end !== '' && !in_array((string)$end, $availableColumns, true)) {
            $errors[] = "mapping.end ('{$end}') no existe en el recurso.";
        }

        $color = is_array($map['color'] ?? null) ? $map['color'] : [];
        $by = (string)($color['by'] ?? 'fixed');
        if (!in_array($by, self::VALID_COLOR_BY, true)) {
            $errors[] = "mapping.color.by inválido ('{$by}').";
        }
        if ($by === 'field' && !in_array((string)($color['field'] ?? ''), $availableColumns, true)) {
            $errors[] = 'mapping.color.by=field requiere un field existente.';
        }

        $views = is_array($config['views'] ?? null) ? $config['views'] : [];
        $enabled = $views['enabled'] ?? [];
        if (!is_array($enabled) || $enabled === []) {
            $errors[] = 'views.enabled debe listar al menos una vista.';
        } else {
            foreach ($enabled as $v) {
                if (!in_array((string)$v, self::VALID_VIEWS, true)) {
                    $errors[] = "views.enabled contiene vista no soportada ('{$v}').";
                }
            }
        }
        $default = (string)($views['default'] ?? '');
        if ($default === '' || (is_array($enabled) && !in_array($default, array_map('strval', $enabled), true))) {
            $errors[] = "views.default ('{$default}') debe estar en views.enabled.";
        }

        foreach ((is_array($config['filters'] ?? null) ? $config['filters'] : []) as $i => $f) {
            if (!is_array($f) || ($f['field'] ?? '') === '') {
                $errors[] = "filters[{$i}].field es obligatorio.";
            } elseif (!in_array((string)$f['field'], $availableColumns, true)) {
                $errors[] = "filters[{$i}].field ('{$f['field']}') no existe en el recurso.";
            }
        }

        if ($errors !== []) {
            throw new ValidationException('Configuración de calendario inválida.', $errors);
        }
    }
}
