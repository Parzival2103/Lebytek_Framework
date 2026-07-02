<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$docsUrl = rtrim((string) ($docsUrl ?? 'https://docs.lebytek.com'), '/');
?>
<section class="py-5">
    <div class="container py-lg-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="badge bg-success-subtle text-success-emphasis mb-3">WhatsApp API</span>
                <h1 class="display-5 fw-bold mb-3">Tu WhatsApp, conectado a tu software</h1>
                <p class="lead text-muted mb-4">
                    Panel de lectura para clientes Lebytek: consulta el estado de tu instancia,
                    escanea el QR y accede a la documentación de integración.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="/portal/acceso" class="btn btn-primary btn-lg">Acceder con token</a>
                    <a href="<?= ViewHelper::e($docsUrl) ?>" class="btn btn-outline-secondary btn-lg" target="_blank" rel="noopener">Documentación</a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3"><i class="bi bi-shield-check text-primary me-2"></i>Seguro y gestionado</h2>
                        <ul class="list-unstyled mb-0 text-muted">
                            <li class="mb-2"><i class="bi bi-check2 me-2"></i>Sin credenciales Green en el panel</li>
                            <li class="mb-2"><i class="bi bi-check2 me-2"></i>Token Sanctum almacenado en sesión server-side</li>
                            <li class="mb-0"><i class="bi bi-check2 me-2"></i>Envío de mensajes vía API REST</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
