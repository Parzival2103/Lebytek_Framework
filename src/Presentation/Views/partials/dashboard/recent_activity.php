<?php
use Lebytek\Framework\Kernel\Helpers\ViewHelper;
/** @var \Lebytek\Framework\Application\DTO\Dashboard\DashboardViewModel $dashboard */
$sec = $dashboard->sections;
$items = $dashboard->activityItems;
$title = $sec['activityTitle'] ?? 'Actividad reciente';
$placeholder = $sec['activityPlaceholder'] ?? '';
?>
<div class="col-12 col-lg-8 fade-stagger" style="--i:0">
    <div class="card border-0 shadow-sm h-100 dashboard-activity-card">
        <div class="card-header bg-transparent border-0 pb-0 pt-3 px-4">
            <h6 class="fw-semibold mb-0"><?= ViewHelper::e($title) ?></h6>
        </div>
        <div class="card-body px-4">
            <?php if ($items === []): ?>
            <div class="activity-placeholder d-flex flex-column align-items-center justify-content-center py-5 text-muted">
                <i class="bi bi-clock-history fs-2 mb-2 opacity-25"></i>
                <p class="small mb-0 text-center"><?= ViewHelper::e($placeholder) ?></p>
            </div>
            <?php else: ?>
            <ul class="list-unstyled mb-0">
                <?php foreach ($items as $row): ?>
                <li class="d-flex gap-3 py-2 border-bottom border-light-subtle">
                    <div class="flex-shrink-0 text-primary"><i class="bi <?= ViewHelper::e($row['icon'] ?? 'bi-dot') ?>"></i></div>
                    <div class="flex-grow-1">
                        <div class="small"><?= ViewHelper::e($row['text'] ?? '') ?></div>
                        <?php if (!empty($row['meta'])): ?>
                        <div class="text-muted" style="font-size:0.75rem"><?= ViewHelper::e($row['meta']) ?></div>
                        <?php endif ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif ?>
        </div>
    </div>
</div>
