<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$usage = is_array($usage ?? null) ? $usage : null;
$usageError = is_string($usageError ?? null) ? $usageError : null;
$docsUrl = (string) ($docsUrl ?? 'https://docs.lebytek.com');

$messagesSent = (int) ($usage['messagesSent'] ?? 0);
$messagesReceived = (int) ($usage['messagesReceived'] ?? 0);
$byStatus = is_array($usage['messagesSentByStatus'] ?? null) ? $usage['messagesSentByStatus'] : [];
$sentOk = (int) ($byStatus['sent'] ?? 0);
$queued = (int) ($byStatus['queued'] ?? 0);
$failed = (int) ($byStatus['failed'] ?? 0);
?>
<section class="py-5">
    <div class="container">
        <div class="mb-4">
            <h1 class="h3 mb-1">Uso de mensajes</h1>
            <p class="text-muted mb-0">Resumen de actividad de tu cuenta en la API.</p>
        </div>

        <?php if ($usageError !== null): ?>
            <div class="alert alert-warning">
                No se pudieron cargar las métricas: <?= ViewHelper::e($usageError) ?>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card waapi-metric-card">
                    <div class="card-body p-4">
                        <p class="metric-label mb-0">Mensajes enviados</p>
                        <p class="metric-value mb-0"><?= $messagesSent ?></p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card waapi-metric-card">
                    <div class="card-body p-4">
                        <p class="metric-label mb-0">Mensajes recibidos</p>
                        <p class="metric-value mb-0"><?= $messagesReceived ?></p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card waapi-metric-card">
                    <div class="card-body p-4">
                        <p class="metric-label mb-0">Entregados</p>
                        <p class="metric-value mb-0 text-success"><?= $sentOk ?></p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card waapi-metric-card">
                    <div class="card-body p-4">
                        <p class="metric-label mb-0">En cola / fallidos</p>
                        <p class="metric-value mb-0">
                            <span class="text-warning"><?= $queued ?></span>
                            <span class="text-muted fs-5">/</span>
                            <span class="text-danger"><?= $failed ?></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h6 mb-2">Integración</h2>
                <p class="text-muted small mb-3">
                    Para enviar mensajes o consultar detalle de un envío, usa la API REST documentada.
                    Este panel muestra solo contadores agregados.
                </p>
                <a href="<?= ViewHelper::e($docsUrl) ?>" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
                    Ver documentación
                </a>
            </div>
        </div>
    </div>
</section>
