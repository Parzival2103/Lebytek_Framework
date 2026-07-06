<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$signals = is_array($signals ?? null) ? $signals : [];
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-0">
        <h3 class="h6 mb-0">Clientes en riesgo</h3>
    </div>
    <div class="card-body p-0">
        <?php if ($signals === []): ?>
            <p class="text-muted small px-3 py-3 mb-0">No hay señales abiertas.</p>
        <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($signals as $signal): ?>
                    <?php
                    $leadId = (int) ($signal['lead_id'] ?? 0);
                    $type = (string) ($signal['signal_type'] ?? '');
                    $severity = (string) ($signal['severity'] ?? 'medium');
                    $nombre = (string) ($signal['lead_nombre'] ?? 'Lead #'.$leadId);
                    $badge = match ($severity) {
                        'high' => 'danger',
                        'low' => 'secondary',
                        default => 'warning',
                    };
                    $url = $leadId > 0 ? '/admin/crud/mkt_leads/'.$leadId : '#';
                    ?>
                    <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <a href="<?= ViewHelper::e($url) ?>" class="text-decoration-none fw-semibold"><?= ViewHelper::e($nombre) ?></a>
                            <div class="small text-muted"><?= ViewHelper::e($type) ?></div>
                        </div>
                        <span class="badge text-bg-<?= ViewHelper::e($badge) ?>"><?= ViewHelper::e($severity) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
