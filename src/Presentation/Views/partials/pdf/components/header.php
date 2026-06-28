<?php
/** @var string $title @var string $subtitle */
?>
<div class="pdf-header">
  <h1 class="pdf-h1"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
  <?php if ($subtitle !== ''): ?>
    <p class="pdf-subtitle"><?= htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>
</div>
