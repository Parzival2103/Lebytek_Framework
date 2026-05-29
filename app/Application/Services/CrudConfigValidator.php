<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\CrudHookHandlerInterface;
use App\Infrastructure\Repositories\GenericCrudRepository;

final class CrudConfigValidator
{
    private const PROTECTED_COLUMNS = [
        'id',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted',
        'deleted_at',
        'deleted_by',
    ];

    private const BLOCKED_PREFIXES = ['auth_', 'cfg_', 'core_', 'log_'];

    public function __construct(
        private readonly GenericCrudRepository $repository,
        private readonly CrudHandlerRegistry $handlerRegistry
    ) {}

    public function validate(array $config): void
    {
        $errors = [];

        $resource = $config['resource'] ?? null;
        if (!is_array($resource)) {
            throw new ValidationException('El bloque "resource" es obligatorio.');
        }

        foreach (['key', 'title', 'table', 'primary_key', 'permission_prefix'] as $requiredKey) {
            if (empty($resource[$requiredKey])) {
                $errors[] = "resource.{$requiredKey} es obligatorio.";
            }
        }

        $table = (string) ($resource['table'] ?? '');
        if ($table !== '') {
            $this->validateTableSecurity($table, $config, $errors);
        }

        if ($table !== '' && !$this->repository->tableExists($table)) {
            $errors[] = "La tabla {$table} no existe en la base de datos.";
        }

        $existingColumns = $table !== '' ? $this->repository->getTableColumns($table) : [];
        $columnLookup    = array_fill_keys($existingColumns, true);

        $primaryKey = (string) ($resource['primary_key'] ?? 'id');
        if ($primaryKey !== '' && !isset($columnLookup[$primaryKey])) {
            $errors[] = "El primary_key {$primaryKey} no existe en {$table}.";
        }

        foreach (($config['form']['fields'] ?? []) as $index => $field) {
            if (!is_array($field)) {
                $errors[] = "form.fields[{$index}] debe ser un objeto.";
                continue;
            }

            $name = (string) ($field['name'] ?? '');
            if ($name === '') {
                $errors[] = "form.fields[{$index}].name es obligatorio.";
                continue;
            }

            if (in_array($name, self::PROTECTED_COLUMNS, true)) {
                $errors[] = "El campo {$name} está protegido y no puede editarse.";
            }

            if ($name !== '' && !isset($columnLookup[$name])) {
                $errors[] = "El campo {$name} no existe en la tabla {$table}.";
            }
        }

        foreach (($config['list']['columns'] ?? []) as $index => $column) {
            if (!is_array($column)) {
                $errors[] = "list.columns[{$index}] debe ser un objeto.";
                continue;
            }

            $name = (string) ($column['name'] ?? '');
            if ($name === '') {
                $errors[] = "list.columns[{$index}].name es obligatorio.";
                continue;
            }

            if (!isset($columnLookup[$name])) {
                $errors[] = "La columna de listado {$name} no existe en {$table}.";
            }
        }

        foreach (($config['list']['filters'] ?? []) as $index => $filter) {
            if (!is_array($filter)) {
                $errors[] = "list.filters[{$index}] debe ser un objeto.";
                continue;
            }
            $field = (string) ($filter['field'] ?? '');
            if ($field === '' || !isset($columnLookup[$field])) {
                $errors[] = "El filtro {$field} no existe en {$table}.";
            }
        }

        $permissionPrefix = (string) ($resource['permission_prefix'] ?? '');
        if ($permissionPrefix !== '') {
            foreach (['ver', 'crear', 'editar', 'eliminar'] as $suffix) {
                $slug = $permissionPrefix . '.' . $suffix;
                if (!$this->repository->permissionExists($slug)) {
                    $errors[] = "El permiso {$slug} no existe en auth_permisos.";
                }
            }
        }

        $this->validateHooks($config, $errors);
        $this->validateListAggregations($config, $columnLookup, $errors);
        $this->validateListAggregationConfig($config, $errors);

        foreach (self::newBlockShapeErrors($config) as $shapeError) {
            $errors[] = $shapeError;
        }

        if (!empty($errors)) {
            throw new ValidationException('La configuración CRUD contiene errores.', $errors);
        }
    }

    /**
     * Scaffolding Fase 0: valida solo la forma de nivel superior de los bloques
     * opcionales nuevos. La validación profunda se agrega en fases posteriores.
     *
     * @param array<string, mixed> $config
     * @return list<string>
     */
    public static function newBlockShapeErrors(array $config): array
    {
        $errors = [];

        if (array_key_exists('actions', $config) && !is_array($config['actions'])) {
            $errors[] = 'actions debe ser un objeto.';
        }

        if (array_key_exists('states', $config)) {
            if (!is_array($config['states'])) {
                $errors[] = 'states debe ser un objeto.';
            } elseif (($config['states']['column'] ?? '') === '') {
                $errors[] = 'states.column es obligatorio cuando se define el bloque states.';
            }
        }

        if (array_key_exists('relations', $config) && !is_array($config['relations'])) {
            $errors[] = 'relations debe ser un objeto.';
        }

        if (array_key_exists('detail', $config) && !is_array($config['detail'])) {
            $errors[] = 'detail debe ser un objeto.';
        }

        $validators = $config['form']['validators'] ?? null;
        if ($validators !== null && !is_array($validators)) {
            $errors[] = 'form.validators debe ser un arreglo.';
        }

        return $errors;
    }

