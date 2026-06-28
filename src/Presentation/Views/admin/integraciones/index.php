<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/** @var \Lebytek\Framework\Domain\Integrations\IntegrationAccount|null $instancia */
/** @var bool $partnerActivo */
/** @var list<array<string,mixed>> $logs */
?>
<div class="ct-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0"><?= ViewHelper::e($titulo ?? 'Integraciones') ?></h1>
        <?php if ($partnerActivo): ?>
            <span class="badge bg-success" title="GREEN_API_PARTNER_TOKEN configurado en .env">Partner API activo</span>
        <?php else: ?>
            <span class="badge bg-secondary" title="Falta GREEN_API_PARTNER_TOKEN en .env (provisión auto de demos)">Provisión manual</span>
        <?php endif; ?>
    </div>

    <div class="card ct-card mb-4">
        <div class="ct-card-header">
            <span class="ct-card-title">Instancia interna (WhatsApp)</span>
        </div>
        <div class="card-body">
            <form method="POST" action="/admin/integraciones/config/internal" class="row g-3">
                <?= ViewHelper::csrfField() ?>
                <div class="col-md-6">
                    <label class="form-label" for="instance_id">Instance ID</label>
                    <input type="text" class="form-control" id="instance_id" name="instance_id"
                           value="<?= ViewHelper::e($instancia?->instanceId ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="token">Token</label>
                    <input type="password" class="form-control" id="token" name="token"
                           placeholder="<?= $instancia !== null ? '••••••••' : '' ?>" required>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Guardar instancia</button>
                    <button type="button" class="btn btn-outline-secondary" id="btnTestConnection">Probar conexión</button>
                </div>
            </form>
            <div id="testResult" class="small mt-2 text-muted"></div>
        </div>
    </div>

    <div class="card ct-card mb-4">
        <div class="ct-card-header">
            <span class="ct-card-title">Registro de envíos (int_logs)</span>
        </div>
        <div class="card-body p-0">
            <?= ViewHelper::partial('admin/integraciones/_logs', ['logs' => $logs ?? []]) ?>
        </div>
    </div>
</div>

<script>
document.getElementById('btnTestConnection')?.addEventListener('click', async () => {
  const out = document.getElementById('testResult');
  out.textContent = 'Probando…';
  try {
    const fd = new FormData();
    const csrf = document.querySelector('input[name="_csrf_token"]')?.value
      || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      || '';
    fd.append('_csrf_token', csrf);
    const res = await fetch('/admin/integraciones/test', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
    });
    const raw = await res.text();
    let data;
    try {
      data = JSON.parse(raw);
    } catch (e) {
      out.textContent = 'Respuesta inesperada del servidor (¿sesión expirada?). Recarga la página e intenta de nuevo.';
      return;
    }
    out.textContent = data.ok ? ('Estado: ' + (data.state || 'ok')) : (data.error || 'Error');
  } catch (e) {
    out.textContent = 'Error de red';
  }
});
</script>
