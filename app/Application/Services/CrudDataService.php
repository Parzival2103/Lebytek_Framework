<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\CrudFieldDefinition;
use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Interfaces\BitacoraRepositoryInterface;
use App\Infrastructure\Repositories\GenericCrudRepository;
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
        private readonly CrudFieldValidationService $fieldValidation
    ) {}

    public function list(CrudResourceDefinition $definition, array $query): array
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

    public function store(CrudResourceDefinition $definition, array $input, array $files, ?int $userId, string $ip): int
    {
        $payload = $this->buildPayload($definition, $input, $files, true, null);
        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['created_by'] = $userId;
        $payload['updated_at'] = null;
        $payload['updated_by'] = null;
        $payload['deleted'] = 0;
        $payload['deleted_at'] = null;
        $payload['deleted_by'] = null;

        $hookPayload = ['data' => $payload, 'resource' => $definition->key(), 'userId' => $userId];
        $this->hookRunner->run($definition, 'beforeStore', $hookPayload);

        $id = $this->repository->insertRecord($definition->table(), $payload);

        $this->bitacoraRepository->registrar(
            $userId,
            'crud.create',
            $definition->table(),
            $id,
            json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '',
            $ip
        );

        $this->hookRunner->run($definition, 'afterStore', ['id' => $id, 'data' => $payload, 'resource' => $definition->key()]);
        return $id;
    }

    public function update(CrudResourceDefinition $definition, int $id, array $input, array $files, ?int $userId, string $ip): void
    {
        $existing = $this->repository->findById($definition->table(), $definition->primaryKey(), $id);
        $payload = $this->buildPayload($definition, $input, $files, false, is_array($existing) ? $existing : null);
        $payload['updated_at'] = date('Y-m-d H:i:s');
        $payload['updated_by'] = $userId;

        $this->hookRunner->run($definition, 'beforeUpdate', ['id' => $id, 'data' => $payload, 'resource' => $definition->key()]);

        $this->repository->updateRecord($definition->table(), $definition->primaryKey(), $id, $payload);
        $this->bitacoraRepository->registrar(
            $userId,
            'crud.update',
            $definition->table(),
            $id,
            json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '',
            $ip
        );

        $this->hookRunner->run($definition, 'afterUpdate', ['id' => $id, 'data' => $payload, 'resource' => $definition->key()]);
    }

    public function delete(CrudResourceDefinition $definition, int $id, ?int $userId, string $ip): void
    {
        $payload = [
            'deleted' => 1,
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $userId,
        ];

        $this->hookRunner->run($definition, 'beforeDelete', ['id' => $id, 'resource' => $definition->key()]);
        $this->repository->updateRecord($definition->table(), $definition->primaryKey(), $id, $payload);

        $this->bitacoraRepository->registrar(
            $userId,
            'crud.delete',
            $definition->table(),
            $id,
            'Borrado lógico',
            $ip
        );

        $this->hookRunner->run($definition, 'afterDelete', ['id' => $id, 'resource' => $definition->key()]);
    }

    private function buildPayload(CrudResourceDefinition $definition, array $input, array $files, bool $isCreate, ?array $existingRow): array
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
                $path = $this->handleUpload($definition, $field, $files);
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

    private function handleUpload(CrudResourceDefinition $definition, CrudFieldDefinition $field, array $files): ?string
    {
        if (!$definition->uploadsEnabled()) {
            return null;
        }

        $file = $files[$field->name()] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new ValidationException('Error al subir archivo para ' . $field->label() . '.');
        }

        $original = (string) ($file['name'] ?? '');
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowed = $field->validation()['allowed_extensions'] ?? null;
        if (is_array($allowed) && $allowed !== []) {
            $allowedLower = array_map(static fn($x): string => strtolower((string) $x), $allowed);
            if ($extension === '' || !in_array($extension, $allowedLower, true)) {
                throw new ValidationException('Extensión de archivo no permitida para ' . $field->label() . '.');
            }
        }
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($original, PATHINFO_FILENAME));
        if ($safeName === '') {
            $safeName = 'upload';
        }
        $filename = $safeName . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
        if ($extension !== '') {
            $filename .= '.' . $extension;
        }

        $publicRelative = trim($definition->uploadsPath(), '/');
        $publicAbsolute = PUBLIC_PATH . '/' . $publicRelative;

        if (!is_dir($publicAbsolute) && !mkdir($publicAbsolute, 0775, true) && !is_dir($publicAbsolute)) {
            throw new ValidationException('No fue posible crear el directorio de uploads.');
        }

        $destination = $publicAbsolute . '/' . $filename;
        if (!move_uploaded_file($tmpName, $destination)) {
            throw new ValidationException('No fue posible guardar el archivo subido.');
        }

        return '/' . $publicRelative . '/' . $filename;
    }
}
