<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/*
|--------------------------------------------------------------------------
| Partial: avatar_thumb
|--------------------------------------------------------------------------
| Miniatura circular de avatar con fallback a inicial.
| Variables que define el include:
|   $thumbClase  string   'topbar-avatar' | 'sidebar-avatar' | 'table-avatar' (+ extras)
|   $thumbRuta   ?string  ruta del avatar actual (null/vacío => inicial)
|   $thumbNombre string   nombre para la inicial y el alt
*/

$thumbRuta   = (string) ($thumbRuta ?? '');
$thumbNombre = (string) ($thumbNombre ?? 'U');
?>
<div class="<?= ViewHelper::e($thumbClase ?? 'table-avatar') ?>">
    <?php if ($thumbRuta !== ''): ?>
        <img src="<?= ViewHelper::e($thumbRuta) ?>" alt="<?= ViewHelper::e($thumbNombre) ?>">
    <?php else: ?>
        <?= strtoupper(substr($thumbNombre, 0, 1)) ?: 'U' ?>
    <?php endif; ?>
</div>
