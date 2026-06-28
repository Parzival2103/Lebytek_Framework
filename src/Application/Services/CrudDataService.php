<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Crud\Context\CrudListContext;
use App\Application\Crud\Context\CrudValidationContext;
use App\Application\Crud\Context\CrudWriteContext;
use App\Domain\Entities\CrudFieldDefinition;
use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\BitacoraRepositoryInterface;
use App\Domain\Interfaces\CrudValidatorInterface;
use App\Infrastructure\Repositories\GenericCrudRepository;
use App\Kernel\Config\Config;
use App\Kernel\Helpers\Paginator;
use App\Kernel\Logging\AppLogger;

final class CrudDataService
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

    public function __construct(
        private readonly GenericCrudRepository $repository,
        private readonly BitacoraRepositoryInterface $bitacoraRepository,
        private readonly CrudHookRunner $hookRunner,
        private readonly CrudFieldValidationService $fieldValidation,
        private readonly ?CrudDbConstraintValidator $dbConstraintValidator = null,
        private readonly ?CrudHandlerRegistry $handlerRegistry = null,
        private readonly ?CrudScopeResolver $scopeResolver = null,
        private readonly ?FileUploadService $fileUploadService = null
    ) {}

    public function list(CrudResourceDefinition $definition, array $query, ?int $userId = null, ?callable $can = null): array
    {
        $columns = $definition->listColumns();
        $selectColumns = array_values(array_unique(array_map(
            static fn(array $column): string => (string) ($column['name'] ?? ''),
            $columns
        )));

        $selectColumns[] = $definition->primaryKey();
        $selectColumns[] = 'deleted';
        $selectColumns = array_values(array_filter(array_unique($selectColumns)));

        $pagina  = max(1, (int) ($query['pagina'] ?? 1));
        $perPage = max(1, min(100, (int) ($query['por_pagina'] ?? 15)));

        $searchTerm = trim((string) ($query['buscar'] ?? ''));
        $groupBy = trim($definition->listGroupBy());
        $summaries = $definition->listSummaries();
        $aggregationSkipped = false;
        $aggregationSkipMessage = null;

        $orderBy = (string) ($query['orden'] ?? ($groupBy !== '' ? $groupBy : $definition->primaryKey()));
        $direction = strtoupper((string) ($query['direccion'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $where = ['deleted = 0'];
        $params = [];

        if ($searchTerm !== '') {
            $searchable = [];
            foreach ($definition->listColumns() as $column) {
                if (!empty($column['searchable'])) {
                    $searchable[] = (string) $column['name'];
                }
            }

            if ($groupBy !== '') {
                $searchable[] = $groupBy;
            }

            if (!empty($searchable)) {
                $searchParts = [];
                foreach ($searchable as $columnName) {
                    $searchParts[] = '`' . $columnName . '` LIKE ?';
                    $params[] = '%' . $searchTerm . '%';
                }
                $where[] = '(' . implode(' OR ', $searchParts) . ')';
            }
        }

        foreach ($definition->listFilters() as $filter) {
            $field = (string) ($filter['field'] ?? '');
            if ($field === '') {
                continue;
            }
            $value = $query['f_' . $field] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $where[] = '`' . $field . '` = ?';
            $params[] = $value;
        }

        $this->applyScopeConditions($definition, $query, $userId, $can, $where, $params);

        $candidateCount = $this->repository->countFiltered($definition->table(), $where, $params);
        $aggConfig = $definition->listAggregation();
        $needsHeavyAggregation = ($groupBy !== '') || (is_array($summaries) && $summaries !== []);

        if ($needsHeavyAggregation && $aggConfig['enabled']) {
            $maxRows = (int) $aggConfig['max_rows'];
            $requireAbove = $aggConfig['require_filter_above'];
            $hasUserFilters = $this->hasActiveListFilters($definition, $query, $searchTerm);

            $shouldSkip = false;
            if ($requireAbove !== null && $candidateCount > $requireAbove && !$hasUserFilters) {
                $shouldSkip = true;
            } elseif ($candidateCount > $maxRows) {
                $shouldSkip = true;
            }

            if ($shouldSkip) {
                $aggregationSkipped = true;
                $aggregationSkipMessage = 'Las sumas se omitieron por volumen de datos. Aplica filtros para calcularlas.';
                AppLogger::warning('CRUD: agregación omitida por límite de filas', [
                    'resource' => $definition->key(),
                    'table' => $definition->table(),
                    'candidate_rows' => $candidateCount,
                    'max_rows' => $maxRows,
                    'require_filter_above' => $requireAbove,
                    'has_user_filters' => $hasUserFilters,
                ]);
                $groupBy = '';
                $summaries = [];
            }
        }

        $paginator = new Paginator(
            total: 0,
            perPage: $perPage,
            currentPage: $pagina,
            baseUrl: '/admin/crud/' . $definition->key()
        );

        if ($groupBy !== '') {
            $availableOrder = array_fill_keys(array_merge([$groupBy], array_map(
                static fn(array $s): string => match ((string) ($s['type'] ?? '')) {
                    'sum' => 'crud_sum_' . (string) ($s['column'] ?? ''),
                    'count' => 'crud_cnt_' . (string) ($s['column'] ?? ''),
                    default => '',
                },
                is_array($summaries) ? $summaries : []
            )), true);
            unset($availableOrder['']);

            if (!isset($availableOrder[$orderBy])) {
                $orderBy = $groupBy;
            }

            $result = $this->repository->selectGroupedAggregates(
                table: $definition->table(),
                groupBy: $groupBy,
                summaries: is_array($summaries) ? $summaries : [],
                whereSqlParts: $where,
                params: $params,
                orderDirection: $direction,
                limit: $perPage,
                offset: $paginator->offset()
            );

            $summaryRow = [];
            if (is_array($summaries) && $summaries !== []) {
                $summaryRow = $this->repository->selectGlobalAggregates(
                    table: $definition->table(),
                    summaries: $summaries,
                    whereSqlParts: $where,
                    params: $params
                );
            }
        } else {
            $availableOrderColumns = array_fill_keys($selectColumns, true);
            if (!isset($availableOrderColumns[$orderBy])) {
                $orderBy = $definition->primaryKey();
            }

            $result = $this->repository->selectPaginated(
                table: $definition->table(),
                selectColumns: $selectColumns,
                whereSqlParts: $where,
                params: $params,
                orderBy: $orderBy,
                orderDirection: $direction,
                limit: $perPage,
                offset: $paginator->offset()
            );

            $summaryRow = [];
            if (is_array($summaries) && $summaries !== []) {
                $summaryRow = $this->repository->selectGlobalAggregates(
                    table: $definition->table(),
                    summaries: $summaries,
                    whereSqlParts: $where,
                    params: $params
                );
            }
        }

        $paginator = new Paginator(
            total: (int) $result['total'],
            perPage: $perPage,
            currentPage: $pagina,
            baseUrl: '/admin/crud/' . $definition->key()
        );

        return [
            'rows' => $result['rows'],
            'total' => (int) $result['total'],
            'paginator' => $paginator,
            'search' => $searchTerm,
            'order' => $orderBy,
            'direction' => $direction,
            'groupBy' => $groupBy,
            'summaryRow' => $summaryRow ?? [],
            'aggregationSkipped' => $aggregationSkipped,
            'aggregationSkipMessage' => $aggregationSkipMessage,
        ];
    }

    /**
     * Aplica las condiciones de scope (row-level) al WHERE/params dados, reutilizando
     * exactamente el mismo mecanismo que el listado CRUD (CrudScopeResolver).
     *
     * @param array<string,mixed> $query
     * @param list<string>        $where
     * @param list<mixed>         $params
     */
    private function applyScopeConditions(
        CrudResourceDefinition $definition,
        array $query,
        ?int $userId,
        ?callable $can,
        array &$where,
        array &$params
    ): void {
        if ($this->scopeResolver === null) {
            return;
        }

        $canCheck = $can ?? static fn(string $slug): bool => false;
        $scope = $this->scopeResolver->resolve($definition, $userId, $canCheck);
        if ($scope === null) {
            return;
        }

        $scopeCtx = new CrudListContext(
            $definition->key(),
            $definition->table(),
            $definition->primaryKey(),
            $userId,
            '',
            $query
        );
        $scope->apply($scopeCtx);
        [$scopeWhere, $scopeParams] = CrudScopeResolver::conditionsToSql($scopeCtx->conditions());
        foreach ($scopeWhere as $part) {
            $where[] = $part;
        }
        foreach ($scopeParams as $param) {
            $params[] = $param;
        }
    }

    /**
     * Filas dentro de un rango de fechas, respetando el mismo scope que el listado.
     * Filtros: igualdad simple sobre columnas declaradas del recurso (validadas).
     *
     * @param array<string,mixed> $filters columna => valor (igualdad)
     * @return list<array<string,mixed>>
     */
    public function eventsInRange(
        CrudResourceDefinition $definition,
        string $dateColumn,
        string $from,
        string $to,
        ?int $userId = null,
        ?callable $can = null,
        array $filters = []
    ): array {
        $where = ['deleted = 0'];
        $params = [];

        $allowed = array_fill_keys($definition->columnNames(), true);
        foreach ($filters as $field => $value) {
            $field = (string) $field;
            if ($field === '' || $value === null || $value === '' || !isset($allowed[$field])) {
                continue;
            }
            $where[] = '`' . $field . '` = ?';
            $params[] = $value;
        }

        $this->applyScopeConditions($definition, [], $userId, $can, $where, $params);

        return $this->repository->selectInDateRange(
            $definition->table(),
            $dateColumn,
            $from,
            $to,
            $where,
            $params
        );
    }

    private function hasActiveListFilters(CrudResourceDefinition $definition, array $query, string $searchTerm): bool
    {
        if (trim($searchTerm) !== '') {
            return true;
        }
        foreach ($definition->listFilters() as $filter) {
            $field = (string) ($filter['field'] ?? '');
            if ($field === '') {
                continue;
            }
            $value = $query['f_' . $field] ?? null;
            if ($value !== null && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    public function find(CrudResourceDefinition $definition, int $id): ?array
    {
        return $this->repository->findById($definition->table(), $definition->primaryKey(), $id);
    }

    /**
     * Un registro por id respetando el mismo row-level scope que el listado.
     *
     * @return array<string,mixed>|null
     */
    public function findInScope(
        CrudResourceDefinition $definition,
        int $id,
        ?int $userId = null,
        ?callable $can = null
    ): ?array {
        $where = ['deleted = 0', '`' . $definition->primaryKey() . '` = ?'];
        $params = [$id];
        $this->applyScopeConditions($definition, [], $userId, $can, $where, $params);

        return $this->repository->findByIdScoped($definition->table(), $where, $params);
    }

    public function store(CrudResourceDefinition $definition, array $input, array $files, ?int $userId, string $ip): int
    {
        $payload = $this->buildPayload($definition, $input, $files, true, null, $userId, $ip);

        $systemColumns = [
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $userId,
            'updated_at' => null,
            'updated_by' => null,
            'deleted' => 0,
            'deleted_at' => null,
            'deleted_by' => null,
        ];
        $payload = array_merge($payload, $systemColumns);

        $ctx = new CrudWriteContext(
            $definition->key(),
            $definition->table(),
            $definition->primaryKey(),
            $userId,
            $ip,
            $input,
            null,
            null,
            $payload,
            true
        );
        $this->hookRunner->run($definition, 'beforeCreate', $ctx);

        // Read-back: el handler pudo mutar data(); re-aplicamos columnas de
        // sistema para que no puedan ser sobrescritas por un handler.
        $payload = array_merge($ctx->data(), $systemColumns);

        $id = $this->repository->insertRecord($definition->table(), $payload);

        $this->bitacoraRepository->registrar(
            $userId,
            'crud.create',
            $definition->table(),
            $id,
            json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '',
            $ip
        );

        $ctx->setData($payload);
        $ctx->setRecordId($id);
        $this->hookRunner->run($definition, 'afterCreate', $ctx);

        return $id;
    }

    public function update(CrudResourceDefinition $definition, int $id, array $input, array $files, ?int $userId, string $ip): void
    {
        $existing = $this->repository->findById($definition->table(), $definition->primaryKey(), $id);
        $payload = $this->buildPayload($definition, $input, $files, false, is_array($existing) ? $existing : null, $userId, $ip);

        $systemColumns = [
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $userId,
        ];
        $payload = array_merge($payload, $systemColumns);

        $ctx = new CrudWriteContext(
            $definition->key(),
            $definition->table(),
            $definition->primaryKey(),
            $userId,
            $ip,
            $input,
            is_array($existing) ? $existing : null,
            $id,
            $payload,
            false
        );
        $this->hookRunner->run($definition, 'beforeUpdate', $ctx);

        // Read-back con columnas de sistema preservadas.
        $payload = array_merge($ctx->data(), $systemColumns);

        $this->repository->updateRecord($definition->table(), $definition->primaryKey(), $id, $payload);
        $this->bitacoraRepository->registrar(
            $userId,
            'crud.update',
            $definition->table(),
            $id,
            json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '',
            $ip
        );

        $ctx->setData($payload);
        $this->hookRunner->run($definition, 'afterUpdate', $ctx);
    }

    public function delete(CrudResourceDefinition $definition, int $id, ?int $userId, string $ip): void
    {
        $existing = $this->repository->findById($definition->table(), $definition->primaryKey(), $id);

        $payload = [
            'deleted' => 1,
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $userId,
        ];

        $ctx = new CrudWriteContext(
            $definition->key(),
            $definition->table(),
            $definition->primaryKey(),
            $userId,
            $ip,
            [],
            is_array($existing) ? $existing : null,
            $id,
            $payload,
            false
        );
        $this->hookRunner->run($definition, 'beforeDelete', $ctx);

        $this->repository->updateRecord($definition->table(), $definition->primaryKey(), $id, $payload);

        $this->bitacoraRepository->registrar(
            $userId,
            'crud.delete',
            $definition->table(),
            $id,
            'Borrado lógico',
            $ip
        );

        $this->hookRunner->run($definition, 'afterDelete', $ctx);
    }

    private function buildPayload(CrudResourceDefinition $definition, array $input, array $files, bool $isCreate, ?array $existingRow, ?int $userId = null, string $ip = ''): array
    {
        $payload = [];
        $errors = [];
        $normalizedByField = [];

        foreach ($definition->formFields() as $field) {
            if (!$field instanceof CrudFieldDefinition) {
                continue;
            }
            $name = $field->name();
            if (in_array($name, self::PROTECTED_COLUMNS, true)) {
                continue;
            }

            if ($field->readonly()) {
                if (!$isCreate && is_array($existingRow) && array_key_exists($name, $existingRow)) {
                    $payload[$name] = $existingRow[$name];
                }
                continue;
            }

            if ($field->type() === 'file') {
                continue;
            }

            if ($field->type() === 'checkbox' && !array_key_exists($name, $input)) {
                $raw = 0;
            } else {
                $raw = $input[$name] ?? null;
            }

            if (($raw === null || $raw === '') && !$isCreate && is_array($existingRow) && array_key_exists($name, $existingRow) && $field->type() === 'checkbox') {
                $raw = $existingRow[$name];
            }
            if (($raw === null || $raw === '') && $field->type() !== 'checkbox') {
                $raw = $field->defaultValue();
            }

            $sanitized = $this->fieldValidation->sanitizeRawInput($field, $raw);
            $normalized = $this->fieldValidation->normalizeValue($field, $sanitized);
            $normalizedByField[$name] = $normalized;
        }

        foreach ($this->fieldValidation->validatePayload($definition->formFields(), $normalizedByField) as $fieldName => $fieldErrors) {
            foreach ($fieldErrors as $msg) {
                $errors[$fieldName][] = $msg;
            }
        }

        $exceptId = (!$isCreate && is_array($existingRow))
            ? (((int) ($existingRow[$definition->primaryKey()] ?? 0)) ?: null)
            : null;

        if ($this->dbConstraintValidator !== null) {
            foreach ($this->dbConstraintValidator->validate($definition, $normalizedByField, $exceptId) as $fieldName => $fieldErrors) {
                foreach ($fieldErrors as $msg) {
                    $errors[$fieldName][] = $msg;
                }
            }
        }

        if ($this->handlerRegistry !== null && $definition->formValidators() !== []) {
            $record = (!$isCreate && is_array($existingRow)) ? $existingRow : null;
            $ctx = new CrudValidationContext(
                $definition->key(),
                $definition->table(),
                $definition->primaryKey(),
                $userId,
                $ip,
                $input,
                $normalizedByField,
                $record,
                !$isCreate
            );
            foreach ($definition->formValidators() as $validatorKey) {
                $validator = $this->handlerRegistry->resolve($validatorKey, CrudValidatorInterface::class);
                if ($validator instanceof CrudValidatorInterface) {
                    $validator->validate($ctx);
                }
            }
            foreach ($ctx->errors() as $fieldName => $fieldErrors) {
                foreach ($fieldErrors as $msg) {
                    $errors[$fieldName][] = $msg;
                }
            }
        }

        foreach ($definition->formFields() as $field) {
            if (!$field instanceof CrudFieldDefinition || $field->type() !== 'file' || $field->readonly()) {
                continue;
            }
            $name = $field->name();
            if (in_array($name, self::PROTECTED_COLUMNS, true)) {
                continue;
            }
            $file = $files[$name] ?? null;
            $hasFile = is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            if ($isCreate && $field->required() && !$hasFile) {
                $errors[$name][] = 'El archivo es obligatorio.';
            }
        }

        $this->fieldValidation->assertNoErrors($errors);

        foreach ($definition->formFields() as $field) {
            if (!$field instanceof CrudFieldDefinition) {
                continue;
            }
            $name = $field->name();
            if (in_array($name, self::PROTECTED_COLUMNS, true) || $field->readonly() || $field->type() === 'file') {
                continue;
            }
            if (!array_key_exists($name, $normalizedByField)) {
                continue;
            }
            try {
                $payload[$name] = $this->fieldValidation->toStorageValue($field, $normalizedByField[$name]);
            } catch (\Throwable) {
                $errors[$name][] = 'Valor inválido.';
            }
        }

        $this->fieldValidation->assertNoErrors($errors);

        foreach ($definition->formFields() as $field) {
            if (!$field instanceof CrudFieldDefinition || $field->type() !== 'file') {
                continue;
            }
            $name = $field->name();
            if (in_array($name, self::PROTECTED_COLUMNS, true) || $field->readonly()) {
                continue;
            }

            try {
                $rowId = $existingRow !== null ? (int) ($existingRow[$definition->primaryKey()] ?? 0) : null;
                $path = $this->handleUpload($definition, $field, $files, $rowId !== 0 ? $rowId : null, $userId);
                if ($path !== null) {
                    $payload[$name] = $path;
                }
            } catch (ValidationException $e) {
                $errors[$name][] = $e->getMessage();
            }
        }

        $this->fieldValidation->assertNoErrors($errors);

        return $payload;
    }

    private function handleUpload(CrudResourceDefinition $definition, CrudFieldDefinition $field, array $files, ?int $rowId = null, ?int $userId = null): ?string
    {
        if (!$definition->uploadsEnabled()) {
            return null;
        }

        $file = $files[$field->name()] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $allowed = $field->validation()['allowed_extensions'] ?? null;

        $service = $this->fileUploadService ?? new FileUploadService(
            new ImageProcessor(),
            new \App\Infrastructure\Repositories\ArchivoRepository()
        );

        $archivo = $service->handle($file, new \App\Application\DTO\Files\FileUploadConfig(
            entidadTipo: 'crud:' . $definition->key(),
            directorio: $definition->uploadsPath(),
            maxBytes: ((int) Config::get('security.max_upload_mb', 10)) * 1024 * 1024,
            entidadId: $rowId,
            coleccion: $field->name(),
            disco: 'public',
            allowedExtensions: is_array($allowed) ? $allowed : null,
            imagen: null,
            esActual: false,
            creadoPor: $userId
        ), $field->label());

        return $archivo->ruta();
    }
}
