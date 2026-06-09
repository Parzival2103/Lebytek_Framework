<?php
use App\Kernel\Helpers\ViewHelper;
/** @var \App\Application\DTO\Dashboard\DashboardViewModel $dashboard */
?>

<div class="ct-page">
<div class="ct-page-header mb-4">
    <h1 class="ct-page-title">Dashboard</h1>
    <p class="ct-page-subtitle">Resumen general del sistema.</p>
</div>
<?= ViewHelper::partial('dashboard/kpi_grid', compact('dashboard')) ?>

<?php
$widgets = $dashboard->widgets ?? [];
$validWidgets = [];
foreach ($widgets as $widget) {
    $partial = (string) ($widget['partial'] ?? '');
    // Whitelist: solo parciales bajo dashboard/, sin recorridos de ruta.
    if ($partial === '' || strncmp($partial, 'dashboard/', 10) !== 0 || strpos($partial, '..') !== false) {
        continue;
    }
    if (!preg_match('/^dashboard\/[a-z0-9_\/-]+$/i', $partial)) {
        continue;
    }
    if (!is_file(APP_PATH . '/Presentation/Views/partials/' . $partial . '.php')) {
        continue;
    }
    $validWidgets[] = $widget;
}
?>
<?php if ($validWidgets !== []): ?>
    <div class="row g-4 mb-4">
        <?php foreach ($validWidgets as $widget): ?>
            <div class="col-12 col-lg-6">
                <?= ViewHelper::partial($widget['partial'], (array) ($widget['data'] ?? [])) ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <?= ViewHelper::partial('dashboard/recent_activity', compact('dashboard')) ?>
    <?= ViewHelper::partial('dashboard/quick_links', compact('dashboard')) ?>
</div>

<?= ViewHelper::partial('dashboard/system_status', compact('dashboard')) ?>
</div>
