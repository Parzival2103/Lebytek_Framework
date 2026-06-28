<?php
/** @var string $text @var string $style */
$cls = 'pdf-text pdf-text-' . preg_replace('/[^a-z]/', '', $style);
?>
<p class="<?= htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?></p>
