<?php declare(strict_types=1);
use Lebytek\Framework\Kernel\Helpers\ViewHelper;
/** @var string $titulo */
/** @var int $leadId */
/** @var array<string, mixed>|null $lead */
?>
<div class="container py-4">
  <h1 class="h3 mb-3"><?= ViewHelper::e($titulo) ?></h1>
  <?php if ($leadId <= 0 || $lead === null): ?>
    <div class="alert alert-danger">Lead inválido.</div>
    <a class="btn btn-secondary" href="/admin/crud/mkt_leads">Volver</a>
  <?php elseif (($lead['estado'] ?? '') === 'demo_baja_pendiente'): ?>
    <div class="alert alert-info">La baja de esta demo ya está en curso. Green API elimina la instancia en segundo plano; el estado pasará a «demo dada de baja» al confirmarse.</div>
    <a class="btn btn-secondary" href="/admin/crud/mkt_leads">Volver</a>
  <?php elseif (empty($lead['api_tenant_public_id'])): ?>
    <div class="alert alert-warning">Este lead no tiene demo activa para dar de baja.</div>
    <a class="btn btn-secondary" href="/admin/crud/mkt_leads">Volver</a>
  <?php else: ?>
    <p class="text-muted mb-1"><strong><?= ViewHelper::e((string) ($lead['nombre'] ?? '')) ?></strong> — <?= ViewHelper::e((string) ($lead['email'] ?? '')) ?></p>
    <p class="text-muted">Tenant: <code><?= ViewHelper::e((string) $lead['api_tenant_public_id']) ?></code></p>
    <div class="alert alert-warning">
      Se encolará la eliminación de las instancias WhatsApp en Green API (proceso asíncrono).
      El lead quedará en «baja pendiente» hasta que se confirme que ya no hay instancias en la API.
      Las demos con más de 30 días se dan de baja automáticamente vía cron.
    </div>
    <form method="post" action="/admin/marketing/leads/deprovision-api">
      <?= ViewHelper::csrfField() ?>
      <input type="hidden" name="lead_id" value="<?= (int) $leadId ?>">
      <button type="submit" class="btn btn-danger">Confirmar baja de demo</button>
      <a class="btn btn-outline-secondary ms-2" href="/admin/crud/mkt_leads">Cancelar</a>
    </form>
  <?php endif; ?>
</div>
