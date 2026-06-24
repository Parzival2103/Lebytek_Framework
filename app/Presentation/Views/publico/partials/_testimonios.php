<?php
// app/Presentation/Views/publico/partials/_testimonios.php
declare(strict_types=1);

use App\Kernel\Helpers\ViewHelper;

$items = is_array($testimonios['items'] ?? null) ? $testimonios['items'] : [];
if ($items === []) {
    return;
}
?>
<section class="ct-testimonios" id="resenas">
  <div class="container">
    <div class="text-center mb-4">
      <h2 class="ct-section__title">Lo que dicen nuestros clientes</h2>
    </div>
    <div class="row g-4">
      <?php foreach ($items as $t): ?>
        <div class="col-md-4">
          <article class="ct-testimonio h-100" data-reveal>
            <div class="ct-testimonio__stars" aria-label="5 estrellas">
              <?php for ($i = 0; $i < 5; $i++): ?><i class="bi bi-star-fill" aria-hidden="true"></i><?php endfor; ?>
            </div>
            <p class="ct-testimonio__text">&ldquo;<?= ViewHelper::e((string) ($t['texto'] ?? '')) ?>&rdquo;</p>
            <footer class="ct-testimonio__autor"><?= ViewHelper::e((string) ($t['autor'] ?? '')) ?></footer>
          </article>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
