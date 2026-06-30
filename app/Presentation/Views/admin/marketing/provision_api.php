<?php declare(strict_types=1);
use Lebytek\Framework\Kernel\Helpers\ViewHelper;
/** @var string $titulo */
/** @var int $leadId */
?>
<div class="container py-4">
  <h1 class="h3 mb-3"><?= ViewHelper::e($titulo) ?></h1>
  <?php if ($leadId <= 0): ?>
    <div class="alert alert-danger">Lead inválido.</div>
    <a class="btn btn-secondary" href="/admin/crud/mkt_leads">Volver</a>
  <?php else: ?>
    <p class="text-muted">Se creará tenant + instancia WhatsApp en api.lebytek.com y se enviará el 2º correo al cliente con su token.</p>
    <form method="post" action="/admin/marketing/leads/provision-api">
      <?= ViewHelper::csrfField() ?>
      <input type="hidden" name="lead_id" value="<?= (int) $leadId ?>">
      <button type="submit" class="btn btn-primary">Confirmar provisioning</button>
      <a class="btn btn-outline-secondary ms-2" href="/admin/crud/mkt_leads">Cancelar</a>
    </form>
  <?php endif; ?>
</div>
