<?php

use App\Kernel\Helpers\ViewHelper;

?>
<link rel="stylesheet" href="<?= ViewHelper::asset('css/crud-engine.css') ?>">
<script src="<?= ViewHelper::asset('js/crud-engine.js') ?>" defer></script>

<div class="container-fluid px-3 px-lg-4 py-3 py-lg-4 crud-engine ct-page">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            <div class="ct-detail-card card border-0 shadow-sm ct-card">
                <div class="card-header bg-transparent border-bottom p-3 p-md-4 d-flex flex-column flex-md-row justify-content-md-between align-items-md-start gap-3">
                    <div>
                        <h1 class="ct-page-title h4 mb-1 d-flex align-items-center gap-2">
                            <?= ViewHelper::e((string) ($title ?? 'Detalle')) ?>
                            <?php if (!empty($state) && (string) ($state['value'] ?? '') !== ''): ?>
                                <span class="badge rounded-pill bg-<?= ViewHelper::e((string) $state['badge']) ?>-subtle text-<?= ViewHelper::e((string) $state['badge']) ?> border border-<?= ViewHelper::e((string) $state['badge']) ?>-subtle">
                                    <?= ViewHelper::e((string) $state['label']) ?>
                                </span>
                            <?php endif; ?>
                        </h1>
                        <p class="ct-page-subtitle text-muted small mb-0">Solo lectura. Fechas y montos con formato local.</p>
                    </div>
                    <div class="ct-actions d-flex flex-column flex-sm-row gap-2 w-100 w-md-auto align-items-stretch align-items-sm-center">
                        <a href="/admin/crud/<?= ViewHelper::e((string) ($resource ?? '')) ?>"
                           class="btn btn-sm btn-outline-secondary">Volver al listado</a>
                        <?php
                            $headerActions = array_values(array_filter(($actions ?? []), static function (array $a): bool {
                                return (string) ($a['name'] ?? '') !== 'show';
                            }));
                        ?>
                        <?php $rowActions = $headerActions; require __DIR__ . '/partials/actions_row.php'; ?>
                    </div>
                </div>
                <div class="card-body p-3 p-md-4">
                    <?php $tabs = is_array($tabs ?? null) ? $tabs : []; ?>
                    <?php if (count($tabs) <= 1): ?>
                        <?php
                            $only = $tabs[0] ?? ['type' => 'fields', 'columns' => ($columns ?? [])];
                            $tabColumns = is_array($only['columns'] ?? null) ? $only['columns'] : ($columns ?? []);
                            require __DIR__ . '/partials/tab_fields.php';
                        ?>
                    <?php else: ?>
                        <ul class="nav nav-tabs mb-3" role="tablist">
                            <?php foreach ($tabs as $i => $tab): ?>
                                <?php $tabKey = (string) ($tab['key'] ?? ('tab' . $i)); ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?= $i === 0 ? 'active' : '' ?>"
                                            id="tab-btn-<?= ViewHelper::e($tabKey) ?>"
                                            data-bs-toggle="tab" data-bs-target="#tab-pane-<?= ViewHelper::e($tabKey) ?>"
                                            type="button" role="tab">
                                        <?= ViewHelper::e((string) ($tab['label'] ?? $tabKey)) ?>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="tab-content">
                            <?php foreach ($tabs as $i => $tab): ?>
                                <?php $tabKey = (string) ($tab['key'] ?? ('tab' . $i)); $tabType = (string) ($tab['type'] ?? 'fields'); ?>
                                <div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>"
                                     id="tab-pane-<?= ViewHelper::e($tabKey) ?>" role="tabpanel">
                                    <?php if ($tabType === 'fields'): ?>
                                        <?php $tabColumns = is_array($tab['columns'] ?? null) ? $tab['columns'] : []; require __DIR__ . '/partials/tab_fields.php'; ?>
                                    <?php elseif ($tabType === 'relation'): ?>
                                        <?php $relColumns = is_array($tab['columns'] ?? null) ? $tab['columns'] : []; $relRows = is_array($tab['rows'] ?? null) ? $tab['rows'] : []; require __DIR__ . '/partials/tab_relation.php'; ?>
                                    <?php elseif ($tabType === 'history'): ?>
                                        <?php $historyEntries = is_array($tab['entries'] ?? null) ? $tab['entries'] : []; require __DIR__ . '/partials/tab_history.php'; ?>
                                    <?php elseif ($tabType === 'component'): ?>
                                        <?php
                                            $componentView = (string) ($tab['view'] ?? '');
                                            if ($componentView !== '' && !str_contains($componentView, '..')) {
                                                echo ViewHelper::partial($componentView, ['row' => $row ?? []]);
                                            }
                                        ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
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
