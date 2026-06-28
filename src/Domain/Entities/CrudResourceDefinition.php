<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\Entities\Crud\CrudActionDefinition;
use App\Domain\Entities\Crud\CrudRelationDefinition;
use App\Domain\Entities\Crud\CrudStateMachine;
use App\Domain\Entities\Crud\CrudTabDefinition;

final class CrudResourceDefinition
{
    /**
     * @param CrudFieldDefinition[] $formFields
     */
    public function __construct(
        private readonly string $key,
        private readonly string $title,
        private readonly string $table,
        private readonly string $primaryKey,
        private readonly string $permissionPrefix,
        private readonly array $listColumns,
        private readonly array $listFilters,
        private readonly array $listActions,
        private readonly string $listGroupBy,
        private readonly array $listSummaries,
        private readonly array $formFields,
        private readonly bool $uploadsEnabled,
        private readonly string $uploadsPath,
        private readonly ?string $hookHandler,
        private readonly array $listAggregation,
        private readonly bool $listTableCompact,
        private readonly bool $hasActionsBlock,
        private readonly array $rowActions,
        private readonly array $bulkActions,
        private readonly ?CrudStateMachine $stateMachine,
        private readonly array $formValidators,
        private readonly array $relations,
        private readonly array $detailTabs,
        private readonly ?array $listScope,
        private readonly ?string $listScopeHandler
    ) {}

    public static function fromArray(array $config): self
    {
        $resource = $config['resource'] ?? [];
        $list     = $config['list'] ?? [];
        $form     = $config['form'] ?? [];
        $uploads  = $config['uploads'] ?? [];
        $hooks    = $config['hooks'] ?? [];
        $aggRaw   = is_array($list['aggregation'] ?? null) ? $list['aggregation'] : [];
        $maxRows  = isset($aggRaw['max_rows']) ? (int) $aggRaw['max_rows'] : 5000;
        if ($maxRows < 1) {
            $maxRows = 5000;
        }
        if ($maxRows > 500000) {
            $maxRows = 500000;
        }
        $listAggregation = [
            'enabled' => !array_key_exists('enabled', $aggRaw) || (bool) $aggRaw['enabled'],
            'max_rows' => $maxRows,
            'require_filter_above' => array_key_exists('require_filter_above', $aggRaw) && (int) $aggRaw['require_filter_above'] > 0
                ? (int) $aggRaw['require_filter_above']
                : null,
        ];
        $listTableCompact = !empty($list['table_compact']) || !empty($list['table_sm']);

        $fields = [];
        foreach (($form['fields'] ?? []) as $fieldConfig) {
            if (is_array($fieldConfig)) {
                $fields[] = CrudFieldDefinition::fromArray($fieldConfig);
            }
        }

        $hasActionsBlock = array_key_exists('actions', $config) && is_array($config['actions']);
        $rowActions = [];
        $bulkActions = [];
        if ($hasActionsBlock) {
            foreach (($config['actions']['row'] ?? []) as $raw) {
                if (is_array($raw) && ($raw['name'] ?? '') !== '') {
                    $rowActions[] = CrudActionDefinition::fromArray($raw);
                }
            }
            foreach (($config['actions']['bulk'] ?? []) as $raw) {
                if (is_array($raw) && ($raw['name'] ?? '') !== '') {
                    $bulkActions[] = CrudActionDefinition::fromArray($raw);
                }
            }
        } else {
            $listActions = is_array($list['actions'] ?? null) ? $list['actions'] : ['show', 'edit', 'delete'];
            foreach ($listActions as $builtin) {
                if (is_string($builtin) && $builtin !== '') {
                    $rowActions[] = CrudActionDefinition::fromArray(['name' => $builtin, 'type' => 'builtin']);
                }
            }
        }

        $stateMachine = null;
        if (array_key_exists('states', $config) && is_array($config['states'])) {
            $stateMachine = CrudStateMachine::fromArray($config['states']);
        }

        $formValidators = [];
        foreach (($form['validators'] ?? []) as $validatorKey) {
            if (is_string($validatorKey) && $validatorKey !== '') {
                $formValidators[] = $validatorKey;
            }
        }

        $relations = [];
        foreach ((is_array($config['relations'] ?? null) ? $config['relations'] : []) as $relName => $relConfig) {
            if (is_string($relName) && $relName !== '' && is_array($relConfig)) {
                $relations[$relName] = CrudRelationDefinition::fromArray($relName, $relConfig);
            }
        }

        $detailTabs = [];
        $detailBlock = $config['detail'] ?? null;
        if (is_array($detailBlock) && is_array($detailBlock['tabs'] ?? null)) {
            foreach ($detailBlock['tabs'] as $tabConfig) {
                if (is_array($tabConfig) && ($tabConfig['key'] ?? '') !== '') {
                    $detailTabs[] = CrudTabDefinition::fromArray($tabConfig);
                }
            }
        }

        $listScope = is_array($list['scope'] ?? null) ? $list['scope'] : null;
        $listScopeHandler = (isset($list['scope_handler']) && is_string($list['scope_handler']) && $list['scope_handler'] !== '')
            ? $list['scope_handler']
            : null;

        return new self(
            key: (string) ($resource['key'] ?? ''),
            title: (string) ($resource['title'] ?? ''),
            table: (string) ($resource['table'] ?? ''),
            primaryKey: (string) ($resource['primary_key'] ?? 'id'),
            permissionPrefix: (string) ($resource['permission_prefix'] ?? ($resource['key'] ?? '')),
            listColumns: is_array($list['columns'] ?? null) ? $list['columns'] : [],
            listFilters: is_array($list['filters'] ?? null) ? $list['filters'] : [],
            listActions: is_array($list['actions'] ?? null) ? $list['actions'] : ['show', 'edit', 'delete'],
            listGroupBy: (string) ($list['group_by'] ?? ''),
            listSummaries: is_array($list['summaries'] ?? null) ? $list['summaries'] : [],
            formFields: $fields,
            uploadsEnabled: (bool) ($uploads['enabled'] ?? false),
            uploadsPath: (string) ($uploads['public_path'] ?? 'uploads/cruds'),
            hookHandler: isset($hooks['handler']) && $hooks['handler'] !== '' ? (string) $hooks['handler'] : null,
            listAggregation: $listAggregation,
            listTableCompact: $listTableCompact,
            hasActionsBlock: $hasActionsBlock,
            rowActions: $rowActions,
            bulkActions: $bulkActions,
            stateMachine: $stateMachine,
            formValidators: $formValidators,
            relations: $relations,
            detailTabs: $detailTabs,
            listScope: $listScope,
            listScopeHandler: $listScopeHandler
        );
    }

