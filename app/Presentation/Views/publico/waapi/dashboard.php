<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$instance = is_array($instance ?? null) ? $instance : null;
$usage = is_array($usage ?? null) ? $usage : null;
$accountStatus = is_array($accountStatus ?? null) ? $accountStatus : null;
$error = is_string($error ?? null) ? $error : null;
$docsUrl = (string) ($docsUrl ?? 'https://docs.lebytek.com');

$plan = is_array($accountStatus['plan'] ?? null) ? $accountStatus['plan'] : [];
$demo = is_array($accountStatus['demo'] ?? null) ? $accountStatus['demo'] : [];
$usageQuota = is_array($accountStatus['usage'] ?? null) ? $accountStatus['usage'] : [];

$daysRemaining = $demo['daysRemaining'] ?? null;
$messagesRemaining = $usageQuota['messagesRemainingThisMonth'] ?? null;
$messagesSent = $usageQuota['messagesSentThisMonth'] ?? ($usage['messagesSent'] ?? null);
$planName = (string) ($plan['name'] ?? 'Demo');
$requestedAt = (string) ($accountStatus['requestedAt'] ?? '');
$expiresSoon = is_int($daysRemaining) && $daysRemaining <= 7;

$status = (string) ($instance['status'] ?? 'unknown');
$badgeClass = match ($status) {
    'authorized' => 'success',
    'waiting_qr', 'configuring' => 'warning',
    'provisioning' => 'info',
    default => 'secondary',
};
?>
<div class="container py-4 py-lg-5">
    <div class="mb-4">
        <h1 class="h3 mb-1">Panel cliente</h1>
        <p class="text-muted mb-0">Cuota de demo, uso de mensajes e instancia WhatsApp.</p>
    </div>

    <?php if ($error !== null): ?>
        <div class="alert alert-warning"><?= ViewHelper::e($error) ?></div>
    <?php endif; ?>

    <?php if ($accountStatus !== null): ?>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Paquete</div>
                        <div class="h5 mb-0"><?= ViewHelper::e($planName) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Días restantes</div>
                        <div class="h5 mb-0">
                            <?= $daysRemaining !== null ? ViewHelper::e((string) $daysRemaining) : '?' ?>
                            <?php if ($expiresSoon): ?>
                                <span class="badge text-bg-warning ms-1">Por vencer</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Mensajes restantes</div>
                        <div class="h5 mb-0"><?= $messagesRemaining !== null ? ViewHelper::e((string) $messagesRemaining) : '?' ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Enviados (mes)</div>
                        <div class="h5 mb-0"><?= $messagesSent !== null ? ViewHelper::e((string) $messagesSent) : '?' ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($requestedAt !== ''): ?>
            <p class="text-muted small mb-4">Consulta API: <?= ViewHelper::e($requestedAt) ?></p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($instance !== null): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h2 class="h6 mb-1"><?= ViewHelper::e((string) ($instance['label'] ?? 'Instancia')) ?></h2>
                        <p class="text-muted small mb-0 font-monospace"><?= ViewHelper::e((string) ($instance['publicId'] ?? '')) ?></p>
                    </div>
                    <span class="badge text-bg-<?= ViewHelper::e($badgeClass) ?>"><?= ViewHelper::e($status) ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($usage !== null): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 pt-3">
                <h2 class="h6 mb-0">Detalle de mensajes</h2>
            </div>
            <div class="card-body pt-0">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4">Enviados (total)</dt>
                    <dd class="col-sm-8"><?= ViewHelper::e((string) ($usage['messagesSent'] ?? 0)) ?></dd>
                    <dt class="col-sm-4">Recibidos</dt>
                    <dd class="col-sm-8"><?= ViewHelper::e((string) ($usage['messagesReceived'] ?? 0)) ?></dd>
                </dl>
            </div>
        </div>
    <?php endif; ?>

    <a href="<?= ViewHelper::e($docsUrl) ?>" class="btn btn-outline-secondary" target="_blank" rel="noopener">Documentación API</a>
</div>
