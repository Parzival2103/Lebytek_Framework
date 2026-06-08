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
                    <dl class="row crud-dl mb-0">
                        <?php foreach (($columns ?? []) as $column): ?>
                            <?php
                                $name = (string) ($column['name'] ?? '');
                                $raw = $row[$name] ?? '';
                                $format = (string) ($column['format'] ?? '');
                                $display = (string) $raw;

                                if ($format === 'date' && $raw !== '' && $raw !== null) {
                                    $ts = strtotime((string) $raw);
                                    $display = $ts ? date('d/m/Y', $ts) : $display;
                                } elseif ($format === 'datetime' && $raw !== '' && $raw !== null) {
                                    $ts = strtotime((string) $raw);
                                    $display = $ts ? date('d/m/Y H:i', $ts) : $display;
                                } elseif ($format === 'money' && $raw !== '' && $raw !== null) {
                                    $display = '$' . number_format((float) $raw, 2, '.', ',');
                                }

                                $badge = null;
                                if (!empty($column['badge']) && is_array($column['badge'])) {
                                    $badgeMap = $column['badge'];
                                    $badge = (string) ($badgeMap[(string) $raw] ?? '');
                                }
                            ?>
                            <dt class="col-12 col-sm-4 col-lg-3"><?= ViewHelper::e((string) ($column['label'] ?? $name)) ?></dt>
                            <dd class="col-12 col-sm-8 col-lg-9 mb-3">
                                <?php if ($badge !== ''): ?>
                                    <span class="badge rounded-pill bg-<?= ViewHelper::e($badge) ?>-subtle text-<?= ViewHelper::e($badge) ?> border border-<?= ViewHelper::e($badge) ?>-subtle">
                                        <?= ViewHelper::e($display) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="d-block py-1 border-bottom border-light-subtle"><?= ViewHelper::e($display) ?></span>
                                <?php endif; ?>
                            </dd>
                        <?php endforeach; ?>
                    </dl>
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
