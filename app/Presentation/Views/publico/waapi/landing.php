<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$docsUrl = rtrim((string) ($docsUrl ?? 'https://docs.lebytek.com'), '/');
?>
<section class="py-5">
    <div class="container py-lg-4">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <span class="badge bg-success-subtle text-success-emphasis mb-3">Portal cliente</span>
                <h1 class="display-6 fw-bold mb-3">Consulta el uso de tu API WhatsApp</h1>
                <p class="lead text-muted mb-4 mx-auto" style="max-width: 36rem;">
                    Inicia sesión con el token del correo de credenciales y revisa cuántos mensajes
                    has enviado con tu cuenta demo o producción.
                </p>
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <a href="/portal/acceso" class="btn btn-primary btn-lg">Iniciar sesión con token</a>
                    <a href="<?= ViewHelper::e($docsUrl) ?>" class="btn btn-outline-secondary btn-lg" target="_blank" rel="noopener">Documentación API</a>
                </div>
            </div>
        </div>
    </div>
</section>
