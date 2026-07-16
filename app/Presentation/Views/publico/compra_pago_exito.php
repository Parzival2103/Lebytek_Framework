<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/** @var array<string, mixed>|null $order */
/** @var string $publicId */
?>
<section class="py-5">
  <div class="container" style="max-width: 720px;">
    <h1 class="h3 mb-3">Pago recibido</h1>
    <div class="alert alert-info">
      Estamos confirmando tu pago. Te avisaremos cuando se active tu membresía.
    </div>
    <p class="text-muted mb-4">
      Orden <strong><?= ViewHelper::e((string) ($order['public_id'] ?? $publicId)) ?></strong>
    </p>
    <a class="btn btn-outline-secondary" href="/?compras=1#paquetes">Volver a paquetes</a>
  </div>
</section>
