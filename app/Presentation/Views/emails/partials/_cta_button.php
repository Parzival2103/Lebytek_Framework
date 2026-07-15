<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/** @var string $url */
/** @var string $label */
/** @var string $bgColor */
$bgColor = $bgColor ?? '#0d6efd';
?>
<a href="<?= ViewHelper::e($url) ?>"
   style="
        display:inline-block;
        background:<?= ViewHelper::e($bgColor) ?>;
        color:#ffffff;
        text-decoration:none;
        padding:14px 24px;
        border-radius:8px;
        font-weight:bold;
   ">
    <?= ViewHelper::e($label) ?>
</a>
