<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Services;

use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;
use Lebytek\Framework\Domain\Interfaces\BitacoraRepositoryInterface;

/**
 * Construye el view-model de pestañas para `show.php`. Sin bloque `detail`,
 * genera una sola tab "Datos generales" equivalente a la vista plana previa.
 */
final class CrudDetailBuilder
{
    public function __construct(
        private readonly CrudRelationService $relationService,
        private readonly BitacoraRepositoryInterface $bitacoraRepository
    ) {}

    /**
     * @param array<string, mixed> $row
     * @return list<array<string, mixed>>
     */
    public function build(CrudResourceDefinition $definition, array $row): array
    {
        $primaryId = (int) ($row[$definition->primaryKey()] ?? 0);

        if (!$definition->hasDetail()) {
            return [[
                'key' => 'general',
                'label' => 'Datos generales',
                'type' => 'fields',
                'columns' => $definition->listColumns(),
            ]];
        }

        $out = [];
        foreach ($definition->detailTabs() as $tab) {
            if ($tab->isFields()) {
                $out[] = [
                    'key' => $tab->key(),
                    'label' => $tab->label(),
                    'type' => 'fields',
                    'columns' => $this->columnsFor($definition, $tab->columns()),
                ];
            } elseif ($tab->isRelation()) {
                $relation = $definition->relation($tab->relation());
                $rows = ($relation !== null && $relation->isHasMany())
                    ? $this->relationService->childrenFor($relation, $primaryId)
                    : [];
                $out[] = [
                    'key' => $tab->key(),
                    'label' => $tab->label(),
                    'type' => 'relation',
                    'columns' => $relation !== null ? $relation->columns() : [],
                    'rows' => $rows,
                ];
            } elseif ($tab->isHistory()) {
                $out[] = [
                    'key' => $tab->key(),
                    'label' => $tab->label(),
                    'type' => 'history',
                    'entries' => $this->bitacoraRepository->porRegistro($definition->table(), $primaryId, 50),
                ];
            } elseif ($tab->isComponent()) {
                $out[] = [
                    'key' => $tab->key(),
                    'label' => $tab->label(),
                    'type' => 'component',
                    'view' => $tab->view(),
                ];
            }
        }

        return $out;
    }

    /**
     * Mapea nombres de columna a sus configuraciones (label/format/badge) desde
     * list.columns; si no existe, usa un default con el nombre como label.
     *
     * @param list<string> $names
     * @return list<array<string, mixed>>
     */
    private function columnsFor(CrudResourceDefinition $definition, array $names): array
    {
        if ($names === []) {
            return $definition->listColumns();
        }

        $byName = [];
        foreach ($definition->listColumns() as $col) {
            $byName[(string) ($col['name'] ?? '')] = $col;
        }

        $out = [];
        foreach ($names as $name) {
            $out[] = $byName[$name] ?? ['name' => $name, 'label' => $name];
        }

        return $out;
    }
}
