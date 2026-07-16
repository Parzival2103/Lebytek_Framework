<?php declare(strict_types=1);
use Lebytek\Framework\Kernel\Helpers\ViewHelper;
/** @var string $titulo */
/** @var int $orderId */
/** @var array<string, mixed>|null $order */
/** @var bool $authorizeEnabled */
?>
<div class="container py-4">
  <h1 class="h3 mb-3"><?= ViewHelper::e($titulo) ?></h1>
  <?php if ($orderId <= 0 || $order === null): ?>
    <div class="alert alert-danger">Orden inválida.</div>
    <a class="btn btn-secondary" href="/admin/crud/mkt_ordenes">Volver</a>
  <?php elseif (! $authorizeEnabled): ?>
    <div class="alert alert-warning">La activación está deshabilitada por configuración (MKT_MEMBERSHIP_AUTHORIZE_ENABLED=false).</div>
    <a class="btn btn-secondary" href="/admin/crud/mkt_ordenes">Volver</a>
  <?php elseif ((string) ($order['status'] ?? '') !== 'paid'): ?>
    <div class="alert alert-info">Solo órdenes pagadas pueden activarse en la API (estado actual: <?= ViewHelper::e((string) ($order['status'] ?? '')) ?>).</div>
    <a class="btn btn-secondary" href="/admin/crud/mkt_ordenes">Volver</a>
  <?php elseif (trim((string) ($order['api_tenant_public_id'] ?? '')) === ''): ?>
    <div class="alert alert-warning">
      Asocia el <strong>tenant demo</strong> (<code>api_tenant_public_id</code>) en el CRUD de la orden antes de activar el plan.
    </div>
    <a class="btn btn-secondary" href="/admin/crud/mkt_ordenes/<?= (int) $orderId ?>/editar">Editar orden</a>
  <?php else: ?>
    <p class="text-muted mb-1">
      <strong><?= ViewHelper::e((string) ($order['nombre'] ?? '')) ?></strong>
      — <?= ViewHelper::e((string) ($order['email'] ?? '')) ?>
    </p>
    <p class="text-muted">
      Plan <?= ViewHelper::e((string) ($order['paquete_slug'] ?? '')) ?> /
      <?= ($order['ciclo'] ?? '') === 'annual' ? 'Anual' : 'Mensual' ?>
      — $<?= ViewHelper::e(number_format((float) ($order['precio_snapshot'] ?? 0), 2, '.', ',')) ?> MXN
    </p>
    <p class="text-muted">Tenant: <code><?= ViewHelper::e((string) $order['api_tenant_public_id']) ?></code></p>
    <?php if (! empty($order['api_activation_error'])): ?>
      <div class="alert alert-danger">Último error: <?= ViewHelper::e((string) $order['api_activation_error']) ?></div>
    <?php endif; ?>
    <p>El pago ya fue capturado. Se activará el plan en api.lebytek.com, se revocará el token demo y se enviará el correo de membresía al cliente.</p>
    <form method="post" action="/admin/marketing/ordenes/activar-plan">
      <?= ViewHelper::csrfField() ?>
      <input type="hidden" name="orden_id" value="<?= (int) $orderId ?>">
      <button type="submit" class="btn btn-primary">Confirmar activación</button>
      <a class="btn btn-outline-secondary ms-2" href="/admin/crud/mkt_ordenes">Cancelar</a>
    </form>
  <?php endif; ?>
</div>
