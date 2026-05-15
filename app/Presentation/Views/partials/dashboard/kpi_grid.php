<?php
use App\Kernel\Helpers\ViewHelper;
/** @var \App\Application\DTO\Dashboard\DashboardViewModel $dashboard */
?>
<div class="row g-4 mb-4 dashboard-kpi-row">
    <?php foreach ($dashboard->kpis as $i => $kpi): ?>
    <div class="col-6 col-xl-3 fade-stagger" style="--i:<?= (int) $i ?>">
        <?php if (($kpi['url'] ?? '') !== '#'): ?>
        <a href="<?= ViewHelper::e($kpi['url'] ?? '#') ?>" class="card kpi-card ct-card ct-metric-card border-0 shadow-sm h-100 text-decoration-none">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon kpi-icon-<?= ViewHelper::e($kpi['color'] ?? 'primary') ?>">
                    <i class="bi <?= ViewHelper::e($kpi['icon'] ?? 'bi-circle') ?>"></i>
                </div>
                <div class="kpi-body-text">
                    <?php if (($kpi['value'] ?? '') !== ''): ?>
                    <div class="kpi-value h4 mb-0 fw-bold"><?= ViewHelper::e($kpi['value']) ?></div>
                    <?php endif ?>
                    <div class="kpi-label small text-muted"><?= ViewHelper::e($kpi['label'] ?? '') ?></div>
                    <?php if (!empty($kpi['description'])): ?>
                    <div class="small text-muted mt-1"><?= ViewHelper::e($kpi['description']) ?></div>
                    <?php endif ?>
                </div>
            </div>
        </a>
        <?php else: ?>
        <div class="card kpi-card ct-card ct-metric-card border-0 shadow-sm h-100 text-muted">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon kpi-icon-<?= ViewHelper::e($kpi['color'] ?? 'secondary') ?>">
                    <i class="bi <?= ViewHelper::e($kpi['icon'] ?? 'bi-circle') ?>"></i>
                </div>
                <div class="kpi-body-text">
                    <div class="kpi-label small fw-medium"><?= ViewHelper::e($kpi['label'] ?? '') ?></div>
                    <?php if (!empty($kpi['description'])): ?>
                    <div class="small mt-1"><?= ViewHelper::e($kpi['description']) ?></div>
                    <?php endif ?>
                </div>
            </div>
        </div>
        <?php endif ?>
    </div>
    <?php endforeach; ?>
</div>
