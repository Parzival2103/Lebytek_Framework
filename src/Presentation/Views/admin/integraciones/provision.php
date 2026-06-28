<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/** @var int $leadId */
/** @var bool $partnerActivo */
?>
<div class="ct-page">
    <h1 class="h4 mb-3"><?= ViewHelper::e($titulo ?? 'Provisionar demo') ?></h1>

    <div class="card ct-card">
        <div class="card-body">
            <form method="POST" action="/admin/integraciones/provision">
                <?= ViewHelper::csrfField() ?>
                <input type="hidden" name="lead_id" value="<?= (int) ($leadId ?? 0) ?>">

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label" for="lead_nombre">Nombre del lead</label>
                        <input type="text" class="form-control" id="lead_nombre" name="lead_nombre" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="lead_email">Correo del lead</label>
                        <input type="email" class="form-control" id="lead_email" name="lead_email" required>
                    </div>
                </div>

                <?php if ($partnerActivo): ?>
                    <div class="mb-3">
                        <label class="form-label d-block">Modo de provisión</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="modo" id="modo_auto" value="auto" checked>
                            <label class="form-check-label" for="modo_auto">Crear automáticamente (Partner API)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="modo" id="modo_manual" value="manual">
                            <label class="form-check-label" for="modo_manual">Ingresar credenciales manualmente</label>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row g-3" id="manualFields" <?= $partnerActivo ? 'style="display:none"' : '' ?>>
                    <div class="col-md-6">
                        <label class="form-label" for="instance_id">Instance ID</label>
                        <input type="text" class="form-control" id="instance_id" name="instance_id">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="token">Token</label>
                        <input type="password" class="form-control" id="token" name="token">
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Provisionar y enviar correo</button>
                    <a href="/admin/integraciones" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($partnerActivo): ?>
<script>
document.querySelectorAll('input[name="modo"]').forEach((el) => {
  el.addEventListener('change', () => {
    const manual = document.getElementById('manualFields');
    manual.style.display = document.getElementById('modo_manual').checked ? '' : 'none';
  });
});
</script>
<?php endif; ?>
