<?php
// app/Presentation/Views/publico/portal.php

use App\Kernel\Helpers\ViewHelper;

$mensaje   = $mensaje ?? '';
$provision = $provision ?? null;
?>
<section class="py-5">
    <div class="container" style="max-width:640px;">
        <div class="card">
            <div class="card-body">
                <h1 class="h4 mb-3">Portal del cliente</h1>
                <p class="text-muted"><?= ViewHelper::e($mensaje) ?></p>
                <?php if ($provision !== null): ?>
                    <p class="small mb-0">Estado de tu provisión: <strong><?= ViewHelper::e((string) ($provision['estado'] ?? 'pendiente')) ?></strong></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
