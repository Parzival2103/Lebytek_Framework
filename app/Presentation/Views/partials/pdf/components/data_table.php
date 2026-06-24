<?php
/** @var list<string> $headers @var list<list<string>> $matrix */
?>
<table class="pdf-table">
  <thead>
    <tr>
      <?php foreach ($headers as $h): ?>
        <th><?= htmlspecialchars((string) $h, ENT_QUOTES, 'UTF-8') ?></th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
    <?php if ($matrix === []): ?>
      <tr><td class="pdf-empty" colspan="<?= max(1, count($headers)) ?>">Sin datos.</td></tr>
    <?php else: ?>
      <?php foreach ($matrix as $row): ?>
        <tr>
          <?php foreach ($row as $cell): ?>
            <td><?= htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8') ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>
