<?php
/** @var list<array{label:string,value:string}> $rows */
?>
<table class="pdf-totals">
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="pdf-totals-label"><?= htmlspecialchars((string) ($r['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
      <td class="pdf-totals-value"><?= htmlspecialchars((string) ($r['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
  <?php endforeach; ?>
</table>
