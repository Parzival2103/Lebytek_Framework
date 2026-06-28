<?php
use Lebytek\Framework\Kernel\Helpers\ViewHelper;
/** @var \Lebytek\Framework\Application\DTO\Dashboard\DashboardViewModel $dashboard */
$s = $dashboard->sections;
$toneToText = static function (?string $t): string {
    return match ($t ?? 'muted') {
        'success' => 'text-success',
        'warning' => 'text-warning',
        'danger' => 'text-danger',
        default => 'text-muted',
    };
};
$badgeTone = $s['badgeTone'] ?? 'success';
$badgeClass = match ($badgeTone) {
    'warning' => 'bg-warning text-dark',
    'danger' => 'bg-danger',
    'secondary', 'muted' => 'bg-secondary',
    default => 'bg-success',
};
?>
<div class="row g-4">
    <div class="col-12 fade-stagger" style="--i:0">
        <div class="card border-0 shadow-sm dashboard-system-card">
            <div class="card-header bg-transparent border-0 pb-0 pt-3 px-4 d-flex justify-content-between align-items-center gap-2 min-w-0">
                <h6 class="fw-semibold mb-0 text-break flex-grow-1 min-w-0"><?= ViewHelper::e($s['statusTitle'] ?? 'Estado del sistema') ?></h6>
                <span class="badge <?= ViewHelper::e($badgeClass) ?> rounded-pill flex-shrink-0"><?= ViewHelper::e($s['badge'] ?? 'OK') ?></span>
            </div>
            <div class="card-body px-4 pb-4">
                <?php if (($s['statusLines'] ?? []) === []): ?>
                <div class="d-flex flex-column align-items-center justify-content-center py-4 text-muted">
                    <i class="bi bi-info-circle fs-2 mb-2 opacity-40"></i>
                    <p class="small mb-0 text-center">Sin líneas de estado aportadas por los módulos.</p>
                </div>
                <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($s['statusLines'] as $line): ?>
                    <li class="small py-1 <?= ViewHelper::e($toneToText($line['tone'] ?? null)) ?>">
                        <?= ViewHelper::e($line['text'] ?? '') ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>
