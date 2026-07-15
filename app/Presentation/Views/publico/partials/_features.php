<?php
// app/Presentation/Views/publico/partials/_features.php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$features = is_array($features ?? null) ? $features : [];
$items    = is_array($features['items'] ?? null) ? $features['items'] : [];
$titulo   = (string) ($features['titulo'] ?? 'Funcionalidades');
$lead     = (string) ($features['lead'] ?? '');

if ($items === []) {
    return;
}
?>
<section class="ct-features" id="funciones">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="ct-section__title"><?= ViewHelper::e($titulo) ?></h2>
      <?php if ($lead !== ''): ?><p class="ct-section__lead"><?= ViewHelper::e($lead) ?></p><?php endif; ?>
    </div>
    <div class="row g-4">
      <?php foreach ($items as $item): ?>
        <div class="col-md-6 col-lg-4">
          <article class="ct-feature h-100" data-reveal>
            <span class="ct-feature__icon" aria-hidden="true">
              <i class="bi <?= ViewHelper::e((string) ($item['icon'] ?? 'bi-check-circle-fill')) ?>"></i>
            </span>
            <h3 class="ct-feature__title"><?= ViewHelper::e((string) ($item['titulo'] ?? '')) ?></h3>
            <p class="ct-feature__text"><?= ViewHelper::e((string) ($item['texto'] ?? '')) ?></p>
          </article>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
