<?php
/** @var list<string> $labels */
?>
<div class="pdf-signatures">
  <?php foreach ($labels as $label): ?>
    <div class="pdf-signature">
      <div class="pdf-signature-line">&nbsp;</div>
      <div class="pdf-signature-label"><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  <?php endforeach; ?>
</div>
