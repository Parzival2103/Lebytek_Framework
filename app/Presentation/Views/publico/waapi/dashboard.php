<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$instance = is_array($instance ?? null) ? $instance : null;
$status = (string) ($instance['status'] ?? 'unknown');
$label = (string) ($instance['label'] ?? 'Instancia');
$publicId = (string) ($instance['publicId'] ?? '');
$docsUrl = (string) ($docsUrl ?? 'https://docs.lebytek.com');

$badgeClass = match ($status) {
    'authorized' => 'success',
    'waiting_qr', 'configuring' => 'warning',
    'provisioning' => 'info',
    default => 'secondary',
};
?>
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Panel cliente</h1>
            <form method="post" action="/portal/logout" class="d-inline">
                <?= ViewHelper::csrfField() ?>
                <button type="submit" class="btn btn-outline-secondary btn-sm">Cerrar sesión</button>
            </form>
        </div>

        <?php if ($instance === null): ?>
            <div class="alert alert-warning">No se encontró ninguna instancia para tu cuenta.</div>
        <?php else: ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h2 class="h5 mb-1"><?= ViewHelper::e($label) ?></h2>
                            <p class="text-muted small mb-0 font-monospace"><?= ViewHelper::e($publicId) ?></p>
                        </div>
                        <span class="badge text-bg-<?= ViewHelper::e($badgeClass) ?>"><?= ViewHelper::e($status) ?></span>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <?php if ($status !== 'authorized'): ?>
                    <a href="/portal/qr" class="btn btn-primary">Ver código QR</a>
                <?php endif; ?>
                <a href="/portal/uso" class="btn btn-outline-primary">Resumen de uso</a>
                <a href="<?= ViewHelper::e($docsUrl) ?>" class="btn btn-outline-secondary" target="_blank" rel="noopener">Documentación API</a>
            </div>
        <?php endif; ?>
    </div>
</section>
