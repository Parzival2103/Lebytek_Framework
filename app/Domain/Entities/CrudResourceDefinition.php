<?php

declare(strict_types=1);

namespace App\Domain\Entities;

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
        private readonly bool $listTableCompact
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
            listTableCompact: $listTableCompact
        );
    }

    public function key(): string { return $this->key; }
    public function title(): string { return $this->title; }
    public function table(): string { return $this->table; }
    public function primaryKey(): string { return $this->primaryKey; }
    public function permissionPrefix(): string { return $this->permissionPrefix; }
    public function listColumns(): array { return $this->listColumns; }
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
}
