<?php

use App\Kernel\Helpers\ViewHelper;

$grouped = !empty($grouped);
$tableCompact = !empty($tableCompact);
$orderOptions = [];
foreach (($columns ?? []) as $column) {
    if (!empty($column['sortable'])) {
        $orderOptions[] = $column;
    }
}
if ($orderOptions === [] && !empty($primaryKey)) {
    $orderOptions[] = ['name' => (string) $primaryKey, 'label' => 'Predeterminado'];
}
$tableClass = 'table table-hover table-striped align-middle mb-0' . ($tableCompact ? ' table-sm' : '');
?>
<link rel="stylesheet" href="<?= ViewHelper::asset('css/crud-engine.css') ?>">

<div class="container-fluid px-3 px-lg-4 py-3 py-lg-4 crud-engine ct-page">
    <?= ViewHelper::partial('crud/aggregation_skipped_alert', [
        'aggregationSkipped' => $aggregationSkipped ?? false,
        'aggregationSkipMessage' => $aggregationSkipMessage ?? null,
    ]) ?>

    <header class="ct-page-header card border-0 shadow-sm ct-card mb-4">
        <div class="card-body p-3 p-md-4 d-flex flex-column flex-lg-row justify-content-lg-between align-items-lg-center gap-3">
            <div class="flex-grow-1">
                <h1 class="ct-page-title h4 mb-1"><?= ViewHelper::e((string) ($title ?? 'CRUD')) ?></h1>
                <p class="ct-page-subtitle text-muted small mb-0">
                    <?= $grouped
                        ? 'Vista agrupada por columna declarada. Las acciones por fila no aplican a grupos.'
                        : 'Listado de registros activos. Usa filtros y búsqueda para acotar resultados.' ?>
                </p>
            </div>
            <div class="ct-actions justify-content-lg-end">
                <?php if (!empty($permissions['crear'])): ?>
                    <a href="/admin/crud/<?= ViewHelper::e((string) ($resource ?? '')) ?>/crear"
                       class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                        <span>Nuevo registro</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <section class="ct-table-card card border-0 shadow-sm ct-card">
        <?= ViewHelper::partial('crud/partials/actions_bulk', [
            'bulkActions' => $bulkActions ?? [],
            'resource' => $resource ?? '',
        ]) ?>
        <div class="card-header bg-transparent border-bottom p-3 p-md-4">
            <form class="row g-2 g-md-3 align-items-end" method="GET" action="/admin/crud/<?= ViewHelper::e((string) ($resource ?? '')) ?>">
                <div class="col-12 col-md-4">
                    <label class="form-label small text-muted mb-1" for="crud-buscar">Búsqueda</label>
                    <input type="text"
                           id="crud-buscar"
                           name="buscar"
                           value="<?= ViewHelper::e($query['buscar'] ?? '') ?>"
                           class="form-control form-control-sm"
                           placeholder="Buscar…">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1" for="crud-orden">Orden</label>
                    <select id="crud-orden" name="orden" class="form-select form-select-sm" aria-label="Ordenar por">
                        <?php foreach ($orderOptions as $column): ?>
                            <option value="<?= ViewHelper::e((string) ($column['name'] ?? '')) ?>" <?= (($query['orden'] ?? '') === ($column['name'] ?? '')) ? 'selected' : '' ?>>
                                <?= ViewHelper::e((string) ($column['label'] ?? ($column['name'] ?? ''))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted mb-1" for="crud-dir">Dirección</label>
                    <select id="crud-dir" name="direccion" class="form-select form-select-sm" aria-label="Dirección">
                        <option value="ASC" <?= (($query['direccion'] ?? 'DESC') === 'ASC') ? 'selected' : '' ?>>Asc</option>
                        <option value="DESC" <?= (($query['direccion'] ?? 'DESC') === 'DESC') ? 'selected' : '' ?>>Desc</option>
                    </select>
                </div>
                <?php foreach (($filters ?? []) as $filter): ?>
                    <?php $filterField = (string) ($filter['field'] ?? ''); ?>
                    <?php if ($filterField === '') {
                        continue;
                    } ?>
                    <div class="col-12 col-md-3">
                        <label class="form-label small text-muted mb-1" for="crud-f-<?= ViewHelper::e($filterField) ?>"><?= ViewHelper::e((string) ($filter['label'] ?? $filterField)) ?></label>
                        <input type="text"
                               id="crud-f-<?= ViewHelper::e($filterField) ?>"
                               class="form-control form-control-sm"
                               name="f_<?= ViewHelper::e($filterField) ?>"
                               value="<?= ViewHelper::e($query['f_' . $filterField] ?? '') ?>"
                               placeholder="<?= ViewHelper::e((string) ($filter['label'] ?? $filterField)) ?>">
                    </div>
                <?php endforeach; ?>
                <div class="col-12 ct-actions flex-wrap gap-2 pt-1">
                    <button class="btn btn-sm btn-primary px-3" type="submit">Aplicar</button>
                    <a href="/admin/crud/<?= ViewHelper::e((string) ($resource ?? '')) ?>" class="btn btn-sm btn-outline-secondary">Limpiar</a>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="<?= ViewHelper::e($tableClass) ?>">
                    <thead class="table-light">
                        <tr>
                            <?php if (!empty($selectable)): ?>
                                <th class="px-3" style="width:2.5rem">
                                    <input type="checkbox" class="form-check-input" data-crud-select-all aria-label="Seleccionar todo">
                                </th>
                            <?php endif; ?>
                            <?php foreach (($columns ?? []) as $column): ?>
                                <th class="px-3 text-nowrap"><?= ViewHelper::e((string) ($column['label'] ?? '')) ?></th>
                            <?php endforeach; ?>
                            <?php if (!$grouped): ?>
                                <th class="text-end px-3 text-nowrap ct-col-actions">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <?= ViewHelper::partial('crud/list_empty', [
                            'colspan' => count($columns ?? []) + ($grouped ? 0 : 1) + (!empty($selectable) ? 1 : 0),
                            'emptyTitle' => 'No hay registros para mostrar',
                            'emptyHint' => 'Crea un registro o ajusta filtros y búsqueda.',
                        ]) ?>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php if (!empty($selectable)): ?>
                                    <td class="px-3">
                                        <input type="checkbox" class="form-check-input" data-crud-row-check
                                               value="<?= (int) ($row[$primaryKey] ?? 0) ?>" aria-label="Seleccionar registro">
                                    </td>
                                <?php endif; ?>
                                <?php foreach (($columns ?? []) as $column): ?>
                                    <?php
                                        $name = (string) ($column['name'] ?? '');
                                        $value = $row['_formatted'][$name] ?? '';
                                        $badge = $row['_badge'][$name] ?? null;
                                    ?>
                                    <td class="px-3">
                                        <?php if ($badge !== null): ?>
                                            <span class="badge rounded-pill bg-<?= ViewHelper::e((string) $badge) ?>-subtle text-<?= ViewHelper::e((string) $badge) ?> border border-<?= ViewHelper::e((string) $badge) ?>-subtle">
                                                <?= ViewHelper::e((string) $value) ?>
                                            </span>
                                        <?php else: ?>
                                            <?= ViewHelper::e((string) $value) ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <?php if (!$grouped): ?>
                                    <td class="text-end px-3">
                                        <?= ViewHelper::partial('crud/partials/actions_row', [
                                            'rowActions' => $row['_actions'] ?? [],
                                            'resource' => $resource ?? '',
                                        ]) ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    <?php if ($grouped && !empty($summaryRow) && count($columns ?? []) > 1): ?>
                        <tfoot class="table-group-divider">
                            <tr class="table-light fw-semibold">
                                <td class="px-3">Totales (filtros aplicados)</td>
                                <?php
                                    $colList = $columns ?? [];
                                    for ($i = 1, $n = count($colList); $i < $n; $i++):
                                        $c = $colList[$i];
                                        $cname = (string) ($c['name'] ?? '');
                                        $val = $summaryRow['_formatted'][$cname] ?? '';
                                        ?>
                                        <td class="px-3"><?= ViewHelper::e((string) $val) ?></td>
                                    <?php endfor; ?>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <?php if (!empty($paginator) && $paginator->hasPages()): ?>
            <div class="card-footer bg-transparent border-top px-3 px-md-4 py-3 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
                <span class="small text-muted"><?= (int) $total ?> <?= $grouped ? 'grupos' : 'registros' ?></span>
                <?= $paginator->render() ?>
            </div>
        <?php elseif (!empty($total)): ?>
            <div class="card-footer bg-transparent border-top px-3 px-md-4 py-2">
                <span class="small text-muted"><?= (int) $total ?> <?= $grouped ? 'grupos' : 'registros' ?></span>
            </div>
        <?php endif; ?>
    </section>
</div>

<div class="modal fade" id="crudDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Esta acción marca el registro como eliminado (borrado lógico). ¿Deseas continuar?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form id="crudDeleteForm" method="POST" action="#">
                    <?= ViewHelper::csrfField() ?>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('crudDeleteModal');
        if (!modal) return;
        modal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn || !btn.getAttribute) return;
            var action = btn.getAttribute('data-action');
            var form = document.getElementById('crudDeleteForm');
            if (form && action) {
                form.setAttribute('action', action);
            }
        });
    });
</script>
<script src="<?= ViewHelper::asset('js/crud-engine.js') ?>"></script>
