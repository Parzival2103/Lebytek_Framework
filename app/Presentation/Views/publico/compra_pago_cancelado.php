<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/** @var string $publicId */
?>
<section class="py-5">
  <div class="container" style="max-width: 720px;">
    <h1 class="h3 mb-3">Pago cancelado</h1>
    <p class="text-muted">
      No se realizó ningún cargo para la orden <?= ViewHelper::e($publicId) ?>.
    </p>
    <a class="btn btn-primary" href="/?compras=1#paquetes">Volver a comprar</a>
  </div>
</section>
