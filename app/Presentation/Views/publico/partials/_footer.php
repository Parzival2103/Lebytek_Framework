<?php
// app/Presentation/Views/publico/partials/_footer.php
declare(strict_types=1);

use App\Kernel\Helpers\ViewHelper;

$footer        = is_array($footer ?? null) ? $footer : [];
$columnas      = is_array($footer['columnas'] ?? null) ? $footer['columnas'] : [];
$legal         = (string) ($footer['legal'] ?? '');
$empresaNombre = (string) ($empresaNombre ?? '');
?>
<footer class="ct-footer">
  <div class="container">
    <?php if ($columnas !== []): ?>
      <div class="row g-4">
        <div class="col-lg-4">
          <div class="ct-footer__brand"><?= ViewHelper::e($empresaNombre) ?></div>
          <?php if ($legal !== ''): ?><p class="ct-footer__legal"><?= ViewHelper::e($legal) ?></p><?php endif; ?>
        </div>
        <?php foreach ($columnas as $col): ?>
          <div class="col-6 col-lg-2">
            <h4 class="ct-footer__col-title"><?= ViewHelper::e((string) ($col['titulo'] ?? '')) ?></h4>
            <ul class="ct-footer__links">
              <?php foreach ((is_array($col['links'] ?? null) ? $col['links'] : []) as $ln): ?>
                <li><a href="<?= ViewHelper::e((string) ($ln['url'] ?? '#')) ?>"><?= ViewHelper::e((string) ($ln['texto'] ?? '')) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
      </div>
      <hr class="ct-footer__sep">
    <?php endif; ?>
    <div class="ct-footer__bottom text-center">
      &copy; <?= date('Y') ?> <?= ViewHelper::e($empresaNombre) ?>
    </div>
  </div>
</footer>
