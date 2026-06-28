<?php
use App\Kernel\Helpers\ViewHelper;

if (empty($aggregationSkipped) || empty($aggregationSkipMessage)) {
    return;
}
?>
<div class="alert alert-warning border-0 shadow-sm mb-4 d-flex align-items-start gap-2" role="status">
    <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
    <div>
        <strong>Volumen de datos</strong>
        <p class="mb-0 small"><?= ViewHelper::e((string) $aggregationSkipMessage) ?></p>
    </div>
</div>
