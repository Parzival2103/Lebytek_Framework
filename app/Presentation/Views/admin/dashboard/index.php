<?php
use App\Kernel\Helpers\ViewHelper;
/** @var \App\Application\DTO\Dashboard\DashboardViewModel $dashboard */
?>

<div class="ct-page">
<?= ViewHelper::partial('dashboard/kpi_grid', compact('dashboard')) ?>

<div class="row g-4 mb-4">
    <?= ViewHelper::partial('dashboard/recent_activity', compact('dashboard')) ?>
    <?= ViewHelper::partial('dashboard/quick_links', compact('dashboard')) ?>
</div>

<?= ViewHelper::partial('dashboard/system_status', compact('dashboard')) ?>
</div>
