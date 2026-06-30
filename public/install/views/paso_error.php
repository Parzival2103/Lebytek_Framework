<?php /** @var string $pasoFallido */ /** @var string $mensaje */ /** @var string $csrf */ ?>
<h2 class="h5 mb-3"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Error de instalación</h2>

<div class="alert alert-danger">
  <p class="mb-1"><strong>Paso donde falló:</strong> <?= e($pasoFallido) ?></p>
  <p class="mb-0"><strong>Mensaje:</strong> <?= e($mensaje) ?></p>
</div>

<p class="text-muted small">
  Detalle técnico en <code>storage/logs/install-wizard.log</code>
  (y posiblemente <code>public/install/error_log</code> del hosting).
</p>
<p class="text-muted small">
  Si hubo intentos fallidos previos, vacíe la base de datos en phpMyAdmin,
  elimine <code>storage/install.lock</code> si existe y reintente.
</p>

<a href="?paso=revision" class="btn btn-outline-secondary">Volver a revisión</a>
