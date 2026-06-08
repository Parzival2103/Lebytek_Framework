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

        foreach (self::actionsBlockErrors($config) as $actionError) {
            $errors[] = $actionError;
        }

        foreach (self::statesBlockErrors($config) as $stateError) {
            $errors[] = $stateError;
        }

        foreach (self::validationConstraintShapeErrors($config) as $constraintError) {
            $errors[] = $constraintError;
        }

        // exists: la tabla/columna destino debe existir realmente.
        foreach (($config['form']['fields'] ?? []) as $index => $field) {
            if (!is_array($field) || !is_array($field['validation']['exists'] ?? null)) {
                continue;
            }
            $exTable = (string) ($field['validation']['exists']['table'] ?? '');
            $exColumn = (string) ($field['validation']['exists']['column'] ?? 'id');
            if ($exTable === '') {
                continue; // ya reportado por validationConstraintShapeErrors
            }
            if (!$this->repository->tableExists($exTable)) {
                $errors[] = "form.fields[{$index}].validation.exists: la tabla {$exTable} no existe.";
                continue;
            }
            $exCols = array_fill_keys($this->repository->getTableColumns($exTable), true);
            if (!isset($exCols[$exColumn])) {
                $errors[] = "form.fields[{$index}].validation.exists: la columna {$exColumn} no existe en {$exTable}.";
            }
        }

        // form.validators: cada clave debe estar registrada e implementar la interfaz.
        foreach ((is_array($config['form']['validators'] ?? null) ? $config['form']['validators'] : []) as $vIndex => $validatorKey) {
            if (!is_string($validatorKey) || $validatorKey === '') {
                $errors[] = "form.validators[{$vIndex}] debe ser una clave string no vacía.";
                continue;
            }
            if (!$this->handlerRegistry->hasKey($validatorKey)) {
                $errors[] = "form.validators '{$validatorKey}' no está registrado en config/crud_handlers.php.";
                continue;
            }
            $class = $this->handlerRegistry->classForKey($validatorKey);
            if ($class === null || !class_exists($class)) {
                $errors[] = "La clase del validador '{$validatorKey}' no existe o no es autoload-eable.";
                continue;
            }
            if (!in_array(\App\Domain\Interfaces\CrudValidatorInterface::class, class_implements($class) ?: [], true)) {
                $errors[] = "El validador '{$validatorKey}' ({$class}) debe implementar CrudValidatorInterface.";
            }
        }

        $statesColumn = is_array($config['states'] ?? null) ? (string) ($config['states']['column'] ?? '') : '';
        if ($statesColumn !== '' && $table !== '') {
            if (!isset($columnLookup[$statesColumn])) {
                $errors[] = "states.column ({$statesColumn}) no existe en {$table}.";
            }
            if (in_array($statesColumn, self::PROTECTED_COLUMNS, true)) {
                $errors[] = 'states.column no puede ser una columna protegida.';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('La configuración CRUD contiene errores.', $errors);
        }
    }

    /**
     * Valida la forma del bloque `actions` (Fase 1). Pura, sin DB.
     *
     * @param array<string, mixed> $config
     * @return list<string>
     */
    public static function actionsBlockErrors(array $config): array
    {
        if (!array_key_exists('actions', $config)) {
            return [];
        }
        $actions = $config['actions'];
        if (!is_array($actions)) {
            return ['actions debe ser un objeto.'];
        }

        $errors = [];
        foreach (['row', 'bulk'] as $group) {
            if (!array_key_exists($group, $actions)) {
                continue;
            }
            if (!is_array($actions[$group])) {
                $errors[] = "actions.{$group} debe ser un arreglo.";
                continue;
            }
            foreach ($actions[$group] as $i => $action) {
                if (!is_array($action)) {
                    $errors[] = "actions.{$group}[{$i}] debe ser un objeto.";
                    continue;
                }
                $name = (string) ($action['name'] ?? '');
                $type = (string) ($action['type'] ?? '');
                if ($name === '') {
                    $errors[] = "actions.{$group}[{$i}].name es obligatorio.";
                }
                if (!in_array($type, ['builtin', 'handler', 'link', 'transition'], true)) {
                    $errors[] = "actions.{$group}[{$i}].type inválido ('{$type}').";
                    continue;
                }
                if ($type === 'handler' && ($action['handler'] ?? '') === '') {
                    $errors[] = "actions.{$group}[{$i}] (handler) requiere 'handler'.";
                }
                if ($type === 'link' && ($action['route'] ?? '') === '') {
                    $errors[] = "actions.{$group}[{$i}] (link) requiere 'route'.";
                }
                if ($type === 'transition' && ($action['to'] ?? '') === '') {
                    $errors[] = "actions.{$group}[{$i}] (transition) requiere 'to'.";
                }
                if ($type === 'builtin' && !in_array($name, ['show', 'edit', 'delete'], true)) {
                    $errors[] = "actions.{$group}[{$i}] builtin debe ser show/edit/delete.";
                }
                if (array_key_exists('method', $action)
                    && !in_array(strtoupper((string) $action['method']), ['GET', 'POST'], true)) {
                    $errors[] = "actions.{$group}[{$i}].method debe ser GET o POST.";
                }
            }
        }
        return $errors;
    }

    /**
     * Valida la forma de los constraints `unique`/`exists` por campo. Pura, sin DB.
     *
     * @param array<string, mixed> $config
     * @return list<string>
     */
    public static function validationConstraintShapeErrors(array $config): array
    {
        $errors = [];
        foreach (($config['form']['fields'] ?? []) as $index => $field) {
            if (!is_array($field)) {
                continue;
            }
            $rules = $field['validation'] ?? null;
            if (!is_array($rules)) {
                continue;
            }

            if (array_key_exists('unique', $rules)) {
                $u = $rules['unique'];
                $okShape = $u === true || (is_array($u) && (!array_key_exists('ignore_self', $u) || is_bool($u['ignore_self'])));
                if (!$okShape) {
                    $errors[] = "form.fields[{$index}].validation.unique debe ser true u objeto {ignore_self:true}.";
                }
            }

            if (array_key_exists('exists', $rules)) {
                $e = $rules['exists'];
                if (!is_array($e)) {
                    $errors[] = "form.fields[{$index}].validation.exists debe ser un objeto.";
                    continue;
                }
                $table = (string) ($e['table'] ?? '');
                if ($table === '') {
                    $errors[] = "form.fields[{$index}].validation.exists.table es obligatorio.";
                    continue;
                }
                foreach (self::BLOCKED_PREFIXES as $prefix) {
                    if (str_starts_with($table, $prefix)) {
                        $errors[] = "form.fields[{$index}].validation.exists.table ({$table}) usa un prefijo bloqueado.";
                        break;
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Valida la forma del bloque `states` y su consistencia con las acciones de
     * transición (Fase 2). Pura, sin DB. NO re-emite "states.column es
     * obligatorio" — eso lo cubre newBlockShapeErrors().
     *
     * @param array<string, mixed> $config
     * @return list<string>
     */
    public static function statesBlockErrors(array $config): array
    {
        if (!array_key_exists('states', $config)) {
            return [];
        }
        $states = $config['states'];
        if (!is_array($states)) {
            return ['states debe ser un objeto.'];
        }

        $errors = [];

        $values = $states['values'] ?? null;
        $stateKeys = [];
        if (!is_array($values) || $values === []) {
            $errors[] = 'states.values debe ser un objeto con al menos un estado.';
        } else {
            foreach ($values as $state => $meta) {
                $stateKeys[(string) $state] = true;
                if (!is_array($meta)) {
                    $errors[] = "states.values.{$state} debe ser un objeto.";
                }
            }
        }

        $transitions = $states['transitions'] ?? null;
        if ($transitions !== null) {
            if (!is_array($transitions)) {
                $errors[] = 'states.transitions debe ser un objeto.';
            } else {
                foreach ($transitions as $from => $targets) {
                    if (!isset($stateKeys[(string) $from])) {
                        $errors[] = "states.transitions tiene un estado origen desconocido: '{$from}'.";
                    }
                    if (!is_array($targets)) {
                        $errors[] = "states.transitions.{$from} debe ser un arreglo de estados.";
                        continue;
                    }
                    foreach ($targets as $target) {
                        if (!is_string($target) || !isset($stateKeys[$target])) {
                            $shown = is_string($target) ? $target : gettype($target);
                            $errors[] = "states.transitions.{$from} referencia un estado destino desconocido: '{$shown}'.";
                        }
                    }
                }
            }
        }

        // Acciones type=transition deben apuntar a un estado declarado en values.
        $actions = $config['actions'] ?? null;
        if (is_array($actions) && is_array($actions['row'] ?? null)) {
            foreach ($actions['row'] as $i => $action) {
                if (!is_array($action) || ($action['type'] ?? '') !== 'transition') {
                    continue;
                }
                $to = (string) ($action['to'] ?? '');
                if ($to !== '' && $stateKeys !== [] && !isset($stateKeys[$to])) {
                    $errors[] = "actions.row[{$i}] (transition) apunta a un estado desconocido: '{$to}'.";
                }
            }
        }

        return $errors;
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