    private function validateTableSecurity(string $table, array $config, array &$errors): void
    {
        $allowCoreTable = (bool) ($config['security']['allow_core_table'] ?? false);
        $mode = (string) ($config['security']['mode'] ?? 'restricted');
        if (!in_array($mode, ['restricted', 'strict'], true)) {
            $errors[] = 'security.mode debe ser "restricted" o "strict".';
        }

        $hasBlockedPrefix = false;
        foreach (self::BLOCKED_PREFIXES as $prefix) {
            if (str_starts_with($table, $prefix)) {
                $hasBlockedPrefix = true;
                break;
            }
        }

        // Tablas core siempre bloqueadas (aunque allow_core_table sea true).
        if ($hasBlockedPrefix) {
            $errors[] = "La tabla {$table} está bloqueada para el CRUD Engine.";
        }

        // Prefijo dom_ siempre permitido (salvo tablas core ya bloqueadas arriba).
        if (str_starts_with($table, 'dom_')) {
            return;
        }

        // Tablas no-dom_*:
        // - restricted: requiere allow_core_table=true (excepción controlada)
        // - strict: no permite tablas no-dom_* aunque allow_core_table=true
        if ($mode === 'strict') {
            $errors[] = "En security.mode=strict solo se permiten tablas con prefijo dom_. Tabla: {$table}.";
            return;
        }

        if (!$allowCoreTable) {
            $errors[] = "La tabla {$table} no usa prefijo dom_ y no fue autorizada (security.allow_core_table).";
        }
    }

    private function validateHooks(array $config, array &$errors): void
    {
        $hooks = $config['hooks'] ?? null;
        if (!is_array($hooks)) {
            return;
        }

        $handler = $hooks['handler'] ?? null;
        if ($handler === null || $handler === '') {
            return;
        }

        if (!is_string($handler)) {
            $errors[] = 'hooks.handler debe ser string o null.';
            return;
        }

        if (str_contains($handler, '\\')) {
            $errors[] = 'hooks.handler no debe ser un FQCN. Usa una clave registrada en config/crud_handlers.php.';
            return;
        }

        if (!preg_match('/^[a-z0-9_-]{1,64}$/', $handler)) {
            $errors[] = 'hooks.handler tiene un formato inválido.';
            return;
        }

        if (!$this->handlerRegistry->hasKey($handler)) {
            $errors[] = "hooks.handler '{$handler}' no está registrado en config/crud_handlers.php.";
            return;
        }

        $class = $this->handlerRegistry->classForKey($handler);
        if ($class === null || !class_exists($class)) {
            $errors[] = "La clase del handler '{$handler}' no existe o no está autoload-eable.";
            return;
        }

        if (!is_subclass_of($class, CrudHookHandlerInterface::class) && !in_array(CrudHookHandlerInterface::class, class_implements($class) ?: [], true)) {
            $errors[] = "El handler '{$handler}' ({$class}) debe implementar CrudHookHandlerInterface.";
        }
    }

    /**
     * @param array<string, true> $columnLookup
     */
    private function validateListAggregations(array $config, array $columnLookup, array &$errors): void
    {
        $list = $config['list'] ?? null;
        if (!is_array($list)) {
            return;
        }

        $groupBy = (string) ($list['group_by'] ?? '');
        if ($groupBy !== '') {
            if (!isset($columnLookup[$groupBy])) {
                $errors[] = "list.group_by ({$groupBy}) no existe en la tabla.";
            }
            if (in_array($groupBy, self::PROTECTED_COLUMNS, true)) {
                $errors[] = 'list.group_by no puede usar columnas protegidas.';
            }
        }

        $summaries = $list['summaries'] ?? null;
        if ($summaries === null) {
            return;
        }
        if (!is_array($summaries)) {
            $errors[] = 'list.summaries debe ser un arreglo.';
            return;
        }

        foreach ($summaries as $index => $summary) {
            if (!is_array($summary)) {
                $errors[] = "list.summaries[{$index}] debe ser un objeto.";
                continue;
            }

            $column = (string) ($summary['column'] ?? '');
            $type = (string) ($summary['type'] ?? '');
            if ($column === '' || !isset($columnLookup[$column])) {
                $errors[] = "list.summaries[{$index}].column inválido o inexistente.";
            }
            if (!in_array($type, ['sum', 'count'], true)) {
                $errors[] = "list.summaries[{$index}].type debe ser sum o count.";
            }
        }
    }

    /**
     * @param list<string> $errors
     */
    private function validateListAggregationConfig(array $config, array &$errors): void
    {
        $list = $config['list'] ?? null;
        if (!is_array($list) || !isset($list['aggregation'])) {
            return;
        }
        $agg = $list['aggregation'];
        if (!is_array($agg)) {
            $errors[] = 'list.aggregation debe ser un objeto.';

            return;
        }
        if (isset($agg['enabled']) && !is_bool($agg['enabled']) && $agg['enabled'] !== 0 && $agg['enabled'] !== 1) {
            $errors[] = 'list.aggregation.enabled debe ser booleano.';
        }
        if (isset($agg['max_rows'])) {
            $mr = (int) $agg['max_rows'];
            if ($mr < 1 || $mr > 500000) {
                $errors[] = 'list.aggregation.max_rows debe estar entre 1 y 500000.';
            }
        }
        if (isset($agg['require_filter_above'])) {
            $rf = (int) $agg['require_filter_above'];
            if ($rf < 0 || $rf > 500000) {
                $errors[] = 'list.aggregation.require_filter_above inválido.';
            }
        }
    }
}
