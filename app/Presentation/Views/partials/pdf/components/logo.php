<?php
/** @var string $src @var int $height */
?>
<?php if ($src !== ''): ?>
  <div class="pdf-logo">
    <img src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>" style="height: <?= (int) $height ?>px;" alt="">
  </div>
<?php endif; ?>
