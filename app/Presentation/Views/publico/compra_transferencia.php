<?php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/** @var array<string, mixed> $order */
/** @var array{bank_name:string,beneficiary:string,clabe:string,account:string,proof_guide:string,reference:string} $bank */
?>
<section class="py-5">
  <div class="container" style="max-width: 720px;">
    <h1 class="h3 mb-2">Instrucciones de transferencia</h1>
    <p class="text-muted mb-4">
      Orden <strong><?= ViewHelper::e((string) ($order['public_id'] ?? '')) ?></strong>
      — <?= ViewHelper::e(ucfirst((string) ($order['paquete_slug'] ?? ''))) ?>
      (<?= ($order['ciclo'] ?? '') === 'annual' ? 'Anual' : 'Mensual' ?>)
    </p>

    <div class="card shadow-sm border-0 mb-4">
      <div class="card-body p-4">
        <h2 class="h5">Datos bancarios</h2>
        <ul class="list-unstyled mb-0">
          <?php if ($bank['bank_name'] !== ''): ?>
            <li><strong>Banco:</strong> <?= ViewHelper::e($bank['bank_name']) ?></li>
          <?php endif; ?>
          <?php if ($bank['beneficiary'] !== ''): ?>
            <li><strong>Beneficiario:</strong> <?= ViewHelper::e($bank['beneficiary']) ?></li>
          <?php endif; ?>
          <?php if ($bank['clabe'] !== ''): ?>
            <li><strong>CLABE:</strong> <?= ViewHelper::e($bank['clabe']) ?></li>
          <?php endif; ?>
          <?php if ($bank['account'] !== ''): ?>
            <li><strong>Cuenta:</strong> <?= ViewHelper::e($bank['account']) ?></li>
          <?php endif; ?>
          <li><strong>Referencia:</strong> <?= ViewHelper::e($bank['reference']) ?></li>
          <li><strong>Monto:</strong> $<?= ViewHelper::e(number_format((float) ($order['precio_snapshot'] ?? 0), 2, '.', ',')) ?> MXN</li>
        </ul>
      </div>
    </div>

    <div class="alert alert-info">
      <strong>Comprobante de pago</strong><br>
      <?= ViewHelper::e($bank['proof_guide']) ?>
    </div>

    <p class="text-muted small mb-0">
      Una vez recibamos y validemos tu pago, activaremos tu membresía en la misma cuenta demo de WhatsApp.
      Recibirás un correo con tu nuevo token de acceso.
    </p>

    <a class="btn btn-outline-secondary mt-4" href="/?compras=1#paquetes">Volver a paquetes</a>
  </div>
</section>
