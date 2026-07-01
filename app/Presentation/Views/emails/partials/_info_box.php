<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/** @var string $title */
/** @var list<string> $items */
/** @var string $borderColor */
$borderColor = $borderColor ?? '#0d6efd';
$bgColor = $bgColor ?? '#eef7ff';
?>
<div style="
    background:<?= ViewHelper::e($bgColor) ?>;
    border-left:4px solid <?= ViewHelper::e($borderColor) ?>;
    padding:16px;
    border-radius:6px;
    margin-bottom:24px;
">
    <strong><?= ViewHelper::e($title) ?></strong>
    <ul style="margin:12px 0 0 18px; padding:0; line-height:1.7;">
        <?php foreach ($items as $item): ?>
        <li><?= ViewHelper::e($item) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
