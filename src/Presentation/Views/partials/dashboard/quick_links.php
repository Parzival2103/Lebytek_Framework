<?php
use App\Kernel\Helpers\ViewHelper;
/** @var \App\Application\DTO\Dashboard\DashboardViewModel $dashboard */
?>
<div class="col-12 col-lg-4 fade-stagger" style="--i:1">
    <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent border-0 pb-0 pt-3 px-4">
            <h6 class="fw-semibold mb-0">Accesos rápidos</h6>
        </div>
        <div class="card-body px-4">
            <div class="d-flex flex-wrap gap-2 mt-2">
                <?php foreach ($dashboard->quickAccessItems as $acceso): ?>
                <a href="<?= ViewHelper::e($acceso['url'] ?? '#') ?>"
                   class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
                    <i class="bi <?= ViewHelper::e($acceso['icon'] ?? '') ?>"></i>
                    <?= ViewHelper::e($acceso['label'] ?? '') ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
