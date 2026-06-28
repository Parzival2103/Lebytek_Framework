<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$views    = $views ?? ['default' => 'month', 'enabled' => ['month', 'table']];
$enabled  = (array) ($views['enabled'] ?? ['month']);
$default  = (string) ($views['default'] ?? 'month');
$caps     = $capabilities ?? [];
$legend   = $legend ?? [];
$resource    = (string) ($resource ?? '');
$key         = (string) ($key ?? '');
$calendarUrl = (string) ($calendarUrl ?? ('/admin/calendario/' . $key));
$createUrl   = ((string) ($crudBaseUrl ?? '')) . '/crear?return_to=' . rawurlencode($calendarUrl);

$viewLabels = [
    'month' => 'Mes',
    'week'  => 'Semana',
    'day'   => 'Día',
    'table' => 'Tabla',
];
$viewIcons = [
    'month' => 'bi-calendar3',
    'week'  => 'bi-calendar-week',
    'day'   => 'bi-calendar-day',
    'table' => 'bi-list-ul',
];
?>
<link rel="stylesheet" href="<?= ViewHelper::asset('css/lebytek-ui.css') ?>">

<div class="container-fluid px-3 px-lg-4 py-3 py-lg-4 lebytek-calendar-page ct-page">
    <header class="ct-page-header card border-0 shadow-sm ct-card mb-4">
        <div class="card-body p-3 p-md-4 d-flex flex-row flex-nowrap justify-content-between align-items-center gap-2 gap-md-3">
            <div class="flex-grow-1 min-w-0">
                <h1 class="ct-page-title h4 mb-0 d-inline-flex align-items-center gap-2">
                    <i class="bi <?= ViewHelper::e((string) ($icon ?? 'bi-calendar-event')) ?>" aria-hidden="true"></i>
                    <span class="text-truncate"><?= ViewHelper::e((string) ($title ?? 'Calendario')) ?></span>
                </h1>
            </div>
            <?php if (!empty($caps['canCreate'])): ?>
                <div class="ct-actions ct-actions--end flex-shrink-0">
                    <a href="<?= ViewHelper::e($createUrl) ?>"
                       class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 text-nowrap">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                        <span>Nuevo registro</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <section class="ct-table-card card border-0 shadow-sm ct-card">
        <div class="card-header bg-transparent border-bottom p-3 p-md-4">
            <div class="d-flex flex-column flex-lg-row justify-content-lg-between align-items-lg-center gap-3">
                <div class="btn-toolbar gap-2" role="toolbar" aria-label="Navegación del calendario">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-secondary" data-cal-nav="prev" aria-label="Periodo anterior">
                            <i class="bi bi-chevron-left" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-cal-nav="today">Hoy</button>
                        <button type="button" class="btn btn-outline-secondary" data-cal-nav="next" aria-label="Periodo siguiente">
                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        </button>
                    </div>
                    <h2 class="h6 mb-0 align-self-center ms-1" data-cal-period aria-live="polite">&nbsp;</h2>
                </div>

                <?php if (count($enabled) > 1): ?>
                    <div class="btn-group" role="group" aria-label="Selector de vista">
                        <?php foreach ($enabled as $view): ?>
                            <?php $view = (string) $view; ?>
                            <button type="button"
                                    class="btn btn-outline-primary<?= $view === $default ? ' active' : '' ?>"
                                    data-cal-view="<?= ViewHelper::e($view) ?>">
                                <i class="bi <?= ViewHelper::e($viewIcons[$view] ?? 'bi-calendar3') ?> me-1" aria-hidden="true"></i>
                                <?= ViewHelper::e($viewLabels[$view] ?? ucfirst($view)) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($filters)): ?>
                <div class="row g-2 g-md-3 align-items-end mt-1 lebytek-calendar-filters">
                    <?php foreach ($filters as $filter): ?>
                        <?php $field = (string) ($filter['field'] ?? ''); ?>
                        <?php if ($field === '') { continue; } ?>
                        <div class="col-6 col-md-3">
                            <label class="form-label small text-muted mb-1" for="cal-filter-<?= ViewHelper::e($field) ?>">
                                <?= ViewHelper::e((string) ($filter['label'] ?? ucfirst($field))) ?>
                            </label>
                            <select id="cal-filter-<?= ViewHelper::e($field) ?>"
                                    class="form-select form-select-sm"
                                    data-cal-filter="<?= ViewHelper::e($field) ?>">
                                <option value="">Todos</option>
                                <?php foreach ((array) ($filter['options'] ?? []) as $value => $label): ?>
                                    <option value="<?= ViewHelper::e((string) $value) ?>"><?= ViewHelper::e((string) $label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($legend !== []): ?>
                <div class="d-flex flex-wrap gap-3 mt-3 lebytek-calendar-legend">
                    <?php foreach ($legend as $item): ?>
                        <span class="d-inline-flex align-items-center gap-1 small text-muted">
                            <span class="badge bg-<?= ViewHelper::e((string) ($item['tone'] ?? 'secondary')) ?>">&nbsp;</span>
                            <?= ViewHelper::e((string) ($item['label'] ?? '')) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card-body p-3 p-md-4">
            <div id="lebytek-calendar"
                 class="lebytek-calendar"
                 data-feed="<?= ViewHelper::e((string) ($feedUrl ?? '')) ?>"
                 data-resource="<?= ViewHelper::e($resource) ?>"
                 data-start-field="<?= ViewHelper::e((string) ($startField ?? '')) ?>"
                 data-crud-base="<?= ViewHelper::e((string) ($crudBaseUrl ?? '')) ?>"
                 data-calendar-url="<?= ViewHelper::e($calendarUrl) ?>"
                 data-default-view="<?= ViewHelper::e($default) ?>"
                 data-all-day="<?= !empty($allDay) ? '1' : '0' ?>"
                 data-can-create="<?= !empty($caps['canCreate']) ? '1' : '0' ?>"
                 data-can-edit="<?= !empty($caps['canEdit']) ? '1' : '0' ?>"
                 data-can-delete="<?= !empty($caps['canDelete']) ? '1' : '0' ?>"
                 data-open-detail="<?= !empty($caps['openDetail']) ? '1' : '0' ?>"
                 data-csrf="<?= ViewHelper::e(ViewHelper::csrfToken()) ?>">
                <div class="lebytek-calendar-loading text-center text-muted py-5">
                    <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
                    Cargando eventos…
                </div>
            </div>
            <div class="lebytek-calendar-empty text-center text-muted py-5 d-none" data-cal-empty>
                <i class="bi bi-calendar-x fs-2 d-block mb-2" aria-hidden="true"></i>
                No hay eventos en este periodo.
            </div>
        </div>
    </section>
</div>

<script src="<?= ViewHelper::asset('js/calendar.js') ?>" defer></script>
