<?php use Lebytek\Framework\Kernel\Helpers\ViewHelper; ?>

<?php foreach ($flashAll ?? [] as $type => $message): ?>
    <?php
    $bsType = match($type) {
        'success' => 'success',
        'error'   => 'danger',
        'warning' => 'warning',
        'info'    => 'info',
        default   => 'secondary',
    };
    $icon = match($type) {
        'success' => 'bi-check-circle-fill',
        'error'   => 'bi-x-circle-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        'info'    => 'bi-info-circle-fill',
        default   => 'bi-bell-fill',
    };
    ?>
    <?php if ($type === 'errors' && is_array($message)): ?>
        <div class="alert alert-danger alert-dismissible d-flex align-items-start gap-2 fade show" role="alert">
            <i class="bi bi-x-circle-fill mt-1 flex-shrink-0"></i>
            <div>
                <strong>Por favor corrige los siguientes errores:</strong>
                <ul class="mb-0 mt-1">
                    <?php foreach ($message as $field => $err): ?>
                        <li><?= ViewHelper::e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (is_string($message)): ?>
        <div class="alert alert-<?= $bsType ?> alert-dismissible d-flex align-items-center gap-2 fade show" role="alert">
            <i class="bi <?= $icon ?> flex-shrink-0"></i>
            <span><?= ViewHelper::e($message) ?></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
