<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/** @var list<array<string,mixed>> $logs */
?>
<div class="table-responsive">
<table class="table table-sm mb-0">
  <thead><tr><th>Fecha</th><th>Canal</th><th>Destinatario</th><th>Estado</th><th>ID proveedor</th></tr></thead>
  <tbody>
  <?php if (empty($logs)): ?>
    <tr><td colspan="5" class="text-center text-muted py-3">Sin envíos registrados.</td></tr>
  <?php else: foreach ($logs as $l): ?>
    <tr>
      <td><?= ViewHelper::e((string) $l['created_at']) ?></td>
      <td><?= ViewHelper::e((string) $l['channel']) ?></td>
      <td><?= ViewHelper::e((string) $l['recipient_masked']) ?></td>
      <td><?= ViewHelper::e((string) $l['status']) ?></td>
      <td><?= ViewHelper::e((string) ($l['provider_message_id'] ?? '')) ?></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
</div>
