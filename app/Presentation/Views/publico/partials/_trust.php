<?php
// app/Presentation/Views/publico/partials/_trust.php
declare(strict_types=1);

use App\Kernel\Helpers\ViewHelper;

$items = is_array($trust['items'] ?? null) ? $trust['items'] : [];
if ($items === []) {
    return;
}
?>
<section class="ct-trust" aria-label="Indicadores de confianza">
  <div class="container">
    <div class="row g-4 text-center">
      <?php foreach ($items as $it): ?>
        <div class="col-6 col-md-<?= count($items) >= 4 ? '3' : '4' ?>">
          <div class="ct-trust__item">
            <span class="ct-trust__valor"><?= ViewHelper::e((string) ($it['valor'] ?? '')) ?></span>
            <span class="ct-trust__etiqueta"><?= ViewHelper::e((string) ($it['etiqueta'] ?? '')) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
