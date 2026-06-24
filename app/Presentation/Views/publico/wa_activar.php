<?php

use App\Kernel\Helpers\ViewHelper;

$error = $error ?? null;
$qr = $qr ?? null;
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 text-center">
            <h1 class="h3 mb-4">Activar WhatsApp</h1>
            <?php if ($error !== null): ?>
                <div class="alert alert-danger"><?= ViewHelper::e($error) ?></div>
            <?php elseif ($qr !== ''): ?>
                <p class="text-muted mb-3">Escanea este código QR con WhatsApp en tu teléfono.</p>
                <img src="data:image/png;base64,<?= ViewHelper::e($qr) ?>" alt="Código QR de WhatsApp" class="img-fluid border rounded">
            <?php else: ?>
                <div class="alert alert-warning">No se pudo obtener el código QR. Intenta más tarde.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