    public function key(): string { return $this->key; }
    public function title(): string { return $this->title; }
    public function table(): string { return $this->table; }
    public function primaryKey(): string { return $this->primaryKey; }
    public function permissionPrefix(): string { return $this->permissionPrefix; }
    public function listColumns(): array { return $this->listColumns; }

    /**
     * Nombres de columnas conocidas del recurso: primary key + columnas de listado
     * + campos de formulario (deduplicado, preservando orden de aparición).
     *
     * @return list<string>
     */
    public function columnNames(): array
    {
        $names = [$this->primaryKey];
        foreach ($this->listColumns as $col) {
            if (is_array($col) && isset($col['name']) && is_string($col['name']) && $col['name'] !== '') {
                $names[] = $col['name'];
            }
        }
        foreach ($this->formFields as $field) {
            $name = $field->name();
            if ($name !== '') {
                $names[] = $name;
            }
        }
        return array_values(array_unique($names));
    }

    public function listFilters(): array { return $this->listFilters; }
    public function listActions(): array { return $this->listActions; }
    public function listGroupBy(): string { return $this->listGroupBy; }
    public function listSummaries(): array { return $this->listSummaries; }
    public function formFields(): array { return $this->formFields; }
    public function uploadsEnabled(): bool { return $this->uploadsEnabled; }
    public function uploadsPath(): string { return $this->uploadsPath; }
    public function hookHandler(): ?string { return $this->hookHandler; }

    /** @return array{enabled: bool, max_rows: int, require_filter_above: ?int} */
    public function listAggregation(): array
    {
        return $this->listAggregation;
    }

    public function listTableCompact(): bool
    {
        return $this->listTableCompact;
    }

    public function permissionFor(string $action): string
    {
        return $this->permissionPrefix . '.' . $action;
    }

    public function hasActionsBlock(): bool
    {
        return $this->hasActionsBlock;
    }

    /** @return list<CrudActionDefinition> */
    public function rowActions(): array
    {
        return $this->rowActions;
    }

    /** @return list<CrudActionDefinition> */
    public function bulkActions(): array
    {
        return $this->bulkActions;
    }

    public function hasStates(): bool
    {
        return $this->stateMachine !== null;
    }

    public function stateMachine(): ?CrudStateMachine
    {
        return $this->stateMachine;
    }

    /** @return list<string> */
    public function formValidators(): array
    {
        return $this->formValidators;
    }

    /** @return array<string, CrudRelationDefinition> */
    public function relations(): array
    {
        return $this->relations;
    }

    public function hasRelations(): bool
    {
        return $this->relations !== [];
    }

    public function relation(string $name): ?CrudRelationDefinition
    {
        return $this->relations[$name] ?? null;
    }

    /** @return list<CrudTabDefinition> */
    public function detailTabs(): array
    {
        return $this->detailTabs;
    }

    public function hasDetail(): bool
    {
        return $this->detailTabs !== [];
    }

    /** @return array<string, mixed>|null */
    public function listScope(): ?array { return $this->listScope; }

    public function listScopeHandler(): ?string { return $this->listScopeHandler; }
}
