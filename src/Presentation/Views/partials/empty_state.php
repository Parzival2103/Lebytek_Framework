<?php

declare(strict_types=1);

use App\Kernel\Helpers\ViewHelper;

$icon = (string) ($icon ?? 'bi-inbox');
$title = (string) ($title ?? 'Sin contenido');
$hint = isset($hint) ? (string) $hint : '';
$extraClass = (string) ($extraClass ?? '');

?>
<div class="ct-empty-state <?= ViewHelper::e($extraClass) ?>">
    <i class="bi <?= ViewHelper::e($icon) ?> ct-empty-state__icon" aria-hidden="true"></i>
    <p class="ct-empty-state__title"><?= ViewHelper::e($title) ?></p>
    <?php if ($hint !== ''): ?>
        <p class="ct-empty-state__hint"><?= ViewHelper::e($hint) ?></p>
    <?php endif; ?>
</div>
