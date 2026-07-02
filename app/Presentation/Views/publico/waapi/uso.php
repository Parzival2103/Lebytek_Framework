<?php

$usageAvailable = (bool) ($usageAvailable ?? false);
$messagesSent = $messagesSent ?? null;
$messagesReceived = $messagesReceived ?? null;
?>
<section class="py-5">
    <div class="container">
        <h1 class="h3 mb-4">Resumen de uso</h1>

        <?php if (! $usageAvailable): ?>
            <div class="alert alert-info">
                Los contadores de mensajes estarán disponibles próximamente desde la API.
                Mientras tanto, puedes consultar el estado de tus envíos con
                <code>GET /messages/{publicId}</code>.
            </div>
        <?php else: ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <p class="text-muted mb-1">Mensajes enviados</p>
                            <p class="display-6 mb-0"><?= (int) $messagesSent ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <p class="text-muted mb-1">Mensajes recibidos</p>
                            <p class="display-6 mb-0"><?= (int) $messagesReceived ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <a href="/portal/dashboard" class="btn btn-outline-secondary mt-4">Volver al panel</a>
    </div>
</section>
